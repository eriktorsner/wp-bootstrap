<?php

namespace Wpbootstrap\Commands;

use Wpbootstrap\Bootstrap;

/**
 * Class Posts
 * @package Wpbootstrap\Command
 */
class Terms
{
    private $preservedFields = array();

    /**
     * List all terms that currectly exists in WordPress. Adds a column to indicate
     * if it's managed by WP Boostrap or not
     *
     * <taxonomy>
     * :List terms of one or more taxonomies
     *
     * [--fields=<fields>]
     * : Limit the output to specific object fields.
     *
     * @subcommand list
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function listItems($args, $assocArgs)
    {
        $app = Bootstrap::getApplication();
        $defaultFields = 'term_id,term_taxonomy_id,name,slug,description,parent,count';

        $this->preserveAndSet($assocArgs, 'format', 'table', 'json');
        $this->preserveAndSetList($assocArgs, 'fields', $defaultFields, 'post_type,post_name');
        $postTypes = isset($assocArgs['post_type'])?$assocArgs['post_type']:'all';

        if ($postTypes == 'all') {
            $postTypes = get_post_types();
            unset($postTypes['revision']);
            unset($postTypes['nav_menu_item']);
            $assocArgs['post_type'] = join(',', $postTypes);
        }

        $posts = $this->getJsonList('post list --format=json', $args, $assocArgs);
        $managedPosts = array();
        if (isset($app['settings']['content']['posts'])) {
            $managedPosts = $app['settings']['content']['posts'];
        }

        $output = array();
        foreach ($posts as $post) {
            $fldManaged = 'No';
            if (isset($managedPosts[$post->post_type])) {
                if (is_array($managedPosts[$post->post_type])) {
                    $fldManaged = in_array($post->post_name, $managedPosts[$post->post_type])?'Yes':'No';
                } else {
                    if ($managedPosts[$post->post_type] == '*') {
                        $fldManaged = 'Yes';
                    }
                }
            }

            $row = array();
            foreach ($post as $fieldName => $fieldValue) {
                $row[$fieldName] = $fieldValue;
            }
            $row['Managed'] = $fldManaged;
            $output[] = $row;
        }

        if (count($output) > 0) {
            $cliutils = $app['cliutils'];
            $cliutils->format_items(
                $this->preservedFields['format'],
                $output,
                array_keys($output[0])
            );
        }
    }

    /**
     * Add a post to be managed by WP Bootstrap
     *
     *
     * <post_identifier>...
     * :One or more post_name (slug) or post id's to be added
     *
     * [--post_type=<post-type>]
     * :When adding a posts by post name, limit the post search
     * to single post-type.
     *
     * @param $args
     * @param $assocArgs
     */
    public function add($args, $assocArgs)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        foreach ($args as $postIdentifier) {
            if (is_numeric($postIdentifier)) {
                $post = get_post($postIdentifier);
            } else {
                $args = array('name' => $postIdentifier, 'post_type' => 'any');
                $posts = get_posts($args);
                $post = array_shift($posts);
            }
            if ($post) {
                $settings = $app['settings'];

                $this->addToSettings(
                    array('content','posts', $post->post_type, $post->post_name),
                    $settings
                );
                $app['settings'] = $settings;

                $settingsManager = $app['settingsmanager'];
                $settingsManager->writeAppsettings();
            } else {
                $cli->warning("Post $postIdentifier not found\n");
            }
        }
    }

    /**
     * @param string $path
     */
    private function addToSettings($path, &$settings)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        $head = array_shift($path);
        if (count($path) == 0) {
            if (is_array($settings)) {
                if (!in_array($head, $settings)) {
                    $settings[] = $head;
                } else {
                    $cli->warning("$head is already managed.");
                }
            }
        } else {
            if (!isset($settings[$head])) {
                $settings[$head] = array();
            }
            $this->addToSettings($path, $settings[$head]);
        }
    }

    /**
     * @param array $assocArgs
     * @param string $name
     * @param string $default
     * @param string $new
     */
    private function preserveAndSetList(&$assocArgs, $name, $default, $new)
    {
        $this->preservedFields[$name] = $default;
        if (isset($assocArgs[$name])) {
            $this->preservedFields[$name] = $assocArgs[$name];
        }

        $assocArgs[$name] = $this->preservedFields[$name] . ",$new";

    }

    /**
     * @param array $assocArgs
     * @param string $name
     * @param string $default
     * @param string $new
     */
    private function preserveAndSet(&$assocArgs, $name, $default, $new)
    {
        $this->preservedFields[$name] = $default;
        if (isset($assocArgs[$name])) {
            $this->preservedFields[$name] = $assocArgs[$name];
        }

        $assocArgs[$name] = $new;
    }


    private function getJsonList($cmd, $args = array(), $assocArgs = array())
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        $ret = $cli->launch_self($cmd, $args, $assocArgs, false, true);

        if ($ret->return_code == 0) {
            return json_decode($ret->stdout);
        }

        return array();
    }
}