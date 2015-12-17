<?php

namespace Wpbootstrap;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Bootstrap
{
    public $localSettings;
    public $appSettings;
    public $argv = array();

    private static $self = false;
    private $log = false;
    private $utils;

    const NETURALURL = '@@__NEUTRAL__@@';
    const VERSION = '0.2.8';

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

    public function __construct()
    {
        global $argv;

        if (!defined('BASEPATH')) {
            define('BASEPATH', getcwd());
        }

        $this->localSettings = new Settings('local');
        $this->appSettings = new Settings('app');
        $this->validateSettings();

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

        $this->argv = $argv;
        if (is_null($this->argv)) {
            $argv = array();
        } else {
            array_shift($this->argv);
            array_shift($this->argv);
        }

        $this->utils = new Utils($this);
        $this->helpers = new Helpers();

        $this->log->addDebug('Parsed argv', $this->argv);
        $this->log->addInfo('Bootstrap initiated. Basepath is '.BASEPATH);
    }

    public function bootstrap()
    {
        $this->log->addDebug('Running Bootstap::bootstrap');
        $this->install();
        $this->setup();
    }

    public function install()
    {
        $this->log->addDebug('Running Bootstap::install');
        $wpcmd = $this->utils->getWpCommand();

        $cmd = 'rm -rf ~/.wp-cli/cache/core/';
        exec($cmd);
        $this->log->addDebug('Executed: '.$cmd);

        $cmd = $wpcmd.'core download --force';
        if (isset($this->appSettings->version)) {
            $cmd .= ' --version='.$this->appSettings->version;
        }
        exec($cmd);

        $cmd = $wpcmd.sprintf(
            'core config --dbname=%s --dbuser=%s --dbpass=%s --quiet',
            $this->localSettings->dbname,
            $this->localSettings->dbuser,
            $this->localSettings->dbpass
        );
        exec($cmd);

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
        exec($cmd);
    }

    public function setup()
    {
        $this->log->addDebug('Running Bootstap::setup');

        $this->log->addDebug('Installing plugins');
        $this->installPlugins();
        $this->log->addDebug('Installing themes');
        $this->installThemes();
        $this->log->addDebug('Applying settings from appsettings.json');
        $this->applySettings();
    }

    public function reset()
    {
        $wpcmd = $this->utils->getWpCommand();
        $cmd = $wpcmd.'db reset --yes';
        exec($cmd);

        $cmd = 'rm -rf '.$this->localSettings->wppath.'/*';
        exec($cmd);
    }

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
            exec($cmd);
        }
    }

    private function installPlugins()
    {
        $wpcmd = $this->utils->getWpCommand();
        if (isset($this->appSettings->plugins->standard)) {
            $standard = $this->appSettings->plugins->standard;
            $this->log->addDebug('Plugins installed from repo or URL', $standard);
            foreach ($standard as $plugin) {
                $parts = explode(':', $plugin);
                if (count($parts) == 1 || $this->helpers->isUrl($plugin)) {
                    $cmd = $wpcmd.'plugin install --activate '.$plugin;
                } else {
                    $cmd = $wpcmd.'plugin install --activate --version='.$parts[1].' '.$parts[0];
                }
                exec($cmd);
            }
        }

        if (isset($this->appSettings->plugins->local)) {
            $local = $this->appSettings->plugins->local;
            $this->log->addDebug('Plugins symlinked to wp-content', $local);
            foreach ($local as $plugin) {
                $cmd = sprintf('rm -f %s/wp-content/plugins/%s', $this->localSettings->wppath, $plugin);
                exec($cmd);

                $cmd = sprintf(
                    'ln -s %s/wp-content/plugins/%s %s/wp-content/plugins/%s',
                    BASEPATH,
                    $plugin,
                    $this->localSettings->wppath,
                    $plugin
                );
                exec($cmd);

                $cmd = $wpcmd.'plugin activate '.$plugin;
                exec($cmd);
            }
        }

        if (isset($this->appSettings->plugins->localcopy)) {
            $local = $this->appSettings->plugins->localcopy;
            $this->log->addDebug('Plugins copied to wp-content', $local);
            foreach ($local as $plugin) {
                $cmd = sprintf('rm -f %s/wp-content/plugins/%s', $this->localSettings->wppath, $plugin);
                exec($cmd);

                $cmd = sprintf(
                    'cp -a %s/wp-content/plugins/%s %s/wp-content/plugins/%s',
                    BASEPATH,
                    $plugin,
                    $this->localSettings->wppath,
                    $plugin
                );
                exec($cmd);

                $cmd = $wpcmd.'plugin activate '.$plugin;
                exec($cmd);
            }
        }
    }

    private function installThemes()
    {
        $wpcmd = $this->utils->getWpCommand();
        if (isset($this->appSettings->themes->standard)) {
            $standard = $this->appSettings->themes->standard;
            $this->log->addDebug('Themes installed from repo or URL', $standard);
            foreach ($standard as $theme) {
                $parts = explode(':', $theme);
                if (count($parts) == 1 || $this->helpers->isUrl($theme)) {
                    $cmd = $wpcmd.'theme install '.$theme;
                } else {
                    $cmd = $wpcmd.'theme install --version='.$parts[1].' '.$parts[0];
                }
                exec($cmd);
            }
        }

        if (isset($this->appSettings->themes->local)) {
            $local = $this->appSettings->themes->local;
            $this->log->addDebug('Themes symlinked to wp-content', $local);
            foreach ($local as $theme) {
                $cmd = sprintf('rm -f %s/wp-content/themes/%s', $this->localSettings->wppath, $theme);
                exec($cmd);

                $cmd = sprintf(
                    'ln -s %s/wp-content/themes/%s %s/wp-content/themes/%s',
                    BASEPATH,
                    $theme,
                    $this->localSettings->wppath,
                    $theme
                );
                exec($cmd);
            }
        }

        if (isset($this->appSettings->themes->localcopy)) {
            $local = $this->appSettings->themes->localcopy;
            $this->log->addDebug('Themes copied to wp-content', $local);
            foreach ($local as $theme) {
                $cmd = sprintf('rm -f %s/wp-content/themes/%s', $this->localSettings->wppath, $theme);
                exec($cmd);

                $cmd = sprintf(
                    'cp -a %s/wp-content/themes/%s %s/wp-content/themes/%s',
                    BASEPATH,
                    $theme,
                    $this->localSettings->wppath,
                    $theme
                );
                exec($cmd);
            }
        }

        if (isset($this->appSettings->themes->active)) {
            $cmd = $wpcmd.'theme activate '.$this->appSettings->themes->active;
            exec($cmd);
        }
    }

    private function applySettings()
    {
        $wpcmd = $this->utils->getWpCommand();
        if (isset($this->appSettings->settings)) {
            foreach ($this->appSettings->settings as $key => $value) {
                $cmd = $wpcmd."option update $key ";
                $cmd .= '"'.$value.'"';
                exec($cmd);
            }
        }
    }

    private function validateSettings()
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
            die();
        }
    }

    public function getLog()
    {
        return $this->log;
    }

    public function getUtils()
    {
        return $this->utils;
    }

    public function getHelpers()
    {
        return $this->helpers;
    }
}
