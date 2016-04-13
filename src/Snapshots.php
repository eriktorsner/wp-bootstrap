<?php

namespace Wpbootstrap;

/**
 * Class Snapshots
 * @package Wpbootstrap
 */
class Snapshots
{
    /**
     * @var Bootstrap
     */
    private $bootstrap;

    /**
     * @var
     */
    private $localSettings;

    /**
     * @var Helpers
     */
    private $helpers;

    /**
     * @var Utils
     */
    private $utils;

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * @var string
     */
    private $baseFolder;

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
     * Snapshots constructor.
     */
    public function __construct()
    {
        $container = Container::getInstance();
        $this->bootstrap = $container->getBootstrap();
        $this->log = $container->getLog();
        $this->helpers = $container->getHelpers();
        $this->utils = $container->getUtils();
        $this->localSettings = $container->getLocalSettings();
        $utils = $container->getUtils();
        $this->climate = $container->getCLImate();

        $this->baseFolder = BASEPATH.'/bootstrap/config/snapshots';
        $utils->includeWordpress();
    }

    /**
     * Main entry point, checks argv and calls sub commands
     */
    public function manage()
    {
        if (count($this->bootstrap->argv) == 0) {
            $this->climate->out('wp-snapshots expects at least one sub command');
        }

        switch ($this->bootstrap->argv[0]) {
            case 'snapshot':
                $this->takeSnapshot();
                break;
            case 'list':
                $this->listSnapshots();
                break;
            case 'diff':
                $this->diffSnapshots();
                break;
            case 'show':
                $this->showSnapshot();
                break;
        }
    }

    /**
     * Creates a snapshot
     *
     * Optional arguments passed via argv
     *   arg1  name The name for the new snapshot, default to current UNIX timestamp
     *   arg2  comment A comment
     *
     */
    private function takeSnapshot()
    {
        $snapshotName = ''.time();
        $snapshotComment = '';
        if (count($this->bootstrap->argv) > 1) {
            if ($snapshotName != 'now') {
                $snapshotName = $this->bootstrap->argv[1];
            }
        }
        if (count($this->bootstrap->argv) > 2) {
            $snapshotComment = $this->bootstrap->argv[2];
        }

        $file = $this->baseFolder.'/'.$snapshotName.'.snapshot';
        if (file_exists($file)) {
            $this->climate->out("Snapshot $snapshotName already exists");
        }

        $snapshot = new \stdClass();
        $snapshot->name = $snapshotName;
        $snapshot->created = date('Y-m-d H:i:s');
        $snapshot->environment = $this->localSettings->environment;
        $snapshot->host = php_uname('n');
        $snapshot->options = $this->getOptionsSnapshot();
        $snapshot->comment = $snapshotComment;

        if (!file_exists($this->baseFolder)) {
            @mkdir($this->baseFolder, 0777, true);
        }
        file_put_contents($file, serialize($snapshot));
    }

    /**
     * Lists all current snapshots
     */
    private function listSnapshots()
    {
        $snapshots = $this->helpers->getFiles($this->baseFolder);
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
            $this->climate->table($output);
        }
    }

    /**
     * Shows all modified options between the current WordPress install or between two
     * snapshots
     *
     * Required arguments passed via argv
     *   arg1 snapshot Name of the snapshot to compare current options against
     *
     * Optional arguments passed via argv
     *   arg2 snapshot2 If a second name is passed in, the diff will be between snapshot and snapshot2
     */
    private function diffSnapshots()
    {
        if (count($this->bootstrap->argv) < 2) {
            $this->climate->out('wp-state diff requires at least 1 additional argument Name the snapshot');
            $this->climate->out('name to compare current state with. Or name 2 existing snapshots to compare');
            $this->climate->out('to each other');

            return;
        }
        $oldState = false;
        $newState = false;
        if (count($this->bootstrap->argv) == 2) {
            $oldState = $this->readSnapshot($this->bootstrap->argv[1]);
            if (!$oldState) {
                $this->climate->out("There's no snapshot file for {$this->bootstrap->argv[1]}. Aborting");

                return;
            }
            $newState = new \stdClass();
            $newState->name = '[current state]';
            $newState->created = 'just now';
            $newState->options = $this->getOptionsSnapshot();
        }
        if (count($this->bootstrap->argv) > 2) {
            $oldState = $this->readSnapshot($this->bootstrap->argv[1]);
            if (!$oldState) {
                $this->climate->out("There's no snapshot file for {$this->bootstrap->argv[1]}. Aborting");

                return;
            }
            $newState = $this->readSnapshot($this->bootstrap->argv[2]);
            if (!$newState) {
                $this->climate->out("There's no snapshot file for {$this->bootstrap->argv[2]}. Aborting");

                return;
            }
        }

        $diff = $this->diff($oldState, $newState);
        $this->climate->out("Comparing snapshot {$oldState->name}, created {$oldState->created} with ");
        $this->climate->out("snapshot {$newState->name}, created {$newState->created}");

        if (count($diff) > 0) {
            $this->climate->table($diff);
        } else {
            $this->climate->flank('No new, removed or changed options.');
        }
    }

    /**
     * Show all options contained in a snapshot
     */
    private function showSnapshot()
    {
        if (count($this->bootstrap->argv) < 2) {
            $this->climate->out('wp-state show requires 1 additional arguments. Name the snapshot name to show');
            $this->climate->out('Optionally name the option name to display in detail');

            return;
        }

        $oldState = $this->readSnapshot($this->bootstrap->argv[1]);
        if (!$oldState) {
            $this->climate->out("There's no snapshot file for {$this->bootstrap->argv[1]}. Aborting");

            return;
        }

        $wpCfmSettings = $this->utils->getWPCFMSettings();

        if (count($this->bootstrap->argv) == 2) {
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
                $this->climate->table($options);
            }
        } else {
            $name = $this->bootstrap->argv[2];
            $value = $oldState->options[$name];
            $this->climate->json($value);
            $this->climate->out('');
        }
    }

    /**
     * Finds all new, modified and removed options between two snapshots
     *
     * @param \stdClass $oldState
     * @param \stdClass $newState
     * @return array
     */
    private function diff($oldState, $newState)
    {
        $added = array();
        $modified = array();
        $removed = array();
        $oldName = $oldState->name;
        $newName = $newState->name;

        $wpCfmSettings = $this->utils->getWPCFMSettings();

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
