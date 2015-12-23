<?php

namespace Wpbootstrap;

class Export extends ExportBase
{
    protected $exportTaxonomies;
    protected $exportMedia;
    protected $extractMedia;
    protected $exportPosts;
    protected $exportSidebars;

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

    public function export()
    {
        $this->log->addInfo('Exporting settings');
        $this->exportSettings();
        $this->log->addInfo('Exporting content');
        $this->exportContent();
        $this->createManifest();
    }

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

            // neutralize
            $this->helpers->fieldSearchReplace($settings, $this->baseUrl, Bootstrap::NETURALURL);

            // save
            file_put_contents($trg, $this->helpers->prettyPrint(json_encode($settings)));
        }
    }

    private function exportContent()
    {
        $base = BASEPATH.'/bootstrap';

        $this->log->addDebug("Cleaning folder $base");
        $this->helpers->recursiveRemoveDirectory($base.'/menus');
        $this->helpers->recursiveRemoveDirectory($base.'/posts');
        $this->helpers->recursiveRemoveDirectory($base.'/media');
        $this->helpers->recursiveRemoveDirectory($base.'/taxonomies');
        $this->helpers->recursiveRemoveDirectory($base.'/sidebars');

        $this->exportMenus->export();
        $this->exportSidebars->export();
        $this->exportPosts->export();
        $this->exportTaxonomies->export();
        $this->exportMedia->export();
    }

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

    private function createManifest()
    {
        $manifest = new \stdClass();
        $manifest->created = date('Y-m-d H:i:s');
        $manifest->boostrapVersion = Bootstrap::VERSION;
        $manifest->appSettings = json_decode($this->appSettings->toString());

        $manifestFile = BASEPATH.'/bootstrap/manifest.json';
        if (file_exists(dirname($manifestFile))) {
            file_put_contents($manifestFile, $this->helpers->prettyPrint(json_encode($manifest)));
        }
    }
}
