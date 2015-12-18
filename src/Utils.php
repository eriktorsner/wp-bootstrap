<?php

namespace Wpbootstrap;

class Utils
{
    private static $self = false;
    private $bootstrap = false;
    private $wpIncluded = false;

    public function __construct($bootstrap)
    {
        $this->bootstrap = $bootstrap;
        $this->log = $this->bootstrap->getLog();
        self::$self = $this;
    }

    public function exec($cmd)
    {
        exec($cmd);
        $this->log->addDebug("Executing: $cmd");
    }

    public function getWpCommand()
    {
        $wpcmd = 'wp --path='.$this->bootstrap->localSettings->wppath.' --allow-root ';

        return $wpcmd;
    }

    public function includeWordPress()
    {
        if (!$this->wpIncluded) {
            $before = ob_get_level();
            $old = set_error_handler('\\Wpbootstrap\\Utils::noError');
            require_once $this->bootstrap->localSettings->wppath.'/wp-load.php';
            set_error_handler($old);
            $this->wpIncluded = true;
            if (ob_get_level() > $before) {
                ob_end_clean();
            }
        }
    }

    public static function noError($errno, $errstr, $errfile, $errline)
    {
    }
}
