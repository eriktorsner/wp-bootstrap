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

    const NEUTRALURL = '@@__**--**NEUTRAL**--**__@@';
    const VERSION = '0.5.0';

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

        $this->writeDefinesInWPConfig();
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

        $this->writeDefinesInWPConfig();
    }

    /**
     * Import serialized settings and content from folder bootstrap/
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function import($args, $assocArgs)
    {
        $app = self::getApplication();
        $obj = $app['import'];
        $obj->run($args, $assocArgs);
    }

    /**
     * Export serialized settings and content to folder ./bootstrap
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function export($args, $assocArgs)
    {
        $app = self::getApplication();
        $obj = $app['export'];
        $obj->run($args, $assocArgs);
    }

    private function writeDefinesInWPConfig()
    {
        $app = self::getApplication();
        $helpers = $app['helpers'];
        $helpers->ensureDefineInFile(
            $app['path'] . '/wp-config.php',
            'WPBOOT_BASEPATH',
            WPBOOT_BASEPATH
        );
    }
}
