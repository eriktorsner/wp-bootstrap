<?php
namespace Wpbootstrap\Commands;

use Wpbootstrap\Bootstrap;

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
     * TODO: still needed?
     */
    public $menus;

    /**
     * @var \stdClass
     */
    public $sidebars;

    /**
     * Keep track of post_meta fields that are post references
     *
     * @var array
     */
    private $postPostMetaReferenceNames = array(
        '_thumbnail_id',
    );

    /**
     * Keep track of post_meta fields that are term references
     *
     * @var array
     */
    private $termPostMetaReferenceNames = array();

    /**
     * Keep track of options that are post references
     *
     * @var array
     */
    private $optionPostReferenceNames = array(
        'page_on_front',
    );

    /**
     * Keep track of options that are term references
     *
     * @var array
     */
    private $optionTermReferenceNames = array();


    public function run($args, $assocArgs)
    {
        $app = \Wpbootstrap\Bootstrap::getApplication();
        $cli = $app['cli'];

        //error_reporting(-1);

        $extensions = $app['extensions'];
        $extensions->init();

        // Run the import
        do_action('wp-bootstrap_before_import');

        $cli->log('Importing settings');
        $importOptions = $app['importoptions'];
        $importOptions->import();
        do_action('wp-bootstrap_after_import_settings');

        $cli->log('Importing content');

        $cli->debug('Importing posts');
        $posts = $app['importposts'];
        $posts->import();
        $this->posts = &$posts->posts;

        $cli->debug('Importing taxonomies');
        $taxonomies = $app['importtaxonomies'];
        $taxonomies->import();
        $this->taxonomies = &$taxonomies->taxonomies;

        $cli->debug('Importing menus');
        $menus = $app['importmenus'];
        $menus->import();

        $cli->debug('Importing sidebars');
        $sidebars = $app['importsidebars'];
        $sidebars->import();

        $taxonomies->assignObjects();
        do_action('wp-bootstrap_after_import_content');

        // references
        $cli->log('Resolving references');
        $this->resolvePostMetaReferences();
        $this->resolveOptionReferences();

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
                    if ($post->post['ID'] == $originalId) {
                        return $post->id;
                    }
                }
                break;
            case 'term':
                foreach ($this->taxonomies as $taxonomy) {
                    foreach ($taxonomy->terms as $term) {
                        if ($term->term['term_id'] == $originalId) {
                            return $term->id;
                        }
                    }
                }
        }

        return 0;
    }

    /**
     * Runs after all import is done. Resolves post_meta fields that
     * are references to posts or terms
     */
    private function resolvePostMetaReferences()
    {
        $app = Bootstrap::getApplication();
        $resolver = $app['resolver'];
        $settings = $app['settings'];

        if (isset($settings['references']['posts']['postmeta'])) {
            $references = $settings['references']['posts']['postmeta'];
            if (is_array($references)) {
                foreach ($references as $value) {
                    if (is_string($value)) {
                        $this->postPostMetaReferenceNames[] = $value;
                    } elseif (is_object($value)) {
                        $arr = (array) $value;
                        $key = key($arr);
                        $this->postPostMetaReferenceNames[$key] = $arr[$key];
                    } elseif (is_array($value)) {
                        $key = key($value);
                        $this->postPostMetaReferenceNames[$key] = $value[$key];
                    }
                }
            }
        }
        $resolver->resolvePostMetaReferences($this->postPostMetaReferenceNames, 'post');

        if (isset($settings['references']['terms']['postmeta'])) {
            $references = $settings['references']['terms']['postmeta'];
            if (is_array($references)) {
                foreach ($references as $value) {
                    if (is_string($value)) {
                        $this->termPostMetaReferenceNames[] = $value;
                    } elseif (is_object($value)) {
                        $arr = (array) $value;
                        $key = key($arr);
                        $this->termPostMetaReferenceNames[$key] = $arr[$key];
                    } elseif (is_array($value)) {
                        $key = key($value);
                        $this->termPostMetaReferenceNames[$key] = $value[$key];
                    }
                }
            }
        }
        $resolver->resolvePostMetaReferences($this->termPostMetaReferenceNames, 'term');
    }
    /**
     * Runs after all import is done. Resolves options fields that
     * are references to posts or terms
     */
    private function resolveOptionReferences()
    {
        $app = Bootstrap::getApplication();
        $resolver = $app['resolver'];
        $settings = $app['settings'];

        $options = [];
        if (isset($settings['references']['posts']['options'])) {
            $options = $settings['references']['posts']['options'];
        }

        // Ask any extensions to add option references via filter
        $options = apply_filters('wp-bootstrap_option_post_references', $options);

        if (is_array($options)) {
            foreach ($options as $value) {
                if (is_string($value)) {
                    $this->optionPostReferenceNames[] = $value;
                } elseif (is_object($value)) {
                    $arr = (array) $value;
                    $key = key($arr);
                    $this->optionPostReferenceNames[$key] = $arr[$key];
                } elseif (is_array($value)) {
                    $key = key($value);
                    $this->optionPostReferenceNames[$key] = $value[$key];
                }
            }
        }
        $resolver->resolveOptionReferences($this->optionPostReferenceNames, 'post');

        $options = [];
        if (isset($settings['references']['terms']['options'])) {
            $options = $settings['references']['terms']['options'];
        }
        // Ask any extensions to add option references via filter
        $options = apply_filters('wp-bootstrap_option_term_references', $options);

        if (is_array($options)) {
            foreach ($options as $value) {
                if (is_string($value)) {
                    $this->optionTermReferenceNames[] = $value;
                } elseif (is_object($value)) {
                    $arr = (array) $value;
                    $key = key($arr);
                    $this->optionTermReferenceNames[$key] = $arr[$key];
                } elseif (is_array($value)) {
                    $key = key($value);
                    $this->optionTermReferenceNames[$key] = $value[$key];
                }
            }
        }
        $resolver->resolveOptionReferences($this->optionTermReferenceNames, 'term');
    }
}