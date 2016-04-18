<?php
namespace Wpbootstrap\Commands;

/**
 * Class Reset
 * @package Wpbootstrap
 */
class Reset extends BaseCommand
{
    public function run($args, $assocArgs)
    {
        $app = \Wpbootstrap\WpCli::getApplication();
        $cli = $app['cli'];
        $cli->log('Running Bootstrap::reset');

        $resp = "y\n";
        if (!isset($assocArgs['yes'])) {
            $cli->line("*************************************************************************************");
            $cli->line("**");
            $cli->line("** WARNING!   WARNING!    WARNING!    WARNING!   WARNING!  WARNING!     WARNING!    **");
            $cli->line("**");
            $cli->line("*************************************************************************************");
            $cli->line("The WordPress installation located in {$app['path']} will be removed");
            $resp = $cli->confirm("Are you sure? Hit Y to go ahead, anything else to cancel");
        }
        if (strtolower($resp) == "y\n") {
            $reset = $app['reset'];
            $reset->run($args, $assocArgs);
        }

        // Reset the DB
        $cli->run_command(array('db', 'reset'), array('yes' => 1));

        // Remove all files
        $cmd = 'rm -rf '. $app['path'] .'/*';
        $cli->launch($cmd);
    }
}