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

        $pimple['cliutils'] = function ($p) {
            return new CliUtilsWrapper();
        };

        $pimple['helpers'] = function ($p) {
            return new \Wpbootstrap\Helpers();
        };

        $pimple['extensions'] = function ($p) {
            return new \Wpbootstrap\Extensions();
        };

        $pimple['resolver'] = function ($p) {
            return new \Wpbootstrap\Resolver();
        };

        $pimple['import'] = function ($p) {
            return new Commands\Import();
        };

        $pimple['export'] = function ($p) {
            return new Commands\Export();
        };

        $pimple['exportoptions'] = function ($p) {
            return new \Wpbootstrap\Export\ExportOptions();
        };

        $pimple['extractmedia'] = function ($p) {
            return new \Wpbootstrap\Export\ExtractMedia();
        };

        $pimple['exportmenus'] = function ($p) {
            return new \Wpbootstrap\Export\ExportMenus();
        };

        $pimple['exportsidebars'] = function ($p) {
            return new \Wpbootstrap\Export\ExportSidebars();
        };

        $pimple['exportposts'] = function ($p) {
            return new \Wpbootstrap\Export\ExportPosts();
        };

        $pimple['exporttaxonomies'] = function ($p) {
            return new \Wpbootstrap\Export\ExportTaxonomies();
        };

        $pimple['exportmedia'] = function ($p) {
            return new \Wpbootstrap\Export\ExportMedia();
        };

    }
}