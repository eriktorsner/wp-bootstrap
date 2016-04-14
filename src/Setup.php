<?php

namespace Wpbootstrap;

/**
 * Class Setup
 * @package Wpbootstrap
 *
 * Setup the WordPress installation with themes and plugins
 *
 */
class Setup
{
    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * @var Settings
     */
    public $localSettings;

    /**
     * @var Settings
     */
    public $appSettings;

    /**
     * @var Helpers
     */
    private $helpers;

    /**
     * @var \Wpbootstrap\Utils
     */
    private $utils;

    /**
     * Array of all installables
     * @var array
     */
    private $installables = [];

    /**
     * Array of already installed plugins
     *
     * @var array
     */
    private $installedPlugins;

    /**
     * Array of already installed themes
     *
     * @var array
     */
    private $installedThemes;

    public function __construct()
    {
        $container = Container::getInstance();

        $this->utils = $container->getUtils();
        $this->helpers = $container->getHelpers();
        $this->log = $container->getLog();
        $this->localSettings = $container->getLocalSettings();
        $this->appSettings = $container->getAppSettings();

        $this->parseInstallables('plugin', 'standard');
        $this->parseInstallables('plugin', 'local');
        $this->parseInstallables('plugin', 'localcopy');
        $this->parseInstallables('theme', 'standard');
        $this->parseInstallables('theme', 'local');
        $this->parseInstallables('theme', 'localcopy');

    }

    /**
     * Install plugins and themes
     */
    public function setup()
    {
        $this->checkAlreadyInstalled();

        $this->log->addDebug('Running Setup::setup');
        $this->log->addDebug('Creating symlinks in Wp-content');
        $this->wpContentSymlinks();

        $this->log->addDebug('Resolving dependencies');
        if (!$this->resolveInstallOrder()) {
            $this->log->addError('Dependencies between plugins and themes could not be resolved, Aborting.');
            die();
        }

        $this->log->addDebug('Installing themes and plugins');
        $this->installInstallable();
        $this->log->addDebug('Applying settings from appsettings.json');
        $this->applySettings();
    }

    /**
     *
     */
    private function resolveInstallOrder()
    {
        $unordered = $this->installables;
        $ordered = [];

        $added = 99;
        while (count($ordered) < count($unordered) && $added > 0) {
            $added = 0;
            foreach ($unordered as $key => $obj) {
                if (isset($ordered[$key])) {
                    continue;
                }

                $ok = true;
                foreach ($obj->requires->plugins as $plugin) {
                    $id = 'plugin:' . $plugin;
                    if (!isset($unordered[$id])) {
                        $this->log->addError("Unresolvable plugin dependency. $id is not defined in appsettings.json ");
                        return false;
                    }
                    if (!isset($ordered[$id])) {
                        $ok = false;
                    }
                }
                foreach ($obj->requires->themes as $theme) {
                    $id = 'theme:' . $theme;
                    if (!isset($unordered[$id])) {
                        $this->log->addError("Unresolvable theme dependency. $id is not defined in appsettings.json ");
                        return false;
                    }
                    if (!isset($ordered[$id])) {
                        $ok = false;
                    }
                }

                if ($ok) {
                    $ordered[$key] = $obj;
                    $added++;
                } elseif (!isset($unordered[$key])) {
                    return false;
                }
            }
        }

        if (count($ordered) < count($unordered)) {
            $this->log->addError("Unresolvable dependencies, do you have a circular reference?");
            return false;
        }

        $this->installables = $ordered;
        return true;
    }


    /**
     * Read a section of the appSettings and collect info
     * about all identified installables
     *
     * @param string $type
     * @param string $path
     */
    private function parseInstallables($type, $path)
    {
        $base = '';
        switch ($type) {
            case 'plugin':
                $base = 'plugins';
                break;
            case 'theme':
                $base = 'themes';
                break;
        }

        if (isset($this->appSettings->$base->$path) && is_array($this->appSettings->$base->$path)) {
            foreach ($this->appSettings->$base->$path as $installable) {
                if (is_string($installable)) {
                    $this->addInstallableString($type, $path, $installable);
                } else {
                    $this->addInstallableObject($type, $path, $installable);
                }
            }
        }
    }

    /**
     * Add an installable plugin or theme based on an object
     * definition
     *
     * @param string $type
     * @param string $path
     * @param \stdClass $definition
     */
    private function addInstallableObject($type, $path, $definition)
    {
        $definition->path = $path;
        $definition->type = $type;

        if (!isset($definition->version)) {
            $definition->version = null;
        }

        if (!isset($definition->requires)) {
            $definition->requires = new \stdClass();
        }

        if (!isset($definition->requires->plugins)) {
            $definition->requires->plugins = [];
        }

        if (!isset($definition->requires->themes)) {
            $definition->requires->themes = [];
        }

        $id = $type . ':' . $definition->slug;
        $this->installables[$id] = $definition;
    }

    /**
     * Add an installable plugin or theme based on a string
     * definition
     *
     * @param string  $type
     * @param string  $path
     * @param string  $definition

     */
    private function addInstallableString($type, $path, $definition)
    {
        $obj = new \stdClass();
        $obj->type = $type;
        $obj->path = $path;
        $obj->version = null;
        $obj->requires = new \stdClass();
        $obj->requires->plugins = [];
        $obj->requires->themes = [];

        if ($this->helpers->isUrl($definition)) {
            $obj->slug = $definition;
            $obj->url = $definition;
        } else {
            $parts = explode(':', $definition);
            if (count($parts) == 1) {
                $obj->slug = $definition;
            } else {
                $obj->slug = $parts[0];
                $obj->version = $parts[1];
            }
        }

        $id = $type . ':' . $obj->slug;
        $this->installables[$id] = $obj;
    }

    private function installInstallable()
    {
        foreach ($this->installables as $key => $installable) {
            switch ($installable->path) {
                case 'standard':
                    $this->installStandard($installable);
                    break;
                case 'local':
                    $this->installLocal($installable, true);
                    break;
                case 'localcopy':
                    $this->installLocal($installable, false);
                    break;
            }
        }

        if (isset($this->appSettings->themes->active)) {
            $wpcmd = $this->utils->getWpCommand();
            $cmd = $wpcmd.'theme activate '.$this->appSettings->themes->active;
            $this->utils->exec($cmd);
        }

    }


    /**
     * @param \stdClass $installable
     */
    private function installStandard($installable)
    {
        if ($installable->type == 'plugin' && in_array($installable->slug, $this->installedPlugins)) {
            $this->log->addDebug("Skipping plugin {$installable->slug}. Already installed");
            return;
        }
        if ($installable->type == 'theme' && in_array($installable->slug, $this->installedThemes)) {
            $this->log->addDebug("Skipping theme {$installable->slug}. Already installed");
            return;
        }

        $wpcmd = $this->utils->getWpCommand();
        $cmd = sprintf(
            '%s %s install %s %s',
            $wpcmd,
            $installable->type,
            $installable->slug,
            $installable->type == 'plugin'? '--activate': ''
        );
        if ($installable->version) {
            $cmd .= ' --version=' . $installable->version;
        }
        $this->utils->exec($cmd);
    }

    /**
     *
     * @param \stdClass $installable
     * @param bool      $symlink
     */
    private function installLocal($installable, $symlink = true)
    {
        $wpcmd = $this->utils->getWpCommand();
        $path = 'plugins';
        if ($installable->type == 'theme') {
            $path = 'themes';
        }

        $cmd = sprintf('rm -f %s/wp-content/%s/%s', $this->localSettings->wppath, $path, $installable->slug);
        $this->utils->exec($cmd);

        $cmdTemplate = 'ln -s %s/wp-content/%s/%s %s/wp-content/%s/%s';
        if (!$symlink) {
            $cmdTemplate = 'cp -a %s/wp-content/%s/%s %s/wp-content/%s/%s';
        }

        $cmd = sprintf(
            $cmdTemplate,
            BASEPATH,
            $path,
            $installable->slug,
            $this->localSettings->wppath,
            $path,
            $installable->slug
        );
        $this->utils->exec($cmd);

        if ($installable->type == 'plugin') {
            $cmd = sprintf(
                '%s %s activate %s',
                $wpcmd,
                $installable->type,
                $installable->slug
            );
            $this->utils->exec($cmd);
        }
    }

    /**
     * Apply settings defined in appsettings.json
     */
    private function applySettings()
    {
        $wpcmd = $this->utils->getWpCommand();
        if (isset($this->appSettings->settings)) {
            foreach ($this->appSettings->settings as $key => $value) {
                $cmd = $wpcmd."option update $key ";
                $cmd .= '"'.$value.'"';
                $this->utils->exec($cmd);
            }
        }
    }

    private function wpContentSymlinks()
    {
        if (isset($this->appSettings->symlinks)) {
            foreach ($this->appSettings->symlinks as $symlink) {
                if (!file_exists(BASEPATH . '/wp-content/' . $symlink)) {
                    continue;
                }
                $cmd = sprintf(
                    'rm -f %s/wp-content/%s',
                    $this->localSettings->wppath,
                    $symlink
                );
                $this->utils->exec($cmd);

                $cmd = sprintf(
                    'ln -s %s/wp-content/%s %s/wp-content/%s',
                    BASEPATH,
                    $symlink,
                    $this->localSettings->wppath,
                    $symlink
                );
                $this->utils->exec($cmd);
            }
        }
    }

    private function checkAlreadyInstalled()
    {
        $this->utils->includeWordPress();

        $this->installedPlugins = [];
        $plugins = get_plugins();
        foreach ($plugins as $path => $plugin) {
            $this->installedPlugins[] = $this->getPluginName($path);
        }

        $this->installedThemes = [];
        foreach (wp_get_themes() as $key => $theme) {
            $this->installedThemes[] = $key;
        }
    }

    /**
     * Converts a plugin basename back into a friendly slug.
     *
     * From wp-cli php/utils-wp.php
     */
    private function getPluginName($basename)
    {
        if (false === strpos($basename, '/')) {
            $name = basename($basename, '.php');
        } else {
            $name = dirname($basename);
        }
        return $name;
    }
}

