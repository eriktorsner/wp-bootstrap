<?php

namespace Wpbootstrap\Commands;

use Wpbootstrap\Bootstrap;

/**
 * Class Posts
 * @package Wpbootstrap\Command
 */
class Posts extends ItemsManagerCommand
{
    /**
     * List all posts that currectly exists in WordPress. Adds a column to indicate
     * if it's managed by WP Boostrap or not
     *
     * [--post_type=<type>]
     * : Limit output to post-type = <type>
     *
     * @subcommand list
     *
     * @param $args
     * @param $assocArgs
     *
     */
    public function listItems($args, $assocArgs)
    {
        $this->args = $args;
        $this->assocArgs = $assocArgs;

        $app = Bootstrap::getApplication();
        $fields = 'ID,post_title,post_name,post_date,post_status,post_type,post_name';

        $postTypes = $this->getAssocArg('post_type', 'all');

        if ($postTypes == 'all') {
            $postTypes = get_post_types();
            unset($postTypes['revision']);
            unset($postTypes['nav_menu_item']);
            $assocArgs['post_type'] = join(',', $postTypes);
        }
        $assocArgs['fields'] = $fields;

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

        $this->output($output);
    }

    /**
     * Add a post to be managed by WP Bootstrap
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
                $post = false;
                $args = array('name' => $postIdentifier, 'post_type' => 'any');
                $posts = get_posts($args);
                if (is_array($posts)) {
                    $post = reset($posts);
                }
            }

            if ($post) {
                $this->updateSettings(
                    array('content','posts', $post->post_type, $post->post_name)
                );
                $this->writeAppsettings();
            } else {
                $cli->warning("Post $postIdentifier not found\n");
            }
        }
    }
}