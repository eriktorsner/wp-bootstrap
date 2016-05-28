<?php

namespace Wpbootstrap\Export;

use \Wpbootstrap\Bootstrap;
use Symfony\Component\Yaml\Dumper;

/**
 * Class ExportMenus
 * @package Wpbootstrap\Export
 */
class ExportMenus
{
    /**
     * @var array
     */
    private $navMenus;

    /**
     * Export Menus
     */
    public function export()
    {
        $app = Bootstrap::getApplication();
        $settings = $app['settings'];
        if (!isset($settings['content']['menus'])) {
            return;
        }

        $this->readMenus();
        $dumper = new Dumper();
        remove_all_filters('wp_get_nav_menu_items');

        $helpers = $app['helpers'];
        $exportTaxonomies = $app['exporttaxonomies'];
        $exportPosts = $app['exportposts'];
        $baseUrl = get_option('siteurl');

        foreach ($settings['content']['menus'] as $menu) {
            $dir = WPBOOT_BASEPATH.'/bootstrap/menus/'.$menu;
            array_map('unlink', glob("$dir/*"));
            @mkdir($dir, 0777, true);

            $menuMeta = array();
            $menuMeta['locations'] = array();
            if (isset($this->navMenus[$menu])) {
                $menuMeta['locations'] = $this->navMenus[$menu]->locations;
            }

            $file = WPBOOT_BASEPATH."/bootstrap/menus/{$menu}_manifest";
            file_put_contents($file, $dumper->dump($menuMeta, 4));

            $menuItems = wp_get_nav_menu_items($menu);

            foreach ($menuItems as $menuItem) {
                $obj = get_post($menuItem->ID, ARRAY_A);
                $obj['post_meta'] = get_post_meta($obj['ID']);

                switch ($obj['post_meta']['_menu_item_type'][0]) {
                    case 'post_type':
                        $postType = $obj['post_meta']['_menu_item_object'][0];
                        $postId = $obj['post_meta']['_menu_item_object_id'][0];
                        $objPost = get_post($postId);
                        $exportPosts->addPost($postType, $objPost->post_name);
                        break;
                    case 'taxonomy':
                        $id = $obj['post_meta']['_menu_item_object_id'][0];
                        $taxonomy = $obj['post_meta']['_menu_item_object'][0];
                        $objTerm = get_term($id, $taxonomy);
                        if (!is_wp_error($objTerm)) {
                            $exportTaxonomies->addTerm($taxonomy, $objTerm->slug);
                        }
                        break;
                }
                $helpers->fieldSearchReplace($obj, $baseUrl, Bootstrap::NEUTRALURL);

                $file = $dir.'/'.$menuItem->post_name;
                file_put_contents($file, $dumper->dump($obj, 4));
            }
        }
    }

    /**
     * Read all current nav menus into
     * a class member for later convenience
     */
    private function readMenus()
    {
        $navMenus = wp_get_nav_menus();
        $menuLocations = get_nav_menu_locations();
        $this->navMenus = array();
        foreach ($navMenus as $menu) {
            $menu->locations = array();
            foreach ($menuLocations as $location => $termId) {
                if ($termId == $menu->term_id) {
                    $menu->locations[] = $location;
                }
            }
            $this->navMenus[$menu->slug] = $menu;

        }
    }
}
