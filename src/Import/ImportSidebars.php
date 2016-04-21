<?php

namespace Wpbootstrap\Import;

use Wpbootstrap\Bootstrap;

/**
 * Class ImportSidebars
 * @package Wpbootstrap\Import
 */
class ImportSidebars
{
    /**
     * @var array
     */
    private $sidebars = array();

     /**
     * ImportSidebars constructor.
     */
    public function __construct()
    {
    }

    public function import()
    {
        $app = Bootstrap::getApplication();
        $helpers = $app['helpers'];
        $baseUrl = get_option('siteurl');

        $dir = BASEPATH.'/bootstrap/sidebars';
        foreach ($helpers->getFiles($dir) as $sidebar) {
            $subdir = BASEPATH."/bootstrap/sidebars/$sidebar";
            $newSidebar = new \stdClass();
            $newSidebar->slug = $sidebar;
            $newSidebar->items = array();
            $newSidebar->meta = unserialize(file_get_contents($subdir.'/meta'));

            foreach ($newSidebar->meta as $key => $widgetRef) {
                $widget = new \stdClass();
                $parts = explode('-', $widgetRef);
                $ord = end($parts);
                $type = substr($widgetRef, 0, -1 * strlen('-'.$ord));

                $widget->type = $type;
                $widget->ord = $ord;
                $widget->meta = unserialize(file_get_contents($subdir.'/'.$widgetRef));
                $newSidebar->items[] = $widget;
            }
            $this->sidebars[] = $newSidebar;
        }

        $helpers->fieldSearchReplace($this->sidebars, Bootstrap::NEUTRALURL, $baseUrl);
        $this->process();
    }

    /**
     * The main import process
     */
    private function process()
    {
        $currentSidebars = get_option('sidebars_widgets', array());
        foreach ($this->sidebars as $sidebar) {
            $current = array();
            $new = array();
            if (isset($currentSidebars[$sidebar->slug])) {
                $current = $currentSidebars[$sidebar->slug];
            }
            foreach ($current as $key => $widgetRef) {
                $this->unsetWidget($widgetRef);
            }

            foreach ($sidebar->items as $widget) {
                $currentWidgetDef = get_option('widget_'.$widget->type, array());
                $ord = $this->findFirstFree($currentWidgetDef);

                $this->helpers->fieldSearchReplace($widget->meta, Bootstrap::NEUTRALURL, $this->import->baseUrl);
                $currentWidgetDef[$ord] = $widget->meta;
                update_option('widget_'.$widget->type, $currentWidgetDef);

                $newKey = $widget->type.'-'.$ord;
                $new[] = $newKey;
            }

            $currentSidebars[$sidebar->slug] = $new;
            update_option('sidebars_widgets', $currentSidebars);
        }
    }

    /**
     * Unset the current widget settings in the options table
     *
     * @param string $widgetRef
     */
    private function unsetWidget($widgetRef)
    {
        $parts = explode('-', $widgetRef);
        $ord = end($parts);
        $type = substr($widgetRef, 0, -1 * strlen('-'.$ord));

        $currentWidgetDef = get_option('widget_'.$type);
        unset($currentWidgetDef[$ord]);
        update_option('widget_'.$type, $currentWidgetDef);
    }

    /**
     * Finds a free slot in a Widget option struct.
     *
     * @param array $arr
     * @return int
     */
    private function findFirstFree($arr)
    {
        ksort($arr);
        $expected = 0;
        foreach ($arr as $key => $value) {
            if ($key == '_multiwidget') {
                continue;
            }

            ++$expected;
            if ($key == $expected) {
                continue;
            }
            if ($key > $expected) {
                return $expected;
            }
        }
        ++$expected;

        return $expected;
    }
}
