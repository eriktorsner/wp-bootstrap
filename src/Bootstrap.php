<?php

namespace Wpbootstrap;

/**
 * Class Bootstrap
 * @package Wpbootstrap
 *
 * Main entry point for installations and setup tasks
 */
class Bootstrap
{
    /**
     * @var Settings
     */
    public $localSettings;

    /**
     * @var Settings
     */
    public $appSettings;

    /**
     * Subset of global argv
     *
     * @var array
     */
    public $argv = array();

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * @var \Wpbootstrap\Utils
     */
    private $utils;

    const NEUTRALURL = '@@__**--**NEUTRAL**--**__@@';
    const VERSION = '0.3.2';

    /**
     * Bootstrap constructor.
     */
    public function __construct()
    {
        global $argv;

        $container = Container::getInstance();

        $this->argv = $argv;
        if (is_null($this->argv)) {
            $argv = array();
        } else {
            array_shift($this->argv);
            array_shift($this->argv);
        }

        $this->utils = $container->getUtils();
        $this->helpers = $container->getHelpers();
        $this->log = $container->getLog();
        $this->localSettings = $container->getLocalSettings();
        $this->appSettings = $container->getAppSettings();

        $this->log->addDebug('Parsed argv', $this->argv);
        $this->log->addInfo('Bootstrap initiated. Basepath is '.BASEPATH);
    }

    /**
     * Run install and setup in one command
     */
    public function bootstrap()
    {
        $this->log->addDebug('Running Bootstrap::bootstrap');
        $this->install();
        $this->setup();
    }

    public function setup()
    {
        $container = Container::getInstance();
        $setup = $container->getSetup();
        $setup->setup();
    }

    /**
     * Install WordPress as specified in localsettings.json and appsettings.json
     */
    public function install()
    {
        $this->log->addDebug('Running Bootstrap::install');
        $wpcmd = $this->utils->getWpCommand();

        if (file_exists($this->localSettings->dbhost . '/wp-config.php')) {
            $this->log->addInfo('WordPress already installed');
            return;
        }

        $cmd = $wpcmd.'core download --force';
        if (isset($this->appSettings->version)) {
            $cmd .= ' --version='.$this->appSettings->version;
        }
        $this->utils->exec($cmd);

        $cmd = $wpcmd.sprintf(
            'core config --dbhost=%s --dbname=%s --dbuser=%s --dbpass=%s --quiet',
            $this->localSettings->dbhost,
            $this->localSettings->dbname,
            $this->localSettings->dbuser,
            $this->localSettings->dbpass
        );
        $this->utils->exec($cmd);

        if (!isset($this->localSettings->wpemail)) {
            $this->localSettings->wpemail = 'admin@local.dev';
        }
        $cmd = $wpcmd.sprintf(
            'core install --url=%s --title="%s" --admin_name=%s --admin_email="%s" --admin_password="%s"',
            $this->localSettings->url,
            $this->appSettings->title,
            $this->localSettings->wpuser,
            $this->localSettings->wpemail,
            $this->localSettings->wppass
        );
        $this->utils->exec($cmd);

        // a brand new wordpress install has content, we don't want that
        $cmd = $wpcmd.sprintf(
            'db query "%s %s %s %s"',
            'delete from wp_posts;',
            'delete from wp_postmeta;',
            'delete from wp_comments;',
            'delete from wp_commentmeta;'
        );
        $this->utils->exec($cmd);

    }

    /**
     * Completely remove the current WordPress installation
     */
    public function reset()
    {
        $wpcmd = $this->utils->getWpCommand();
        $cmd = $wpcmd.'db reset --yes';
        exec($cmd);

        $cmd = 'rm -rf '.$this->localSettings->wppath.'/*';
        $this->utils->exec($cmd);
    }

    /**
     * Run update via wp-cli, arguments are passed via $argv
     *   no args      => update core, themes and plugins
     *   plugin       => update all plugins
     *   plugin NAME  => update named plugin
     *   theme        => update all themes
     *   theme NAME   => update named theme
     */
    public function update()
    {
        $this->log->addDebug('Running Bootstrap::update');
        $wpcmd = $this->utils->getWpCommand();
        $commands = array();

        if (count($this->argv) == 0) {
            $commands[] = $wpcmd.'plugin update --all';
            $commands[] = $wpcmd.'theme update --all';
            $commands[] = $wpcmd.'core update';
        } elseif ($this->argv[0] == 'plugins') {
            if (count($this->argv) == 1) {
                $commands[] = $wpcmd.'plugin update --all';
            } else {
                $commands[] = $wpcmd.'plugin update '.$this->argv[1];
            }
        } elseif ($this->argv[0] == 'themes') {
            if (count($this->argv) == 1) {
                $commands[] = $wpcmd.'theme update --all';
            } else {
                $commands[] = $wpcmd.'theme update '.$this->argv[1];
            }
        }

        foreach ($commands as $cmd) {
            $this->log->addDebug("Executing: $cmd");
            $this->utils->exec($cmd);
        }
    }






}
