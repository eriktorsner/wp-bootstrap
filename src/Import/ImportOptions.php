<?php

namespace Wpbootstrap\Import;

use \Wpbootstrap\Bootstrap;

/**
 * Class ExportMedia
 * @package Wpbootstrap\Import
 */
class ImportOptions
{
    /**
     * Import settings via WP-CFM
     */
    public function import()
    {
        $app = Bootstrap::getApplication();
        $helpers = $app['helpers'];
        $baseUrl = get_option('siteurl');

        $src = WPBOOT_BASEPATH.'/bootstrap/config/wpbootstrap.json';
        $trg = $app['path'] .'/wp-content/config/wpbootstrap.json';

        if (file_exists($src)) {
            @mkdir(dirname($trg), 0777, true);
            copy($src, $trg);

            // deneutralize
            $settings = json_decode(file_get_contents($trg));
            $helpers->fieldSearchReplace($settings, Bootstrap::NEUTRALURL, $baseUrl);
            file_put_contents($trg, $helpers->prettyPrint(json_encode($settings)));

            if (function_exists('WPCFM')) {
                WPCFM()->readwrite->pull_bundle('wpbootstrap');
            } else {
                $cli = $app['cli'];
                $cli->warning('Plugin WP-CFM does not seem to be installed. No options imported.');
                $cli->warning('Add the WP-CFM plugin directly to this install using:');
                $cli->warning('$ wp plugin install wp-cfm --activate');
                $cli->warning('');
                $cli->warning('...or to your appsettings using:');
                $cli->warning('$ wp bootstrap add plugin wp-cfm');
                $cli->warning('$ wp bootstrap setup');
                return;
            }

            // Flush options to make sure no other code overwrites based
            // on old settings stored in cache
            wp_cache_flush();
        }
    }
}