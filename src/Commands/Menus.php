<?php

namespace Wpbootstrap\Commands;

use Wpbootstrap\Bootstrap;

/**
 * Class Posts
 * @package Wpbootstrap\Command
 */
class Menus extends ItemsManagerCommand
{
    /**
     * List all menus that currectly exists in WordPress. Adds a column to indicate
     * if it's managed by WP Boostrap or not
     *
     * @subcommand list
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function listItems($args, $assocArgs)
    {
        $this->args = $args;
        $this->assocArgs = $assocArgs;

        $app = Bootstrap::getApplication();

        $menus = $this->getJsonList('menu list --format=json', $args, $assocArgs);
        $managedMenus = array();
        if (isset($app['settings']['content']['menus'])) {
            $managedMenus = $app['settings']['content']['menus'];
        }

        $output = array();
        foreach ($menus as $menu) {
            $fldManaged = 'No';
            if (isset($managedMenus[$menu->slug])) {
                $fldManaged = 'Yes';
            }

            $row = array();
            foreach ($menu as $fieldName => $fieldValue) {
                $row[$fieldName] = $fieldValue;
            }
            $row['Managed'] = $fldManaged;
            $output[] = $row;
        }

        $this->output($output);
    }

    /**
     * Add a menu to be managed by WP Bootstrap
     *
     * <menu_identifier>...
     * :One or more menus, identified by their (slug) or term id, to be added
     *
     * @param $args
     * @param $assocArgs
     */
    public function add($args, $assocArgs)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        $menus = $this->getJsonList('menu list --format=json', array(), $assocArgs);

        foreach ($args as $menuIdentifier) {
            $menu = $this->getMenuSlug($menuIdentifier, $menus);
            if ($menu) {
                if (count($menu->locations) > 0) {
                    foreach ($menu->locations as $location) {
                        $this->updateSettings(
                            array('content','menus', $menu->slug , $location)
                        );
                    }
                } else {
                    $this->updateSettings(array('content','menus', $menu->slug , array()));
                }
                $this->writeAppsettings();
            } else {
                $cli->warning("Menu $menuIdentifier not found\n");
            }
        }
    }

    /**
     * @param $menuIdentifier
     * @param $menus
     * @return bool
     */
    private function getMenuSlug($menuIdentifier, $menus)
    {
        foreach ($menus as $menu) {
            if (is_numeric($menuIdentifier)) {
                $hit = $menuIdentifier == $menu->term_id ? true : false;
            } else {
                $hit = $menuIdentifier == $menu->slug ? true : false;
            }

            if ($hit) {
                return $menu;
            }
        }

        return false;
    }
}