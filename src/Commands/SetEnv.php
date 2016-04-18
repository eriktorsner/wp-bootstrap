<?php

namespace Wpbootstrap\Commands;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;
use Dotenv\Dotenv;
use WP_CLI;

/**
 * Update wp-cli.yml with settings from .env files
 *
 * Note: This command is stand alone, it does not inherit the BaseCommand class
 */
class SetEnv
{
    /**
     * Update wp-cli.yml with settings from .env files
     *
     * ## OPTIONS
     *
     * <environment>
     * : The name of the environment to set. Typically matched by a .env-<environemnt> file in the project root
     *
     * @param $args
     * @param $assocArgs
     *
     * @when before_wp_load
     */
    public function __invoke($args, $assocArgs)
    {
        $environment = $args[0];

        if (file_exists(BASEPATH . "/.env")) {
            $dotEnv = new Dotenv(BASEPATH);
            $dotEnv->load();
        }

        $file = '.env-' . $environment;
        if (file_exists(BASEPATH . "/$file")) {
            $dotEnv = new Dotenv(BASEPATH, $file);
            $dotEnv->overload();
        }

        try {
            $dotEnv = new Dotenv(__DIR__);
            $dotEnv->required('wppath');
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            return;
        }

        $runner = WP_CLI::get_runner();
        $ymlPath = $runner->project_config_path;

        $yaml = new Yaml();
        $config = $yaml->parse(file_get_contents($ymlPath));
        $config['path'] = $_ENV['wppath'];
        $config['environment'] = $environment;

        $dumper = new Dumper();
        file_put_contents($ymlPath, $dumper->dump($config, 2));
    }
}