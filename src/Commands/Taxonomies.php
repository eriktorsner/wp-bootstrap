<?php

namespace Wpbootstrap\Commands;

use Wpbootstrap\Bootstrap;

/**
 * Class Posts
 * @package Wpbootstrap\Command
 */
class Taxonomies extends ItemsManagerCommand
{
    /**
     * List terms that currectly exists in WordPress. Adds a column to indicate
     * if it's managed by WP Boostrap or not
     *
     * <taxonomy>...
     * :List terms of one or more taxonomies
     *
     * [--format=<format>]
     * :Accepted values: table, csv, json, count, ids, yaml. Default: table
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
        $terms = $this->getTerms();

        $managedTerms = array();
        if (isset($app['settings']['content']['taxonomies'])) {
            $managedTerms = $app['settings']['content']['taxonomies'];
        }

        $output = array();
        foreach ($terms as $term) {
            $fldManaged = 'No';
            if (isset($managedTerms[$term->taxonomy])) {
                if (is_array($managedTerms[$term->taxonomy])) {
                    $fldManaged = in_array($term->slug, $managedTerms[$term->taxonomy])?'Yes':'No';
                } else {
                    if ($managedTerms[$term->taxonomy] == '*') {
                        $fldManaged = 'Yes';
                    }
                }
            }

            $row = array();
            foreach ($term as $fieldName => $fieldValue) {
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
     * <taxonomy>
     * :The name of the taxonomy
     *
     * <term_identifier>...
     * :One or more term slugs or term id's to be added
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
        $this->args = $args;
        $this->assocArgs = $assocArgs;

        $app = Bootstrap::getApplication();
        $cli = $app['cli'];
        $taxonomy = array_shift($args);

        foreach ($args as $termIdentifier) {
            $slug = false;

            if ($termIdentifier != '*') {
                if (is_numeric($termIdentifier)) {
                    $term = get_term_by('id', $termIdentifier, $taxonomy);
                } else {
                    $term = get_term_by('slug', $termIdentifier, $taxonomy);
                }
                if ($term) {
                    $slug = $term->slug;
                }
            } else {
                $slug = $termIdentifier;
            }

            if ($slug) {
                $this->updateSettings(
                    array('content','taxonomies', $taxonomy, $slug)
                );
            } else {
                $cli->warning("Term $termIdentifier not found");
                return;
            }
        }
        $this->writeAppsettings();
    }

    private function getTerms()
    {
        global $wp_version;
        if (version_compare($wp_version, '4.5', '>=')) {
            $terms = get_terms(array(
                'taxonomy' => $this->args,
                'hide_empty' => false,
            ));
            return $terms;
        } else {
            $terms = get_terms($this->args, array(
                'hide_empty' => false,
            ));

            return $terms;
        }
    }
}