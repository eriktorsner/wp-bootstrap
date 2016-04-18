<?php

namespace Wpbootstrap;

/**
 * Class ExportSidebars
 * @package Wpbootstrap
 */
class ExportSidebars extends ExportBase
{
    /**
     * ExportSidebars constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $container = Container::getInstance();
        $this->extractMedia = $container->getExtractMedia();
        $this->exportMedia = $container->getExportMedia();
    }

    /**
     * Export sidebars
     */
    public function export()
    {
        if (!isset($this->appSettings->content->sidebars)) {
            return;
        }

        $storedSidebars = get_option('sidebars_widgets', array());
        foreach ($this->appSettings->content->sidebars as $sidebar) {
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

                $ret = $this->extractMedia->getReferencedMedia($widgetSettings);
                $this->exportMedia->addMedia($ret);

                $file = $dir.'/'.$widget;
                $this->helpers->fieldSearchReplace($widgetSettings, $this->baseUrl, Bootstrap::NEUTRALURL);
                file_put_contents($file, serialize($widgetSettings));
            }

            $file = $dir.'/meta';
            file_put_contents($file, serialize($obj));
        }
    }
}
