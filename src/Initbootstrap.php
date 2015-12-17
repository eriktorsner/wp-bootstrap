<?php

namespace Wpbootstrap;

class Initbootstrap
{
    private $helpers;
    private static $self = false;

    public function getInstance()
    {
        if (!self::$self) {
            self::$self = new self();
        }

        return self::$self;
    }

    public function __construct()
    {
        $this->helpers = new Helpers();
    }

    private $scriptMaps = array(
        'wp-bootstrap' => 'vendor/bin/wpbootstrap wp-bootstrap',
        'wp-install' => 'vendor/bin/wpbootstrap wp-install',
        'wp-setup' => 'vendor/bin/wpbootstrap wp-setup',
        'wp-update' => 'vendor/bin/wpbootstrap wp-update',
        'wp-export' => 'vendor/bin/wpbootstrap wp-export',
        'wp-import' => 'vendor/bin/wpbootstrap wp-import',
        'wp-pullsettings' => 'vendor/bin/wpbootstrap wp-updateAppSettings',
        'wp-init' => 'vendor/bin/wpbootstrap wp-init',
        'wp-init-composer' => 'vendor/bin/wpbootstrap wp-initComposer',
    );

    public function init()
    {
        if (!file_exists(BASEPATH.'/appsettings.json')) {
            $appSettings = new \stdClass();
            $appSettings->title = '[title]';
            file_put_contents(BASEPATH.'/appsettings.json', $this->helpers->prettyPrint(json_encode($appSettings)));
        }
        if (!file_exists(BASEPATH.'/localsettings.json')) {
            $localSettings = new \stdClass();
            $localSettings->environment = '[environment]';
            $localSettings->url = '[url]';
            $localSettings->dbhost = '[dbhost]';
            $localSettings->dbname = '[dbname]';
            $localSettings->dbuser = '[dbuser]';
            $localSettings->dbpass = '[dbpass]';
            $localSettings->wpuser = '[wpuser]';
            $localSettings->wppass = '[wppass]';
            $localSettings->wppath = '[wppath]';
            file_put_contents(BASEPATH.'/localsettings.json', $this->helpers->prettyPrint(json_encode($localSettings)));
        }
    }

    public function initComposer()
    {
        if (!file_exists(BASEPATH.'/composer.json')) {
            die("Error: composer.json not found in current folder\n");
        }
        $composer = json_decode(file_get_contents(BASEPATH.'/composer.json'));
        if (!$composer) {
            die("Error: composer.json does not contain vaild JSON\n");
        }

        if (!isset($composer->scripts)) {
            $composer->scripts = new \stdClass();
        }

        foreach ($this->scriptMaps as $key => $script) {
            $composer->scripts->$key = $script;
        }

        file_put_contents(BASEPATH.'/composer.json', $this->helpers->prettyPrint(json_encode($composer)));
    }
}
