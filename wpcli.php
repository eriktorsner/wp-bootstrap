<?php

if (!defined('WPBOOT_BASEPATH')) {
    define('WPBOOT_BASEPATH', getcwd());
}

if (defined('WP_CLI') && class_exists('WP_CLI', false)) {
    WP_CLI::add_command('bootstrap', Wpbootstrap\Bootstrap::class);
    WP_CLI::add_command('bootstrap posts', Wpbootstrap\Commands\Posts::class);
    WP_CLI::add_command('bootstrap taxonomies', Wpbootstrap\Commands\Taxonomies::class);
    WP_CLI::add_command('bootstrap menus', Wpbootstrap\Commands\Menus::class);
    WP_CLI::add_command('setenv', '\Wpbootstrap\Commands\SetEnv');
    WP_CLI::add_command('optionsnap', Wpbootstrap\Commands\OptionSnap::class);
}
