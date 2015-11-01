<?php
namespace Wpbootstrap;

class Pushmenus
{
    private $menus = array();
    private $skipped_meta_fields = array(
        '_menu_item_menu_item_parent',
        '_menu_item_object_id',
        '_menu_item_object',
    );

    private $bootstrap;
    private $import;
    private $resolver;

    public function __construct()
    {
        $this->bootstrap = Bootstrap::getInstance();
        $this->import = Import::getInstance();
        $this->resolver = Resolver::getInstance();

        foreach ($this->bootstrap->appSettings->wpbootstrap->menus as $menu => $locations) {
            $dir = BASEPATH."/bootstrap/menus/$menu";
            $newMenu = new \stdClass();
            $newMenu->slug = $menu;
            $newMenu->locations = $locations;
            $newMenu->items = array();
            foreach ($this->getFiles($dir) as $file) {
                $menuItem = new \stdClass();
                $menuItem->done = false;
                $menuItem->id = 0;
                $menuItem->parentId = 0;
                $menuItem->slug = $file;
                $menuItem->menu = unserialize(file_get_contents($dir.'/'.$file));
                $newMenu->items[] = $menuItem;
            }
            usort($newMenu->items, function ($a, $b) {
                return (int) $a->menu->menu_order - (int) $b->menu->menu_order;
            });
            $this->menus[] = $newMenu;
        }
        $baseUrl = get_option('siteurl');
        $neutralUrl = Bootstrap::NETURALURL;
        $this->resolver->fieldSearchReplace($this->menus, Bootstrap::NETURALURL, $this->import->baseUrl);
        $this->process();
    }

    private function process()
    {
        $locations = array();
        foreach ($this->menus as $menu) {
            $this->processMenu($menu);
            foreach ($menu->locations as $location) {
                $locations[$location] = $menu->id;
            }
        }
        set_theme_mod('nav_menu_locations', $locations);
    }

    private function processMenu(&$menu)
    {
        $objMenu = wp_get_nav_menu_object($menu->slug);
        if (!$objMenu) {
            $id = wp_create_nav_menu($menu->slug);
            $objMenu = wp_get_nav_menu_object($menu->slug);
        }
        $menuId = $objMenu->term_id;
        $menu->id = $menuId;

        wp_set_current_user(1);
        $loggedInmenuItems = wp_get_nav_menu_items($menu->slug);
        wp_set_current_user(0);
        $notloggedInmenuItems = wp_get_nav_menu_items($menu->slug);
        $existingMenuItems = array_merge($loggedInmenuItems, $notloggedInmenuItems);
        $existingMenuItems = $this->bootstrap->uniqueObjectArray($existingMenuItems, 'ID');
        foreach ($existingMenuItems as $existingMenuItem) {
            $ret = wp_delete_post($existingMenuItem->ID, true);
        }

        foreach ($menu->items as &$objMenuItem) {
            $menuItemType = $objMenuItem->menu->post_meta['_menu_item_type'][0];
            switch ($menuItemType) {
                case 'post_type':
                    $newTarget = $this->import->posts->findTargetPostId($objMenuItem->menu->post_meta['_menu_item_object_id'][0]);
                    break;
                case 'taxonomy':
                    $newTarget = $this->import->taxonomies->findTargetTermId($objMenuItem->menu->post_meta['_menu_item_object_id'][0]);
                    break;
            }

            $parentItem = $this->findMenuItem($objMenuItem->menu->post_meta['_menu_item_menu_item_parent'][0]);

            $args = array(
                    'menu-item-title'       =>  $objMenuItem->menu->post_title,
                    'menu-item-position'    =>  $objMenuItem->menu->menu_order,
                    'menu-item-description' =>  $objMenuItem->menu->post_content,
                    'menu-item-attr-title'  =>  $objMenuItem->menu->post_title,
                    'menu-item-status'      =>  $objMenuItem->menu->post_status,
                    'menu-item-type'        =>  $menuItemType,
                    'menu-item-object'      =>  $objMenuItem->menu->post_meta['_menu_item_object'][0],
                    'menu-item-object-id'   =>  $newTarget,
                    'menu-item-url'         =>  $objMenuItem->menu->post_meta['_menu_item_url'][0],
                    'menu-item-parent-id'   =>  $parentItem,
            );
            $ret = wp_update_nav_menu_item($menuId, 0, $args);
            $objMenuItem->id = $ret;

            foreach ($objMenuItem->menu->post_meta as $key => $meta) {
                if (in_array($key, $this->skipped_meta_fields) || substr($key, 0, 1) == '_') {
                    continue;
                }
                $val = $meta[0];
                update_post_meta($ret, $key, $val);
            }
        }
    }

    private function findMenuItem($target)
    {
        foreach ($this->menus as $menu) {
            foreach ($menu->items as $item) {
                if ($item->menu->ID == $target) {
                    return $item->id;
                }
            }
        }

        return 0;
    }

    private function getFiles($folder)
    {
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file != '..' && $file != '.') {
                $ret[] = $file;
            }
        }

        return $ret;
    }
}
