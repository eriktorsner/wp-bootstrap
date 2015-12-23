<?php

namespace Wpbootstrap;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
    );
    private $singletonInstances = array();
    private $log;
    private $appSettings;
    private $localSettings;

    public function __construct()
    {
        $this->localSettings = new Settings('local');
        $this->appSettings = new Settings('app');
    }

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

    public static function getInstance()
    {
        if (!self::$self) {
            self::$self = new self();
        }

        return self::$self;
    }

    public static function destroy()
    {
        self::$self = false;
    }

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

            return $good;
        }
    }
}
