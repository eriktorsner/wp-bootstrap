<?php
namespace Wpbootstrap\Commands;

use Wpbootstrap\Bootstrap;

/**
 * Class Install
 * @package Wpbootstrap
 */
class Setup extends BaseCommand
{
    /**
     * @var \Pimple\Pimple
     */
    private $app;

    /**
     * @var \Wpbootstrap\Providers\CliWrapper;
     */
    private $cli;

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

    public function run($args, $assocArgs)
    {
        $this->app = Bootstrap::getApplication();
        $this->cli = $this->app['cli'];
        $this->cli->log('Running Bootstrap::setup');

        $this->checkAlreadyInstalled();

        $this->parseInstallables('plugin', 'standard');
        $this->parseInstallables('plugin', 'local');
        $this->parseInstallables('plugin', 'localcopy');
        $this->parseInstallables('theme', 'standard');
        $this->parseInstallables('theme', 'local');
        $this->parseInstallables('theme', 'localcopy');
        $this->resolveInstallOrder();
        $this->installAll();

        $this->applySettings();
        $this->wpContentSymlinks();
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
        $settings = $this->app['settings'];
        $base = $type . 's';

        if (isset($settings[$base][$path]) && is_array($settings[$base][$path])) {
            foreach ($settings[$base][$path] as $installable) {
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
        $helpers = $this->app['helpers'];

        $obj = new \stdClass();
        $obj->type = $type;
        $obj->path = $path;
        $obj->version = null;
        $obj->requires = new \stdClass();
        $obj->requires->plugins = [];
        $obj->requires->themes = [];

        if ($helpers->isUrl($definition)) {
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

    /**
     * Make sure all installable objects are treated in order
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
                    if (!isset($unordered[$id]) && !in_array($id, $this->installedPlugins)) {
                        $this->cli->error(
                            "Unresolvable plugin dependency. $id is not installed or defined in settings"
                        );
                    }
                    if (!isset($ordered[$id])) {
                        $ok = false;
                    }
                }
                foreach ($obj->requires->themes as $theme) {
                    $id = 'theme:' . $theme;
                    if (!isset($unordered[$id]) && !in_array($id, $this->installedThemes)) {
                        $this->cli->error(
                            "Unresolvable plugin dependency. $id is not installed or defined in settings"
                        );
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
            $this->cli->error("Unresolvable dependencies, do you have a circular reference?");
        }

        $this->installables = $ordered;
        return true;
    }

    private function installAll()
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

        if (isset($this->app['settings']['themes']['active'])) {
            $theme = $this->app['settings']['themes']['active'];
            $this->cli->run_command(array('theme', 'activate', $theme));
        }
    }


    /**
     * @param \stdClass $installable
     */
    private function installStandard($installable)
    {
        if ($installable->type == 'plugin' && in_array($installable->slug, $this->installedPlugins)) {
            $this->cli->debug("Skipping plugin {$installable->slug}. Already installed");
            return;
        }
        if ($installable->type == 'theme' && in_array($installable->slug, $this->installedThemes)) {
            $this->cli->debug("Skipping theme {$installable->slug}. Already installed");
            return;
        }

        $cmd = $installable->type . ' install';
        $args = array($installable->slug);
        $assocArgs = array();
        if ($installable->type == 'plugin') {
            $assocArgs['activate'] = 1;
        }
        if ($installable->version) {
            $assocArgs['version'] = $installable->version;
        }
        $this->cli->launch_self($cmd, $args, $assocArgs);
    }

    /**
     *
     * @param \stdClass $installable
     * @param bool      $symlink
     */
    private function installLocal($installable, $symlink = true)
    {
        $path = 'plugins';
        if ($installable->type == 'theme') {
            $path = 'themes';
        }

        $cmd = sprintf('rm -f %s/wp-content/%s/%s', $this->app['path'], $path, $installable->slug);
        $this->cli->launch($cmd);

        $cmdTemplate = 'ln -s %s/wp-content/%s/%s %s/wp-content/%s/%s';
        if (!$symlink) {
            $cmdTemplate = 'cp -a %s/wp-content/%s/%s %s/wp-content/%s/%s';
        }

        $cmd = sprintf(
            $cmdTemplate,
            BASEPATH,
            $path,
            $installable->slug,
            $this->app['path'],
            $path,
            $installable->slug
        );
        $this->cli->launch($cmd);

        if ($installable->type == 'plugin') {
            $args = array($installable->slug);
            $cmd = 'plugin activate';
            $this->cli->launch_self($cmd, $args, array());
        }
    }

    /**
     * Apply settings defined in appsettings
     */
    private function applySettings()
    {
        $settings = $this->app['settings'];
        if (isset($settings['settings'])) {
            foreach ($settings['settings'] as $key => $value) {
                $this->cli->run_command(array(
                    'option',
                    'update',
                    $key,
                    $value,
                ));
            }
        }
    }

    private function wpContentSymlinks()
    {
        $settings = $this->app['settings'];
        if (isset($settings['symlinks'])) {
            foreach ($settings['symlinks'] as $symlink) {
                if (!file_exists(BASEPATH . '/wp-content/' . $symlink)) {
                    continue;
                }
                $cmd = sprintf(
                    'rm -f %s/wp-content/%s',
                    $this->app['path'],
                    $symlink
                );
                $this->cli->run_command($cmd);

                $cmd = sprintf(
                    'ln -s %s/wp-content/%s %s/wp-content/%s',
                    BASEPATH,
                    $symlink,
                    $this->app['path'],
                    $symlink
                );
                $this->cli->run_command($cmd);
            }
        }
    }

    /**
     * Check the WordPress installation for existing themes and plugins
     */
    private function checkAlreadyInstalled()
    {
        $this->installedPlugins = [];
        $items = $this->getJsonList('plugin list', array(), array('format' => 'json'));
        array_walk($items, function ($item) {
            $this->installedPlugins[] = $item->name;
        });

        $this->installedThemes= [];
        $items = $this->getJsonList('theme list', array(), array('format' => 'json'));
        array_walk($items, function ($item) {
            $this->installedThemes[] = $item->name;
        });
    }

    private function getJsonList($cmd, $args = array(), $assocArgs = array())
    {
        $ret = $this->cli->launch_self($cmd, $args, $assocArgs, false, true);

        if ($ret->return_code == 0) {
            return json_decode($ret->stdout);
        }

        return array();
    }
}