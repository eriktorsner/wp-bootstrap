<?php

namespace Wpbootstrap\Export;

/**
 * Class Export
 * @package Wpbootstrap
 */
class Export extends ExportBase
{
    /**
     * Export constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $container = Container::getInstance();

        $this->exportTaxonomies = $container->getExportTaxonomies();
        $this->exportMedia = $container->getExportMedia();
        $this->exportMenus = $container->getExportMenus();
        $this->exportPosts = $container->getExportPosts();
        $this->exportSidebars = $container->getExportSidebars();
        $this->extractMedia = $container->getExtractMedia();
    }

    /**
     * The main entry point for exports. Exports config (via WP-CFM) and content using internal classes
     */
    public function export()
    {
        $container = Container::getInstance();
        $extensions = $container->getExtensions();
        $extensions->init();

        do_action('wp-bootstrap_before_export');
        $this->log->addInfo('Exporting settings');
        $this->exportSettings();
        $this->log->addInfo('Exporting content');
        $this->exportContent();
        do_action('wp-bootstrap_after_export');

        $this->createManifest();
    }

    /**
     * Export content via WP-CFM
     */
    private function exportSettings()
    {
        if (function_exists('WPCFM')) {
            // running inside WordPress, use WPCFM directly
            $this->log->addDebug('Using WPCFM directly');
            WPCFM()->readwrite->push_bundle('wpbootstrap');
        } else {
            $this->log->addDebug('Using WPCFM via wp-cli');
            $wpcmd = $this->utils->getWpCommand();
            $this->ensureBundleExists();

            $cmd = $wpcmd.'config push wpbootstrap 2>/dev/null';
            $this->utils->exec($cmd);
        }

        $src = $this->localSettings->wppath.'/wp-content/config/wpbootstrap.json';
        $trg = BASEPATH.'/bootstrap/config/wpbootstrap.json';
        if (file_exists($src)) {
            @mkdir(dirname($trg), 0777, true);
            copy($src, $trg);
            $this->log->addDebug("Copied $src to $trg");

            // read settings
            $settings = json_decode(file_get_contents($trg));

            // sanity check
            $label = '.label';
            if (is_null($settings->$label)) {
                $settings->$label = 'wpbootstrap';
            }

            // look for media refrences in the included settings
            foreach ($settings as $name => $value) {
                if ($name != 'theme_mods_eteritique') continue;
                $ret = $this->extractMedia->getReferencedMedia($value);
                if (count($ret) > 0) {
                    $this->exportMedia->addMedia($ret);
                }
            }

            // neutralize
            $this->helpers->fieldSearchReplace($settings, $this->baseUrl, Bootstrap::NEUTRALURL);

            // save
            file_put_contents($trg, $this->helpers->prettyPrint(json_encode($settings)));
        }
    }

    /**
     * Exports content
     * (menus, posts, media, taxonomies, widgets)
     */
    private function exportContent()
    {

    }




}
