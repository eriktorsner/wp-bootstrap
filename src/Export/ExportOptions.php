<?php

namespace Wpbootstrap\Export;

use \Wpbootstrap\Bootstrap;

/**
 * Class ExportOptions
 * @package Wpbootstrap\Export
 */
class ExportOptions
{
    /**
     * Export content via WP-CFM
     */
    public function export()
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        if (function_exists('WPCFM')) {
            // running inside WordPress, use WPCFM directly
            $this->ensureBundleExists();
            WPCFM()->readwrite->push_bundle('wpbootstrap');
        } else {
            $cli->warning('Plugin WP-CFM does not seem to be installed. No options exported.');
            $cli->warning('Add the WP-CFM plugin directly to this install using:');
            $cli->warning('$ wp plugin install wp-cfm --activate');
            $cli->warning('');
            $cli->warning('...or to your appsettings using:');
            $cli->warning('$ wp bootstrap add plugin wp-cfm');
            $cli->warning('$ wp bootstrap setup');
            return;
        }

        $src = $app['path'] . '/wp-content/config/wpbootstrap.json';
        $trg = BASEPATH . '/bootstrap/config/wpbootstrap.json';
        if (file_exists($src)) {
            @mkdir(dirname($trg), 0777, true);
            copy($src, $trg);
            $cli->debug("Copied $src to $trg");

            // read settings
            $settings = json_decode(file_get_contents($trg));

            // sanity check
            $label = '.label';
            if (is_null($settings->$label)) {
                $settings->$label = 'wpbootstrap';
            }

            // look for media refrences in the included settings
            $extractMedia = $app['extractmedia'];
            $exportMedia = $app['exportmedia'];

            foreach ($settings as $name => $value) {
                $ret = $extractMedia->getReferencedMedia($value);
                if (count($ret) > 0) {
                    $exportMedia->addMedia($ret);
                }
            }

            // neutralize
            $helpers = $app['helpers'];
            $baseUrl = get_option('siteurl');
            $helpers->fieldSearchReplace($settings, $baseUrl, Bootstrap::NEUTRALURL);

            // save
            file_put_contents($trg, $helpers->prettyPrint(json_encode($settings)));
        }
    }

    /**
     * Checks if a wpbootstrap bundle exists in WP-CFM settings and creates one if needed
     */
    private function ensureBundleExists()
    {
        $wpcfm = json_decode(get_option('wpcfm_settings', '{}'));
        if (!isset($wpcfm->bundles)) {
            $wpcfm->bundles = array();
        }
        $found = false;
        foreach ($wpcfm->bundles as $bundle) {
            if ($bundle->name == 'wpbootstrap') {
                $found = true;
            }
        }
        if (!$found) {
            $bundle = new \stdClass();
            $bundle->name = 'wpbootstrap';
            $bundle->label = 'wpbootstrap';
            $bundle->config = null;
            $wpcfm->bundles[] = $bundle;
            update_option('wpcfm_settings', json_encode($wpcfm));
        }
    }
}