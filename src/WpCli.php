<?php

namespace Wpbootstrap;

/**
 * Tools to manage a WordPress installation via config files
 */
class WpCli
{
    /**
     * @var \Wpbootstrap\Container
     */
    private $container;

    /**
     * Bootstrap a WordPress site based on appsettings.json and localsettings.json.
     * Equal to running commands install, setup and import
     *
     * @param $args
     * @param $assocArgs
     *
     * @when before_wp_load
     */
    public function bootstrap($args, $assocArgs)
    {
        $this->initiate($args, $assocArgs);
        $bootstrap = $this->container->getBootstrap();
        $this->container->validateSettings();
        $bootstrap->bootstrap();
    }

    /**
     * Install a WordPress site based on appsettings.json and localsettings.json.
     *
     * @param $args
     * @param $assocArgs
     *
     * @when before_wp_load
     */
    public function install($args, $assocArgs)
    {
        $this->initiate($args, $assocArgs);
        $bootstrap = $this->container->getBootstrap();
        $this->container->validateSettings();
        $bootstrap->install();
    }

    /**
     * Completely removes the WordPress installation defined in localsettings.json
     *
     * @param $args
     * @param $assocArgs
     *
     * @when before_wp_load
     */
    public function reset($args, $assocArgs)
    {
        $this->initiate($args, $assocArgs);
        $bootstrap = $this->container->getBootstrap();
        $this->container->validateSettings();
        $localSettings = $this->container->getLocalSettings();
        $resp = "y\n";
        if (!isset($assocArgs['force'])) {
            echo "*************************************************************************************\n";
            echo "**\n";
            echo "** WARNING!   WARNING!    WARNING!    WARNING!   WARNING!  WARNING!     WARNING!    **\n";
            echo "**\n";
            echo "*************************************************************************************\n";
            echo "The WordPress installation located in {$localSettings->wppath} will be removed\n";
            \WP_CLI::confirm("Are you sure? Hit Y to go ahead, anything else to cancel");
        }
        if (strtolower($resp) == "y\n") {
            $bootstrap->reset();
        }
    }

    /**
     * Install themes and plugins into the WordPress site based on appsettings.json
     *
     * @param $args
     * @param $assocArgs
     *
     * @when before_wp_load
     */
    public function setup($args, $assocArgs)
    {
        $this->initiate($args, $assocArgs);
        $bootstrap = $this->container->getBootstrap();
        $this->container->validateSettings();
        $bootstrap->setup();
    }

    /**
     * Import serialized settings and content from folder bootstrap/
     *
     * @param $args
     * @param $assocArgs
     *
     * @when before_wp_load
     */
    public function import($args, $assocArgs)
    {
        $this->initiate($args, $assocArgs);
        $import = $this->container->getImport();
        $import->import();
    }

    /**
     * Export serialized settings and content to folder bootstrap/
     *
     * @param $args
     * @param $assocArgs
     *
     * @when before_wp_load
     */
    public function export($args, $assocArgs)
    {
        $this->initiate($args, $assocArgs);
        $export = $this->container->getExport();
        $export->export();
    }

    /**
     * Initiate a new project with default localsettings.json,
     * appsettings.json and wp-cli.yml
     *
     * @param $args
     * @param $assocArgs
     *
     * @subcommand init-project
     * @when before_wp_load
     */
    public function initProject($args, $assocArgs)
    {
        $this->initiate($args, $assocArgs);
        $initBootstrap = $this->container->getInitbootstrap();
        $initBootstrap->init();
    }

    /**
     * Manage snapshots. WordPress options serialized to disk
     *
     * @param $args
     * @param $assocArgs
     *
     * @when before_wp_load
     */
    public function snapshots($args, $assocArgs)
    {
        $this->initiate($args, $assocArgs);
        $this->container->validateSettings();
        $snapshots = $this->container->getSnapshots();
        $snapshots->manage();
    }


    /**
     * Read and handle common command line args
     *
     * @param $args
     * @param $assocArgs
     */
    private function initiate($args, $assocArgs)
    {
        $this->container = \Wpbootstrap\Container::getInstance();
        $this->container->getExtensions()->init();

    }
}