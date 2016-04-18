<?php
namespace Wpbootstrap\Commands;

/**
 * Class Install
 * @package Wpbootstrap
 */
class Install extends BaseCommand
{
    /**
     *
     * @param $args
     * @param $assocArgs
     */
    public function run($args, $assocArgs)
    {
        $app = \Wpbootstrap\WpCli::getApplication();
        $cli = $app['cli'];
        $cli->log('Running Bootstrap::install');

        $this->installCore();
    }

    /**
     * Download, configure and install WordPress core
     */
    private function installCore()
    {
        $app = \Wpbootstrap\WpCli::getApplication();
        $cli = $app['cli'];

        $ret = $this->requireEnv(array('wppath', 'wpurl', 'wpuser', 'wppass', 'dbhost', 'dbname', 'dbuser', 'dbpass'));
        if (!$ret) {
            return;
        }

        if (file_exists($app['path'] . '/wp-config.php')) {
            $cli->error('The \'wp-config.php\' file already exists.');
        }

        // download core
        $assocArgs = array(
            'path' => $_ENV['wppath'],
        );
        $cli->run_command(array('core', 'download'), $assocArgs);

        // config core
        $assocArgs = array(
            'dbhost' => $_ENV['dbhost'],
            'dbname' => $_ENV['dbname'],
            'dbuser' => $_ENV['dbuser'],
            'dbpass' => $_ENV['dbpass'],
        );
        $cli->run_command(array('core', 'config'), $assocArgs);

        // install core
        $assocArgs = array(
            'url'            => $_ENV['wpurl'],
            'title'          => '[title]',
            'admin_user'     => $_ENV['wpuser'],
            'admin_password' => $_ENV['wppass'],
        );

        if (isset($app['settings']['title'])) {
            $assocArgs['title'] = $app['settings']['title'];
        }

        $assocArgs['skip-email'] = 1;
        $assocArgs['admin_email'] = 'admin@local.dev';
        if (isset($_ENV['wpemail'])) {
            $assocArgs['admin_email'] = $_ENV['wpemail'];
        }

        $ret = $cli->launch_self('core install', array(), $assocArgs, false, true);
        if ($ret->return_code !=0 && strlen($ret->stderr) > 0) {
            $cli->error(substr($ret->stderr, 7));
        }
        $this->deleteDefaultContent();

        $cli->success('Installation succeeded');
    }

    /**
     * Unless specified otherwise, remove default content from a fresh
     * WordPress installation
     */
    private function deleteDefaultContent()
    {
        $app = \Wpbootstrap\WpCli::getApplication();
        $cli = $app['cli'];

        $cli->log('Deleting default posts, comments, themes and plugins');
        $app = \Wpbootstrap\WpCli::getApplication();

        if (!isset($app['settings']['keepDefaultContent']) || $app['settings']['keepDefaultContent'] == false) {
            $cmd = sprintf(
                'db query "%s %s %s %s"',
                'delete from wp_posts;',
                'delete from wp_postmeta;',
                'delete from wp_comments;',
                'delete from wp_commentmeta;'
            );
            $ret = $cli->launch_self($cmd, array(), array(), false, true);

            $cmd = sprintf("rm -rf %s/wp-content/plugins/*", $app['path']);
            $ret = $cli->launch($cmd);

            $cmd = sprintf("rm -rf %s/wp-content/themes/*", $app['path']);
            $ret = $cli->launch($cmd);
        }
    }
}