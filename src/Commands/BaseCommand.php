<?php

namespace Wpbootstrap\Commands;

use Dotenv\Dotenv;

class BaseCommand
{
    public function __construct()
    {
    }

    protected function requireEnv($parameters)
    {
        $app = \Wpbootstrap\Bootstrap::getApplication();
        $cli = $app['cli'];
        $dotEnv = new Dotenv(BASEPATH);

        try {
            foreach ($parameters as $parameter) {
                $dotEnv->required($parameter);
            }
        } catch (\Exception $e) {
            $cli->warning($e->getMessage());
            return false;
        }

        return true;

    }
}