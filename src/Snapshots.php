<?php

namespace Wpbootstrap;

class Snapshots
{
    private $bootstrap;
    private $localSettings;
    private $helpers;
    private $log;
    private $baseFolder;

    const MAX_STRLEN = 40;

    private $expludedOptions = array(
        'cron', 'rewrite_rules',

    );

    public function __construct()
    {
        $container = Container::getInstance();
        $this->bootstrap = $container->getBootstrap();
        $this->log = $container->getLog();
        $this->helpers = $container->getHelpers();
        $this->localSettings = $container->getLocalSettings();
        $utils = $container->getUtils();
        $this->climate = $container->getCLImate();

        $this->baseFolder = BASEPATH.'/bootstrap/config/snapshots';
        $utils->includeWordpress();
    }

    public function manage()
    {
        if (count($this->bootstrap->argv) == 0) {
            $this->climate->out('wp-snapshots expects at least one subcommand');
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
                'comment' => $snapshot->comment,
            );
        }
        if (count($output) > 0) {
            $this->climate->table($output);
        }
    }

    private function diffSnapshots()
    {
        if (count($this->bootstrap->argv) < 2) {
            $this->climate->out('wp-state diff requires at least 1 additional argument Name the snapshot name to compare');
            $this->climate->out('current state with. Or name 2 existing snapshots to compare to each other');

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

        if (count($this->bootstrap->argv) == 2) {
            $options = array();
            foreach ($oldState->options as $name => $value) {
                $options[] = array(
                    'name' => $name,
                    'value' => $this->valueToString($value),
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

    private function diff($oldState, $newState)
    {
        $added = array();
        $modified = array();
        $removed = array();
        foreach ($oldState->options as $name => $value) {
            if (isset($newState->options[$name])) {
                if (md5(serialize($value)) != md5(serialize($newState->options[$name]))) {
                    $modified[] = array(
                        'state' => 'MOD',
                        'name' => $name,
                        'old' => $this->valueToString($value),
                        'new' => $this->valueToString($newState->options[$name]),
                    );
                }
            } else {
                $removed[] = array(
                    'state' => 'DEL',
                    'name' => $name,
                    'old' => $this->valueToString($value),
                    'new' => null,
                );
            }
        }
        foreach ($newState->options as $name => $value) {
            if (!isset($oldState->options[$name])) {
                $added[] = array(
                    'state' => 'NEW',
                    'name' => $name,
                    'old' => null,
                    'new' => $this->valueToString($value),
                );
            }
        }

        return array_merge($added, $modified, $removed);
    }

    private function valueToString($value)
    {
        $ret = '';

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

    private function readSnapshot($name)
    {
        $snapshotFile = $name.'.snapshot';
        if (!file_exists($this->baseFolder.'/'.$snapshotFile)) {
            return false;
        }

        return unserialize(file_get_contents($this->baseFolder.'/'.$snapshotFile));
    }

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
            if (!in_array($optionName, $this->expludedOptions)) {
                $options[$optionName] = get_option($optionName);
            }
        }

        return $options;
    }
}
