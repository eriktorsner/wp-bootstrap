<?php

namespace Wpbootstrap;

/**
 * Class Initbootstrap
 * @package Wpbootstrap
 */
class Initbootstrap
{
    /**
     * @var Helpers
     */
    private $helpers;

    /**
     * Initbootstrap constructor.
     */
    public function __construct()
    {
        $container = Container::getInstance();
        $this->helpers = $container->getHelpers();
    }

    /**
     * Array with composer commands to add and map to wpbootstrap command
     *
     * @var array
     */
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
        'wp-snapshots' => 'vendor/bin/wpbootstrap wp-snapshots',
    );

    /**
     * Create skeleton appsettings and localsettings if they don't already exists
     * If a localsettings file exists and contains a non-default value for wppath,
     * a wp-cli.yml file is generated
     */
    public function init()
    {
        if (!file_exists(BASEPATH.'/appsettings.json')) {
            $appSettings = new \stdClass();
            $appSettings->title = '[title]';
            $appSettings->plugins = new \stdClass();
            $appSettings->plugins->standard = array('wp-cfm');
            $appSettings->themes = new \stdClass();
            $appSettings->themes->standard = array('twentysixteen');
            $appSettings->themes->active = 'twentysixteen';
            file_put_contents(BASEPATH.'/appsettings.json', $this->helpers->prettyPrint(json_encode($appSettings)));
        } else {
            $this->output("Note: appsettings.json already exists");
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
        } else {
            $this->output("Note: localsettings.json already exists");
        }

        $this->initWpCli();
    }

    /**
     * If a composer.json file exists, add or update script mappings in it
     */
    public function initComposer()
    {
        if (!file_exists(BASEPATH.'/composer.json')) {
            die("Error: composer.json not found in current folder\n");
        }
        $composer = json_decode(file_get_contents(BASEPATH.'/composer.json'));
        if (!$composer) {
            die("Error: composer.json does not contain valid JSON\n");
        }

        if (!isset($composer->scripts)) {
            $composer->scripts = new \stdClass();
        }

        foreach ($this->scriptMaps as $key => $script) {
            $composer->scripts->$key = $script;
        }

        file_put_contents(BASEPATH.'/composer.json', $this->helpers->prettyPrint(json_encode($composer)));
    }

    /**
     * @param bool|false $warn
     */
    public function initWpCli($warn = false)
    {
        if (!file_exists(BASEPATH.'/wp-cli.yml')) {
            $wpcli = "";
            $wpCliBinary = dirname(__DIR__) . '/bin/wpcli.php';

            $wpcli .= "require:\n";
            $wpcli .= "  - $wpCliBinary\n";

            $ls = json_decode(file_get_contents(BASEPATH.'/localsettings.json'));
            if (isset($ls->wppath)) {
                if ($ls->wppath != '[wppath]') {
                    $wpcli .= "path: {$ls->wppath}\n";
                }
            }
            file_put_contents(BASEPATH.'/wp-cli.yml', $wpcli);
        } elseif ($warn) {
            die("Warning: wp-cli.yml already exists. Remove it first if you want to regenerate it\n");
        } else {
            $this->output("Note: wp-cli.yml already exists.");
        }
    }

    private function output($msg)
    {
        if (defined('WPBOOT_LAUNCHER') && WPBOOT_LAUNCHER == 'wpcli') {
            \WP_CLI::line($msg);
        } else {
            echo "$msg\n";
        }
    }
}
