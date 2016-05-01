<?php

namespace Wpbootstrap\Commands;

use Wpbootstrap\Bootstrap;
use Symfony\Component\Yaml\Dumper;

class ItemsManagerCommand
{
    protected $preservedFields = array();

    protected $args;

    protected $assocArgs;

    /**
     * @param array $path
     */
    protected function updateSettings($path)
    {
        $app = Bootstrap::getApplication();
        $settings = $app['settings'];
        $this->addToSettings($path, $settings);
        $app['settings'] = $settings;
    }

    /**
     * @param string $path
     */
    protected function addToSettings($path, &$settings)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        $head = array_shift($path);
        if (count($path) == 0) {
            if ($head == '*') {
                if (is_array($settings)) {
                    $cli->warning('Overwrote array with wildcard expression');
                }
                $settings = '*';
            } else {
                if (is_array($settings)) {
                    if (!in_array($head, $settings)) {
                        $settings[] = $head;
                    } else {
                        $cli->warning("$head is already managed.");
                    }
                } else {
                    $settings = array($head);
                    $cli->warning('Overwrote wildcard expression with array');
                }
            }
        } else {
            if (!isset($settings[$head])) {
                $settings[$head] = array();
            }
            $this->addToSettings($path, $settings[$head]);
        }
    }

    /**
     * @param array $assocArgs
     * @param string $name
     * @param string $default
     * @param string $new
     */
    protected function preserveAndSetList(&$assocArgs, $name, $default, $new)
    {
        $this->preservedFields[$name] = $default;
        if (isset($assocArgs[$name])) {
            $this->preservedFields[$name] = $assocArgs[$name];
        }

        $assocArgs[$name] = $this->preservedFields[$name] . ",$new";

    }

    /**
     * @param array $assocArgs
     * @param string $name
     * @param string $default
     * @param string $new
     */
    protected function preserveAndSet(&$assocArgs, $name, $default, $new)
    {
        $this->preservedFields[$name] = $default;
        if (isset($assocArgs[$name])) {
            $this->preservedFields[$name] = $assocArgs[$name];
        }

        $assocArgs[$name] = $new;
    }

    protected function getJsonList($cmd, $args = array(), $assocArgs = array())
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        $ret = $cli->launch_self($cmd, $args, $assocArgs, false, true);

        if ($ret->return_code == 0) {
            return json_decode($ret->stdout);
        } else {
            if (strlen($ret->stderr) > 0) {
                $errMessage = str_replace('Error: ', '', $ret->stderr);
                $errMessage = str_replace("\n", '', $errMessage);
                $cli->error($errMessage);
                return false;
            }
        }

        return array();
    }

    /**
     * @param $name
     * @param $default
     *
     * @return mixed
     */
    protected function getAssocArg($name, $default)
    {
        $ret = $default;
        if (isset($this->assocArgs[$name])) {
            $ret = $this->assocArgs[$name];
        }

        return $ret;
    }

    /**
     * Write the current settings in the Pimple
     * container back to appsettings.yml
     */
    protected function writeAppsettings()
    {
        $app = Bootstrap::getApplication();
        $settings = $app['settings'];

        $file = BASEPATH . '/appsettings.yml';
        $dumper = new Dumper();
        file_put_contents($file, $dumper->dump($settings, 4));
    }

    protected function output($output)
    {
        $app = Bootstrap::getApplication();
        $cliutils = $app['cliutils'];
        $cli = $app['cli'];

        if (count($output) > 0) {
            $cliutils->format_items(
                $this->getAssocArg('format', 'table'),
                $output,
                array_keys($output[0])
            );
        }
    }

}