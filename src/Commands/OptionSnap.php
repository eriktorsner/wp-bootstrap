<?php

namespace Wpbootstrap\Commands;

use \Wpbootstrap\Bootstrap;

/**
 * Snap, list, show and diff snapshots of the WordPress options table. Part of WP Bootstrap
 */
class OptionSnap
{
    /**
     * Max strlen to be printed in a output table
     */
    const MAX_STRLEN = 40;

    /**
     * Options that we ignore all together
     *
     * @var array
     */
    private $excludedOptions = array(
        'cron', 'rewrite_rules', 'wp_user_roles', 'can_compress_scripts',
    );

    /**
     * @var string
     */
    private $baseFolder;

    public function __construct()
    {
        $this->baseFolder = BASEPATH . '/bootstrap/snapshots';
    }

    /**
     * Grab a snapshot of the current option table and store to disk
     *
     * ## OPTIONS
     *
     * <name>
     * : A name for the new snapshot
     *
     * [--comment=<comment>]
     * : A comment. I.e "before installing plugin Foobar"
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function snap($args, $assocArgs)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];
        list($name) = $args;
        $comment = isset($assocArgs['comment'])?$assocArgs['comment']:'';

        $file = "{$this->baseFolder}/$name.snapshot";
        if (file_exists($file)) {
            $cli->error("Snapshot $name already exists");
            return;
        }

        $snapshot = new \stdClass();
        $snapshot->name = $name;
        $snapshot->created = date('Y-m-d H:i:s');
        $snapshot->environment = $app['environment'];
        $snapshot->host = php_uname('n');
        $snapshot->options = $this->getOptionsSnapshot();
        $snapshot->comment = $comment;

        if (!file_exists($this->baseFolder)) {
            @mkdir($this->baseFolder, 0777, true);
        }
        file_put_contents($file, serialize($snapshot));
    }

    /**
     * List all existing snapshots
     *
     * @param $args
     * @param $assocArgs
     *
     * @subcommand list
     */
    public function listSnapshots($args, $assocArgs)
    {
        $app = Bootstrap::getApplication();
        $helpers = $app['helpers'];
        $cliutils = $app['cliutils'];

        $snapshots = $helpers->getFiles($this->baseFolder);
        $output = array();
        foreach ($snapshots as $snapshotFile) {
            $snapshot = unserialize(file_get_contents($this->baseFolder.'/'.$snapshotFile));
            $output[] = array(
                'name' => $snapshot->name,
                'created' => $snapshot->created,
                'environment' => $snapshot->environment,
                'host' => $snapshot->host,
                'comment' => $snapshot->comment,
            );
        }
        if (count($output) > 0) {
            $cliutils->format_items('table', $output, array_keys($output[0]));
        }
    }

    /**
     * Shows all modified options between the current WordPress install or between two snapshots
     *
     *
     * ## OPTIONS
     *
     * <name>...
     * : Name of the snapshot to compare current WordPress options against,
     *   If a second <name> is passed in, the diff diff will be between <name> and <name2>
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function diff($args, $assocArgs)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];
        $cliutils = $app['cliutils'];

        $oldState = false;
        $newState = false;
        if (count($args) == 1) {
            $oldState = $this->readSnapshot($args[0]);
            if (!$oldState) {
                $cli->error("There's no snapshot file for {$args[0]}. Aborting");
                return;
            }
            $newState = new \stdClass();
            $newState->name = '[current state]';
            $newState->created = 'just now';
            $newState->options = $this->getOptionsSnapshot();
        }
        if (count($args) > 1) {
            $oldState = $this->readSnapshot($args[0]);
            if (!$oldState) {
                $cli->error("There's no snapshot file for {$args[0]}. Aborting");
                return;
            }
            $newState = $this->readSnapshot($args[1]);
            if (!$newState) {
                $cli->error("There's no snapshot file for {$args[1]}. Aborting");
                return;
            }
        }

        $diff = $this->internalDiff($oldState, $newState);
        $cli->line("Comparing snapshot {$oldState->name}, created {$oldState->created} with ");
        $cli->line("snapshot {$newState->name}, created {$newState->created}");

        if (count($diff) > 0) {
            $cliutils->format_items('table', $diff, array_keys($diff[0]));
        } else {
            $cli->line('No new, removed or changed options.');
        }
    }


    /**
     * Show all options and values contained in a snapshot, or an individual option
     *
     * ## OPTIONS
     *
     * <name>...
     * : Name of the snapshot to show
     * If a second <name> is passed in, shows the option named <name2>
     * Objects and arrays will be shown json encoded
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function show($args, $assocArgs)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];
        $cliutils = $app['cliutils'];
        $helpers = $app['helpers'];

        $oldState = $this->readSnapshot($args[0]);
        if (!$oldState) {
            $cli->error("There's no snapshot file for {$args[0]}. Aborting");
            return;
        }

        $wpCfmSettings = $helpers->getWPCFMSettings();

        if (count($args) == 1) {
            $options = array();
            foreach ($oldState->options as $name => $value) {
                if (in_array($name, $this->excludedOptions)) {
                    continue;
                }
                $options[] = array(
                    'name' => $name,
                    'value' => $this->valueToString($value),
                    'managed' => isset($wpCfmSettings->$name) ? 'Yes' : 'No',
                );
            }
            if (count($options) > 0) {
                $cliutils->format_items('table', $options, array_keys($options[0]));
            }
        } else {
            $name = $args[1];
            $value = $oldState->options[$name];
            if (is_object($value) || is_array($value)) {
                $cli->line($helpers->prettyPrint(json_encode($value)));
            } else {
                $cli->line($value);
            }

        }
    }

    /**
     * Finds all new, modified and removed options between two snapshots
     *
     * @param \stdClass $oldState
     * @param \stdClass $newState
     * @return array
     */
    private function internalDiff($oldState, $newState)
    {
        $added = array();
        $modified = array();
        $removed = array();
        $oldName = $oldState->name;
        $newName = $newState->name;

        $app = Bootstrap::getApplication();
        $helpers = $app['helpers'];
        $wpCfmSettings = $helpers->getWPCFMSettings();

        foreach ($oldState->options as $name => $value) {
            if (in_array($name, $this->excludedOptions)) {
                continue;
            }
            if (isset($newState->options[$name])) {
                if (md5(serialize($value)) != md5(serialize($newState->options[$name]))) {
                    $modified[] = array(
                        'state' => 'MOD',
                        'name' => $name,
                        $oldName => $this->valueToString($value),
                        $newName => $this->valueToString($newState->options[$name]),
                        'managed' => isset($wpCfmSettings->$name) ? 'Yes' : 'No',
                    );
                }
            } else {
                $removed[] = array(
                    'state' => 'DEL',
                    'name' => $name,
                    $oldName => $this->valueToString($value),
                    $newName => null,
                    'managed' => isset($wpCfmSettings->$name) ? 'Yes' : 'No',
                );
            }
        }
        foreach ($newState->options as $name => $value) {
            if (in_array($name, $this->excludedOptions)) {
                continue;
            }
            if (!isset($oldState->options[$name])) {
                $added[] = array(
                    'state' => 'NEW',
                    'name' => $name,
                    $oldName => null,
                    $newName => $this->valueToString($value),
                    'managed' => isset($wpCfmSettings->$name) ? 'Yes' : 'No',
                );
            }
        }

        return array_merge($added, $modified, $removed);
    }

    /**
     * Formats any value in a terminal output friendly way
     *
     * @param mixed $value
     * @return mixed|string
     */
    private function valueToString($value)
    {
        if (gettype($value) == 'object' || is_array($value)) {
            $ret = print_r($value, true);
        } else {
            $ret = (string) $value;
        }
        if (strlen($ret) > self::MAX_STRLEN) {
            $ret = substr($ret, 0, self::MAX_STRLEN - 3).'...';
        }
        $ret = str_replace("\n", '', $ret);
        $ret = str_replace('    ', '', $ret);

        return $ret;
    }

    /**
     * Reads a snapshot from file
     *
     * @param string $name
     * @return mixed|null
     */
    private function readSnapshot($name)
    {
        $snapshotFile = $name.'.snapshot';
        if (!file_exists($this->baseFolder.'/'.$snapshotFile)) {
            return null;
        }

        return unserialize(file_get_contents($this->baseFolder.'/'.$snapshotFile));
    }

    /**
     * Get all current options from WordPress
     *
     * @return array
     */
    private function getOptionsSnapshot()
    {
        global $wpdb;
        wp_cache_delete('alloptions', 'options');
        $allOptions = $wpdb->get_col(
            "SELECT option_name from $wpdb->options
            WHERE option_name NOT like '\_%';"
        );
        $options = array();
        foreach ($allOptions as $optionName) {
            if (!in_array($optionName, $this->excludedOptions)) {
                $options[$optionName] = get_option($optionName);
            }
        }

        return $options;
    }
}
