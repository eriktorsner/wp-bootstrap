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
        $utils = $app['utils'];
        $baseUrl = get_option('siteurl');

        $src = BASEPATH.'/bootstrap/config/wpbootstrap.json';
        $trg = $app['path'] .'/wp-content/config/wpbootstrap.json';

        if (file_exists($src)) {
            @mkdir(dirname($trg), 0777, true);
            copy($src, $trg);

            // deneutralize
            $settings = json_decode(file_get_contents($trg));
            $helpers->fieldSearchReplace($settings, Bootstrap::NEUTRALURL, $baseUrl);
            file_put_contents($trg, $helpers->prettyPrint(json_encode($settings)));

            $cmd = $wpcmd.'config pull wpbootstrap';
            $utils->exec($cmd);

            // Flush options to make sure no other code overwrites based
            // on old settings stored in cache
            wp_cache_flush();
        }
    }
}