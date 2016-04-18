<?php

if (!defined('BASEPATH')) {
    define('BASEPATH', getcwd());
}

if (defined('WP_CLI') && class_exists('WP_CLI', false)) {
    WP_CLI::add_command('bootstrap', Wpbootstrap\Bootstrap::class);
    WP_CLI::add_command('setenv', '\Wpbootstrap\Commands\SetEnv');
}
