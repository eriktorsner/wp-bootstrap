<?php

namespace Wpbootstrap\Import;

use Wpbootstrap\Bootstrap;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ImportMenus
 * @package Wpbootstrap\Import
 */
class ImportMenus
{
    /**
     * @var array
     */
    private $menus = array();

    /**
     * @var array
     */
    private $skipped_meta_fields = array(
        '_menu_item_menu_item_parent',
        '_menu_item_object_id',
        '_menu_item_object',
    );

    /**
     * ImportMenus constructor.
     */
    public function __construct()
    {
    }

    public function import()
    {
        $app = Bootstrap::getApplication();
        $settings = $app['settings'];
        $yaml = new Yaml();

        $helpers = $app['helpers'];
        $baseUrl = get_option('siteurl');

        if (!isset($settings['content']['menus'])) {
            return;
        }

        foreach ($settings['content']['menus'] as $menu) {
            $dir = WPBOOT_BASEPATH."/bootstrap/menus/$menu";
            $menuMeta = $yaml->parse(file_get_contents(WPBOOT_BASEPATH."/bootstrap/menus/{$menu}_manifest"));

            $newMenu = new \stdClass();
            $newMenu->slug = $menu;
            $newMenu->locations = $menuMeta['locations'];
            $newMenu->items = array();
            foreach ($helpers->getFiles($dir) as $file) {
                $menuItem = new \stdClass();
                $menuItem->done = false;
                $menuItem->id = 0;
                $menuItem->parentId = 0;
                $menuItem->slug = $file;
                //$menuItem->menu = unserialize(file_get_contents($dir.'/'.$file));
                $menuItem->menu = $yaml->parse(file_get_contents("$dir/$file"));
                $newMenu->items[] = $menuItem;
            }
            usort($newMenu->items, function ($a, $b) {
                return (int) $a->menu['menu_order'] - (int) $b->menu['menu_order'];
            });
            $this->menus[] = $newMenu;
        }

        $helpers->fieldSearchReplace($this->menus, Bootstrap::NEUTRALURL, $baseUrl);
        $this->process();
    }

    /**
     * The main import process
     */
    private function process()
    {
        remove_all_filters('wp_get_nav_menu_items');
        $locations = array();

        foreach ($this->menus as $menu) {
            $this->processMenu($menu);
            foreach ($menu->locations as $location) {
                $locations[$location] = $menu->id;
            }
        }
        set_theme_mod('nav_menu_locations', $locations);
    }

    /**
     * Process individual menu
     *
     * @param $menu
     */
    private function processMenu(&$menu)
    {
        $app = Bootstrap::getApplication();
        $import = $app['import'];

        $objMenu = wp_get_nav_menu_object($menu->slug);
        if (!$objMenu) {
            wp_create_nav_menu($menu->slug);
            $objMenu = wp_get_nav_menu_object($menu->slug);
        }
        $menuId = $objMenu->term_id;
        $menu->id = $menuId;

        $existingMenuItems = wp_get_nav_menu_items($menu->slug);
        foreach ($existingMenuItems as $existingMenuItem) {
            wp_delete_post($existingMenuItem->ID, true);
        }

        foreach ($menu->items as &$objMenuItem) {
            $menuItemType = $objMenuItem->menu['post_meta']['_menu_item_type'][0];
            $newTarget = 0;
            switch ($menuItemType) {
                case 'post_type':
                    $newTarget = $import->findTargetObjectId(
                        $objMenuItem->menu['post_meta']['_menu_item_object_id'][0],
                        'post'
                    );
                    break;
                case 'taxonomy':
                    $newTarget = $import->findTargetObjectId(
                        $objMenuItem->menu['post_meta']['_menu_item_object_id'][0],
                        'term'
                    );
                    break;
            }

            $parentItem = $this->findMenuItem($objMenuItem->menu['post_meta']['_menu_item_menu_item_parent'][0]);

            $args = array(
                    'menu-item-title' => $objMenuItem->menu['post_title'],
                    'menu-item-position' => $objMenuItem->menu['menu_order'],
                    'menu-item-description' => $objMenuItem->menu['post_content'],
                    'menu-item-attr-title' => $objMenuItem->menu['post_title'],
                    'menu-item-status' => $objMenuItem->menu['post_status'],
                    'menu-item-type' => $menuItemType,
                    'menu-item-object' => $objMenuItem->menu['post_meta']['_menu_item_object'][0],
                    'menu-item-object-id' => $newTarget,
                    'menu-item-url' => $objMenuItem->menu['post_meta']['_menu_item_url'][0],
                    'menu-item-parent-id' => $parentItem,
            );
            $ret = wp_update_nav_menu_item($menuId, 0, $args);
            $objMenuItem->id = $ret;

            foreach ($objMenuItem->menu['post_meta'] as $key => $meta) {
                if (in_array($key, $this->skipped_meta_fields) || substr($key, 0, 1) == '_') {
                    continue;
                }
                $val = $meta[0];
                update_post_meta($ret, $key, $val);
            }
        }
    }



    /**
     * Finds a menu item based on it's original id. If found, returns the new (after import) id
     *
     * @param int $target
     * @return int
     */
    private function findMenuItem($target)
    {
        foreach ($this->menus as $menu) {
            foreach ($menu->items as $item) {
                if ($item->menu['ID'] == $target) {
                    return $item->id;
                }
            }
        }

        return 0;
    }
}
