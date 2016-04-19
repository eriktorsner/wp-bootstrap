<?php

namespace Wpbootstrap\Export;

use \Wpbootstrap\Bootstrap;

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

        $storedSidebars = get_option('sidebars_widgets', array());
        foreach ($settings['content']['sidebars'] as $sidebar) {
            $dir = BASEPATH.'/bootstrap/sidebars/'.$sidebar;
            array_map('unlink', glob("$dir/*"));
            @mkdir($dir, 0777, true);

            $obj = new \stdClass();
            if (isset($storedSidebars[$sidebar])) {
                $obj = $storedSidebars[$sidebar];
            }
            foreach ($obj as $key => $widget) {
                $parts = explode('-', $widget);
                $ord = end($parts);
                $name = substr($widget, 0, -1 * strlen('-'.$ord));
                $widgetTypeSettings = get_option('widget_'.$name);
                $widgetSettings = $widgetTypeSettings[$ord];

                $ret = $extractMedia->getReferencedMedia($widgetSettings);
                $exportMedia->addMedia($ret);

                $file = $dir.'/'.$widget;
                $helpers->fieldSearchReplace($widgetSettings, $baseUrl, Bootstrap::NEUTRALURL);
                file_put_contents($file, serialize($widgetSettings));
            }

            $file = $dir.'/meta';
            file_put_contents($file, serialize($obj));
        }
    }
}
