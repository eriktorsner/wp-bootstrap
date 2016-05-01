<?php

namespace Wpbootstrap\Export;

use \Wpbootstrap\Bootstrap;
use Symfony\Component\Yaml\Dumper;

/**
 * Class ExportSidebars
 * @package Wpbootstrap\Export
 */
class ExportSidebars
{
    /**
     * Export sidebars
     */
    public function export()
    {
        $app = Bootstrap::getApplication();
        $settings = $app['settings'];
        if (!isset($settings['content']['sidebars'])) {
            return;
        }

        $extractMedia = $app['extractmedia'];
        $exportMedia = $app['exportmedia'];
        $helpers = $app['helpers'];
        $baseUrl = get_option('siteurl');
        $dumper = new Dumper();

        $storedSidebars = get_option('sidebars_widgets', array());
        foreach ($settings['content']['sidebars'] as $sidebar) {
            $dir = BASEPATH.'/bootstrap/sidebars/'.$sidebar;
            array_map('unlink', glob("$dir/*"));
            @mkdir($dir, 0777, true);

            $sidebarDef = array();
            if (isset($storedSidebars[$sidebar])) {
                $sidebarDef = $storedSidebars[$sidebar];
            }
            foreach ($sidebarDef as $key => $widgetRef) {
                $parts = explode('-', $widgetRef);
                $ord = end($parts);
                $name = substr($widgetRef, 0, -1 * strlen('-'.$ord));
                $widgetTypeSettings = get_option('widget_'.$name);
                $widgetSettings = $widgetTypeSettings[$ord];

                $ret = $extractMedia->getReferencedMedia($widgetSettings);
                $exportMedia->addMedia($ret);

                $file = $dir.'/'.$widgetRef;
                $helpers->fieldSearchReplace($widgetSettings, $baseUrl, Bootstrap::NEUTRALURL);
                file_put_contents($file, $dumper->dump($widgetSettings, 4));
            }

            $file = BASEPATH."/bootstrap/sidebars/{$sidebar}_manifest";
            file_put_contents($file, $dumper->dump($sidebarDef, 4));
        }
    }
}
