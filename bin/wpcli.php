<?php
global $argc, $argv;

define('WPBOOT_BASEPATH', getcwd());
define('WPBOOT_LAUNCHER', 'wpcli');

require_once WPBOOT_BASEPATH . '/vendor/autoload.php';

if (isset($argv[1]) && $argv[1] == 'bootstrap') {
    $runner = WP_CLI::get_runner();
    $config = $runner->config;
    $assocArgs = $runner->assoc_args;

    // check if we got environment from cmd line args before
    // reading the localsettings for the first time
    if (isset($assocArgs['env'])) {
        define('WPBOOT_ENVIRONMENT', $assocArgs['env']);
    }
    $localSettings = new \Wpbootstrap\Settings('local');

    if (rtrim($localSettings->wppath, '/') != rtrim($config['path'], '/') && $localSettings->wppath != '[wppath]') {
        wpbstrp_rewritePath($localSettings->wppath);
        $cmd = join(' ', $argv);
        $output = [];
        exec($cmd, $output);
        echo join("\n", $output) . "\n";
        WP_CLI::line("Path in wp-cli.yml is now set to {$localSettings->wppath}");
        die();
    }
}

WP_CLI::add_command('bootstrap', 'Wpbootstrap\WpCli');

function wpbstrp_rewritePath($newPath) {
    $lines = file(WPBOOT_BASEPATH . '/wp-cli.yml');
    $buffer = '';
    foreach ($lines as $line) {
        if (substr($line, 0, 5) != 'path:') {
            $buffer .= $line;
        }
    }
    $buffer .= "path: $newPath";
    file_put_contents(WPBOOT_BASEPATH . '/wp-cli.yml', $buffer);
}
