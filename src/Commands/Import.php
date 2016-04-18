<?php
namespace Wpbootstrap\Commands;

/**
 * Class Import
 * @package Wpbootstrap
 */
class Import extends BaseCommand
{
    /**
     * @var \stdClass
     */
    public $posts;

    /**
     * @var \stdClass
     */
    public $taxonomies;

    /**
     * @var \stdClass
     */
    public $menus;

    /**
     * @var \stdClass
     */
    public $sidebars;


    public function run($args, $assocArgs)
    {
        $app = \Wpbootstrap\Bootstrap::getApplication();
        $cli = $app['cli'];

        error_reporting(-1);

        $extensions = $app['extensions'];
        $extensions->init();

        require_once $app['path'] . '/wp-admin/includes/image.php';

        $this->baseUrl = get_option('siteurl');
        $this->uploadDir = wp_upload_dir();


        // Run the import
        do_action('wp-bootstrap_before_import');

        $cli->log('Importing settings');
        //$this->importSettings();
        do_action('wp-bootstrap_after_import_settings');

        $cli->log('Importing content');
        //$this->importContent();
        do_action('wp-bootstrap_after_import_content');

        // references
        $cli->log('Resolving references');
        //$this->resolvePostMetaReferences();
        //$this->resolveOptionReferences();

        do_action('wp-bootstrap_after_import');

    }

    /**
     * Finds a post or term based on it's original id. If found, returns the new (after import) id
     *
     * @param int $originalId
     * @param string $type
     * @return int
     */
    public function findTargetObjectId($originalId, $type)
    {
        switch ($type) {
            case 'post':
                foreach ($this->posts as $post) {
                    if ($post->post->ID == $originalId) {
                        return $post->id;
                    }
                }
                break;
            case 'term':
                foreach ($this->taxonomies as $taxonomy) {
                    foreach ($taxonomy->terms as $term) {
                        if ($term->term->term_id == $originalId) {
                            return $term->id;
                        }
                    }
                }
        }

        return 0;
    }
}