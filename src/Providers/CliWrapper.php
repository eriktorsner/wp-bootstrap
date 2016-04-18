<?php

namespace Wpbootstrap\Providers;

class CliWrapper
{
    /**
     * @return \WP_CLI\Runner
     */
    public function get_runner()
    {
        return \WP_CLI::get_runner();
    }

    /**
     * @param string $msg
     */
    public function log($msg)
    {
        \WP_CLI::log($msg);
    }

    public function line($message = '')
    {
        \WP_CLI::line($message);
    }

    /**
     * @param string $message
     */
    public function debug($message)
    {
        \WP_CLI::debug($message);
    }

    /**
     * @param string $message
     */
    public function warning($message)
    {
        \WP_CLI::warning($message);
    }

    /**
     * @param string    $message
     * @param bool|true $exit
     */
    public function error($message, $exit = true)
    {
        \WP_CLI::error($message, $exit);
    }

    /**
     * @param string $question
     * @param array  $assoc_args
     */
    public function confirm($question, $assoc_args = array())
    {
        \WP_CLI::confirm($question, $assoc_args);
    }

    /**
     * @param string  $message
     */
    public function success($message)
    {
        \WP_CLI::success($message);
    }

    /**
     * @param       $args
     * @param array $assoc_args
     */
    public function run_command($args, $assoc_args = array())
    {
        return \WP_CLI::run_command($args, $assoc_args);
    }

    /**
     * @param string     $command
     * @param bool|true  $exit_on_error
     * @param bool|false $return_detailed
     *
     * @return int|\ProcessRun
     */
    public function launch($command, $exit_on_error = true, $return_detailed = false)
    {
        return \WP_CLI::launch($command, $exit_on_error, $return_detailed);
    }

    /**
     * @param string     $command
     * @param array      $args
     * @param array      $assoc_args
     * @param bool|true  $exit_on_error
     * @param bool|false $return_detailed
     * @param array      $runtime_args
     *
     * @return int|\ProcessRun
     */
    public function launch_self(
        $command,
        $args = array(),
        $assoc_args = array(),
        $exit_on_error = true,
        $return_detailed = false,
        $runtime_args = array()
    ) {
        return \WP_CLI::launch_self(
            $command,
            $args,
            $assoc_args,
            $exit_on_error,
            $return_detailed,
            $runtime_args
        );
    }
}