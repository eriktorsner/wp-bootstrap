<?php

namespace Wpbootstrap;

class Utils
{
    private $wpIncluded = false;
    private $log = false;
    private $localSettings = false;

    public function __construct()
    {
        $container = Container::getInstance();
        $this->log = $container->getLog();
        $this->localSettings = $container->getLocalSettings();
    }

    public function exec($cmd)
    {
        $this->log->addDebug("Executing: $cmd");
        exec($cmd);
    }

    public function getWpCommand()
    {
        $wpcmd = 'wp --path='.$this->localSettings->wppath.' --allow-root ';

        return $wpcmd;
    }

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

    public static function noError($errNo, $errStr, $errFile, $errLine)
    {
    }
}
