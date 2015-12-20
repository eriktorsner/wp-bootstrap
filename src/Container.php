<?php

namespace Wpbootstrap;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Container
{
    public static $self = false;

    private $bootstrap = false;
    private $log = false;
    private $utils = false;
    private $resolver = false;
    private $helpers = false;
    private $export = false;
    private $exportMedia = false;
    private $import = false;
    private $initBootstrap = false;

    private $localSettings = false;
    private $appSettings = false;

    public static function getInstance()
    {
        if (!self::$self) {
            self::$self = new self();
        }

        return self::$self;
    }

    public function __construct()
    {
        $this->localSettings = new Settings('local');
        $this->appSettings = new Settings('app');
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

    public function getLocalSettings()
    {
        return $this->localSettings;
    }

    public function getAppSettings()
    {
        return $this->appSettings;
    }

    public function getBootstrap()
    {
        if (!$this->bootstrap) {
            $this->bootstrap = new Bootstrap();
        }

        return $this->bootstrap;
    }

    public function getUtils()
    {
        if (!$this->utils) {
            $this->utils = new Utils();
        }

        return $this->utils;
    }

    public function getHelpers()
    {
        if (!$this->helpers) {
            $this->helpers = new Helpers();
        }

        return $this->helpers;
    }

    public function getResolver()
    {
        if (!$this->resolver) {
            $this->resolver = new Resolver();
        }

        return $this->resolver;
    }

    public function getExport()
    {
        if (!$this->export) {
            $this->export = new Export();
        }

        return $this->export;
    }

    public function getImport()
    {
        if (!$this->import) {
            $this->import = new Import();
        }

        return $this->import;
    }

    public function getInitBootstrap()
    {
        if (!$this->initBootstrap) {
            $this->initBootstrap = new Initbootstrap();
        }

        return $this->initBootstrap;
    }

    public function getExportMedia()
    {
        if (!$this->exportMedia) {
            $this->exportMedia = new ExportMedia();
        }

        return $this->exportMedia;
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
