<?php

namespace Wpbootstrap\Providers;

use Wpbootstrap\Helpers;
use Wpbootstrap\Commands;
use Pimple\ServiceProviderInterface;
use Pimple\Container;
use Symfony\Component\Yaml\Yaml;
use Dotenv\Dotenv;

class ApplicationParametersProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        $runner = $pimple['cli']->get_runner();

        $pimple['path'] = $runner->config['path'];
        $pimple['args'] = $runner->arguments;
        $pimple['assocArgs'] = $runner->assoc_args;
        $pimple['ymlPath'] = $runner->project_config_path;

        $this->readEnvironment($pimple);
        $this->readDotEnv($pimple);
        $this->loadAppsettings($pimple);
    }

    /**
     * @param Container $pimple
     */
    private function readEnvironment(Container $pimple)
    {
        $pimple['environment'] = '[notset]';
        if (file_exists($pimple['ymlPath'])) {
            $yaml = new Yaml();
            $config = $yaml->parse(file_get_contents($pimple['ymlPath']));
            if (isset($config['environment'])) {
                $pimple['environment'] = $config['environment'];
            }
        }
    }

    /**
     * @param Container $pimple
     */
    private function readDotEnv(Container $pimple)
    {
        if (file_exists(BASEPATH . "/.env")) {
            $dotEnv = new Dotenv(BASEPATH);
            $dotEnv->load();
        }

        $file = '.env-' . $pimple['environment'];
        if (file_exists(BASEPATH . "/$file")) {
            $dotEnv = new Dotenv(BASEPATH, $file);
            $dotEnv->overload();
        }
    }

    /**
     * @param Container $pimple
     */
    private function loadAppsettings(Container $pimple)
    {
        if (file_exists(BASEPATH . '/appsettings.yml')) {
            $yaml = new Yaml();
            $settings = $yaml->parse(file_get_contents(BASEPATH . '/appsettings.yml'));
            $pimple['settings'] = $settings;
            return;
        }

        if (file_exists(BASEPATH . '/appsettings.json')) {
            $settings = json_decode(file_get_contents(BASEPATH . '/appsettings.json'));
            $settings = (array)$settings;
            $pimple['settings'] = $settings;
            return;
        }

        $pimple['settings'] = array();
    }
}