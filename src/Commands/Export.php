<?php
namespace Wpbootstrap\Commands;

use Wpbootstrap\Bootstrap;
use Symfony\Component\Yaml\Dumper;

/**
 * Class Export
 * @package Wpbootstrap
 */
class Export extends BaseCommand
{

    /**
     * @var \Pimple\Pimple
     */
    private $app;

    /**
     * @var \Wpbootstrap\Providers\CliWrapper;
     */
    private $cli;

    public function run($args, $assocArgs)
    {
        $app = \Wpbootstrap\Bootstrap::getApplication();
        $cli = $app['cli'];

        $extensions = $app['extensions'];
        $extensions->init();

        do_action('wp-bootstrap_before_export');

        $cli->log('Exporting options');
        $exportOptions = $app['exportoptions'];
        $exportOptions->export();

        $cli->log('Exporting content');
        $this->clearExportFolders();

        $exportMenus = $app['exportmenus'];
        $exportMenus->export();

        $exportSidebars = $app['exportsidebars'];
        $exportSidebars->export();

        $exportPosts = $app['exportposts'];
        $exportPosts->export();

        $exportTaxonomies = $app['exporttaxonomies'];
        $exportTaxonomies->export();

        $exportMedia = $app['exportmedia'];
        $exportMedia->export();

        do_action('wp-bootstrap_after_export');

        $this->createManifest();

    }

    private function clearExportFolders()
    {
        $app = \Wpbootstrap\Bootstrap::getApplication();
        $helpers = $app['helpers'];
        $cli = $app['cli'];
        $base = BASEPATH.'/bootstrap';

        $cli->debug("Cleaning export folders under $base");
        $helpers->recursiveRemoveDirectory($base.'/menus');
        $helpers->recursiveRemoveDirectory($base.'/posts');
        $helpers->recursiveRemoveDirectory($base.'/media');
        $helpers->recursiveRemoveDirectory($base.'/taxonomies');
        $helpers->recursiveRemoveDirectory($base.'/sidebars');
    }

    /**
     * Create and saves manifest file with details about the export
     */
    private function createManifest()
    {
        $app = Bootstrap::getApplication();
        $settings = $app['settings'];
        $helpers = $app['helpers'];

        $manifest = new \stdClass();
        $dumper = new Dumper();
        $manifest->created = date('Y-m-d H:i:s');
        $manifest->boostrapVersion = Bootstrap::VERSION;
        $manifest->appSettings = $dumper->dump($settings, 2);

        $manifestFile = BASEPATH.'/bootstrap/manifest.json';
        if (file_exists(dirname($manifestFile))) {
            file_put_contents($manifestFile, $helpers->prettyPrint(json_encode($manifest)));
        }
    }
}