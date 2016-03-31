<?php

namespace Wpbootstrap;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use League\CLImate\CLImate;

/**
 * Class Container
 * @package Wpbootstrap
 *
 * @method Bootstrap        getBootstrap
 * @method Utils            getUtils
 * @method Resolver         getResolver
 * @method Helpers          getHelpers
 * @method Initbootstrap    getInitbootstrap
 * @method Export           getExport
 * @method ExportMedia      getExportMedia
 * @method ExportMenus      getExportMenus
 * @method ExportPosts      getExportPosts
 * @method ExportSidebars   getExportSidebars
 * @method ExportTaxonomies getExportTaxonomies
 * @method ExtractMedia     getExtractMedia
 * @method Snapshots        getSnapshots
 * @method Settings         getLocalSettings
 * @method Settings         getAppSettings
 * @method Import           getImport*
 * @method ImportMenus      getImportMenus
 * @method ImportPosts      getImportPosts
 * @method ImportTaxonomies getImportTaxonomies
 * @method ImportSidebars   getImportSidebars
 *
 */
class Container
{
    public static $self = false;
    private $singeltons = array(
        'Bootstrap',
        'Log',
        'Utils',
        'Resolver',
        'Helpers',
        'Initbootstrap',
        'Import',
        'Export',
        'ExportMedia',
        'ExportMenus',
        'ExportPosts',
        'ExportSidebars',
        'ExportTaxonomies',
        'ExtractMedia',
        'Snapshots',
        'ImportMenus',
        'ImportPosts',
        'ImportTaxonomies',
        'ImportSidebars',
    );

    /**
     * Keep handles to all singletons
     *
     * @var array
     */
    private $singletonInstances = array();

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * @var \League\CLImate\CLImate
     */
    private $climate;

    /**
     * @var \Wpbootstrap\Settings
     */
    private $appSettings;

    /**
     * @var \Wpbootstrap\Settings
     */
    private $localSettings;

    /**
     * @var \Wpbootstrap\Extensions
     */
    private $extensions;

    /**
     * Container constructor.
     */
    public function __construct()
    {
        $this->localSettings = new Settings('local');
        $this->appSettings = new Settings('app');
        $this->climate = new CLImate();
        $this->extensions = new Extensions();
    }

    /**
     * Magic method to
     *
     * @param $name
     * @param $args
     * @return \Wpbootstrap\Settings
     */
    public function __call($name, $args)
    {
        $class = substr($name, 3);

        if ($class == 'AppSettings') {
            return $this->appSettings;
        }

        if ($class == 'LocalSettings') {
            return $this->localSettings;
        }

        if (in_array($class, $this->singeltons)) {
            if (!isset($this->singletonInstances[$class])) {
                $fullClass = 'Wpbootstrap\\'.$class;
                $this->singletonInstances[$class] = new $fullClass();
            }

            return $this->singletonInstances[$class];
        }

        die("Class $class not found\n");
    }

    /**
     * Get the global instance
     *
     * @return \Wpbootstrap\Container
     */
    public static function getInstance()
    {
        if (!self::$self) {
            self::$self = new self();
        }

        return self::$self;
    }

    /**
     * Destroy the global instance
     */
    public static function destroy()
    {
        self::$self = false;
    }

    /**
     * Get an initialized CLimage object
     *
     * @return \League\CLImate\CLImate
     */
    public function getCLImate()
    {
        return $this->climate;
    }

    /**
     * Get an initialized Monolog\Logger object
     * @return \Monolog\Logger
     */
    public function getLog()
    {
        if (!$this->log) {
            // Set up logging

            $this->log = new Logger('wp-bootstrap');
            $consoleloglevel = 1000;
            if (isset($this->localSettings->logfile)) {
                $level = Logger::WARNING;
                if (isset($this->localSettings->loglevel)) {
                    $level = constant('Monolog\Logger::'.$this->localSettings->loglevel);
                }
                $this->log->pushHandler(new StreamHandler($this->localSettings->logfile, $level));
            }
            if (isset($this->localSettings->consoleloglevel)) {
                $consoleloglevel = constant('Monolog\Logger::'.$this->localSettings->consoleloglevel);
            }
            $this->log->pushHandler(new StreamHandler('php://stdout', $consoleloglevel));
        }

        return $this->log;
    }

    /**
     * Validate localsettings and appsettings
     *
     * @param bool|true $die Should the PHP process die on invalid settings?
     * @return bool
     */
    public function validateSettings($die = true)
    {
        $good = true;
        if (!$this->localSettings->isValid()) {
            echo "localsettings.json does not exist or contains invalid JSON\n";
            $good = false;
        }
        if (!$this->appSettings->isValid()) {
            echo "appsettings.json does not exist or contains invalid JSON\n";
            $good = false;
        }
        if (!$good) {
            echo "\nAt least one configuration file is missing or contains invalid JSON\n";
            echo "Consider running command wp-init to set up template setting files\n";

            if ($die) {
                die();
            }
        }

        return $good;
    }

    public function getExtensions()
    {
        return $this->extensions;
    }
}
