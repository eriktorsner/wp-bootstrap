<?php

namespace Wpbootstrap;

/**
 * Tools to manage a WordPress installation via config files
 */
class Bootstrap
{
    /**
     * @var \Pimple\Container
     *
     */
    private static $application;

    /**
     * @return \Pimple\Container
     */
    public static function getApplication()
    {
        if (!self::$application) {
            self::$application = new \Pimple\Container();
            self::$application->register(new Providers\DefaultObjectProvider());
            self::$application->register(new Providers\ApplicationParametersProvider());
        }

        return self::$application;
    }

    /**
     * @param \Pimple\Container $application
     */
    public static function setApplication($application)
    {
        self::$application = $application;
    }

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
        $app = self::getApplication();
        $installer = $app['install'];
        $installer->run($args, $assocArgs);
    }

    /**
     * Completely removes the WordPress installation defined in localsettings.json
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function reset($args, $assocArgs)
    {
        $app = self::getApplication();
        $reset = $app['reset'];
        $reset->run($args, $assocArgs);
    }

    /**
     * Install themes and plugins and apply options from appsettings.yml
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function setup($args, $assocArgs)
    {
        $app = self::getApplication();
        $obj = $app['setup'];
        $obj->run($args, $assocArgs);
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

}