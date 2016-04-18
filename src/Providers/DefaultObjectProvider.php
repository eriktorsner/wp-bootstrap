<?php

namespace Wpbootstrap\Providers;

use Wpbootstrap\Commands;
use Wpbootstrap\Helpers;
use Pimple\ServiceProviderInterface;
use Pimple\Container;

class DefaultObjectProvider implements ServiceProviderInterface
{

    public function register(Container $pimple)
    {
        // Set up objects
        $pimple['install'] = function ($p) {
            return new Commands\Install();
        };

        $pimple['setup'] = function ($p) {
            return new Commands\Setup();
        };

        $pimple['reset'] = function ($p) {
            return new Commands\Reset();
        };

        $pimple['helpers'] = function ($p) {
            return new \Wpbootstrap\Helpers();
        };

        $pimple['cli'] = function ($p) {
            return new CliWrapper();
        };
    }
}