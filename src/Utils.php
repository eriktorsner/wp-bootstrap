<?php

namespace Wpbootstrap;

/**
 * Class Utils
 * @package Wpbootstrap
 *
 * Simple Utilities used from other classes. Assumes that localsettings.json is defined and valid.
 *
 */
class Utils
{
    /**
     * @var bool
     */
    private $wpIncluded = false;

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * @var Settings
     */
    private $localSettings;

    /**
     * Utils constructor.
     */
    public function __construct()
    {
        $container = Container::getInstance();
        $this->log = $container->getLog();
        $this->localSettings = $container->getLocalSettings();
    }

    /**
     * Executes and logs external commands
     *
     * @param $cmd
     */
    public function exec($cmd)
    {
        $this->log->addDebug("Executing: $cmd");
        $output = [];
        exec($cmd, $output);
        if (count($output) > 0) {
            foreach ($output as $row) {
                $this->log->addDebug("Output: $row");
            }
        }
    }

    /**
     * Returns the wp-cli command with correct path
     *
     * @return string
     */
    public function getWpCommand()
    {
        $wpcmd = 'wp --path='.$this->localSettings->wppath.' --allow-root ';

        return $wpcmd;
    }

    /**
     * Include WordPres wp-load.php
     */
    public function includeWordPress()
    {
        if (!$this->wpIncluded) {
            $before = ob_get_level();
            $old = set_error_handler('\\Wpbootstrap\\Utils::noError');
            require_once $this->localSettings->wppath.'/wp-load.php';
            set_error_handler($old);
            $this->wpIncluded = true;
            if (ob_get_level() > $before) {
                ob_end_clean();
            }
        }
    }

    /**
     * Temporary dummy error handler
     *
     * @param $errNo
     * @param $errStr
     * @param $errFile
     * @param $errLine
     */
    public static function noError($errNo, $errStr, $errFile, $errLine)
    {
    }

    public function getWPCFMSettings()
    {
        $src = BASEPATH.'/bootstrap/config/wpbootstrap.json';
        if (file_exists($src)) {
            $config = json_decode(file_get_contents($src));
            if ($config) {
                return $config;
            }
        }

        return [];
    }
}
