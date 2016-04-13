<?php

namespace Wpbootstrap;

/**
 * Class Import
 * @package Wpbootstrap
 */
class Import
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

    /**
     * @var string
     */
    public $baseUrl;

    /**
     * @var string
     */
    public $uploadDir;

    /**
     * @var \Monolog\Logger
     */
    private $log;

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

    /**
     * Entry point for the import process
     */
    public function import()
    {
        error_reporting(-1);

        $container = Container::getInstance();
        $localSettings = Container::getInstance()->getLocalSettings();
        $container->getUtils()->includeWordPress();

        $extensions = $container->getExtensions();
        $extensions->init();

        require_once $localSettings->wppath.'/wp-admin/includes/image.php';

        $this->baseUrl = get_option('siteurl');
        $this->uploadDir = wp_upload_dir();
        $this->log = $container->getLog();

        // Run the import
        do_action('wp-bootstrap_before_import');

        $this->log->addInfo('Importing settings');
        $this->importSettings();
        do_action('wp-bootstrap_after_import_settings');

        $this->log->addInfo('Importing content');
        $this->importContent();
        do_action('wp-bootstrap_after_import_content');

        // references
        $this->log->addInfo('Resolving references');
        $this->resolvePostMetaReferences();
        $this->resolveOptionReferences();

        do_action('wp-bootstrap_after_import');

    }

    /**
     * Import settings via WP-CFM
     */
    private function importSettings()
    {
        $container = Container::getInstance();
        $localSettings = $container->getLocalSettings();
        $utils = $container->getUtils();
        $helpers = $container->getHelpers();
        $wpcmd = $utils->getWpCommand();

        $src = BASEPATH.'/bootstrap/config/wpbootstrap.json';

        $trg = $localSettings->wppath.'/wp-content/config/wpbootstrap.json';
        if (file_exists($src)) {
            @mkdir(dirname($trg), 0777, true);
            copy($src, $trg);

            // deneutralize
            $settings = json_decode(file_get_contents($trg));
            $helpers->fieldSearchReplace($settings, Bootstrap::NEUTRALURL, $this->baseUrl);
            file_put_contents($trg, $helpers->prettyPrint(json_encode($settings)));

            $cmd = $wpcmd.'config pull wpbootstrap';
            $utils->exec($cmd);

            // Flush options to make sure no other code overwrites based
            // on old settings stored in cache
            wp_cache_flush();
        }
    }

    /**
     * Import content
     */
    private function importContent()
    {
        $container = Container::getInstance();

        $this->log->addDebug('Importing posts');
        $this->posts = $container->getImportPosts();

        $this->log->addDebug('Importing taxonomies');
        $this->taxonomies = $container->getImportTaxonomies();

        $this->log->addDebug('Importing menus');
        $this->menus = $container->getImportMenus();

        $this->log->addDebug('Importing sidebars');
        $this->sidebars = $container->getImportSidebars();

        $this->taxonomies->assignObjects();
    }

    /**
     * Runs after all import is done. Resolves post_meta fields that
     * are references to posts or terms
     */
    private function resolvePostMetaReferences()
    {
        $container = Container::getInstance();
        $appSettings = $container->getAppSettings();
        $resolver = $container->getResolver();

        if (isset($appSettings->references->posts->postmeta)) {
            $references = $appSettings->references->posts->postmeta;
            if (is_array($references)) {
                foreach ($references as $value) {
                    if (is_string($value)) {
                        $this->postPostMetaReferenceNames[] = $value;
                    } elseif (is_object($value)) {
                        $arr = (array) $value;
                        $key = key($arr);
                        $this->postPostMetaReferenceNames[$key] = $arr[$key];
                    }
                }
            }
        }
        $resolver->resolvePostMetaReferences($this->postPostMetaReferenceNames, 'posts');

        if (isset($appSettings->references->terms->postmeta)) {
            $references = $appSettings->references->terms->postmeta;
            if (is_array($references)) {
                foreach ($references as $value) {
                    if (is_string($value)) {
                        $this->termPostMetaReferenceNames[] = $value;
                    } elseif (is_object($value)) {
                        $arr = (array) $value;
                        $key = key($arr);
                        $this->termPostMetaReferenceNames[$key] = $arr[$key];
                    }
                }
            }
        }
        $resolver->resolvePostMetaReferences($this->termPostMetaReferenceNames, 'terms');
    }

    /**
     * Runs after all import is done. Resolves options fields that
     * are references to posts or terms
     */
    private function resolveOptionReferences()
    {
        $container = Container::getInstance();
        $appSettings = $container->getAppSettings();
        $resolver = $container->getResolver();

        $options = [];
        if (isset($appSettings->references->posts->options)) {
            $options = $appSettings->references->posts->options;
        }
        $options = apply_filters('wp-bootstrap_option_post_references', $options);

        if (is_array($options)) {
            foreach ($options as $value) {
                if (is_string($value)) {
                    $this->optionPostReferenceNames[] = $value;
                } elseif (is_object($value)) {
                    $arr = (array) $value;
                    $key = key($arr);
                    $this->optionPostReferenceNames[$key] = $arr[$key];
                }
            }
        }
        $resolver->resolveOptionReferences($this->optionPostReferenceNames, 'posts');

        $options = [];
        if (isset($appSettings->references->terms->options)) {
            $options = $appSettings->references->terms->options;
        }
        $options = apply_filters('wp-bootstrap_option_term_references', $options);

        if (is_array($options)) {
            foreach ($options as $value) {
                if (is_string($value)) {
                    $this->optionTermReferenceNames[] = $value;
                } elseif (is_object($value)) {
                    $arr = (array) $value;
                    $key = key($arr);
                    $this->optionTermReferenceNames[$key] = $arr[$key];
                }
            }
        }
        $resolver->resolveOptionReferences($this->optionTermReferenceNames, 'terms');
    }

    /**
     * Finds a post or term based on it's original id. If found, returns the new (after import) id
     *
     * @param int $target
     * @param string $type
     * @return int
     */
    public function findTargetObjectId($target, $type)
    {
        switch ($type) {
            case 'posts':
                foreach ($this->posts->posts as $post) {
                    if ($post->post->ID == $target) {
                        return $post->id;
                    }
                }
                break;
            case 'terms':
                foreach ($this->taxonomies->taxonomies as $taxonomy) {
                    foreach ($taxonomy->terms as $term) {
                        if ($term->term->term_id == $target) {
                            return $term->id;
                        }
                    }
                }
        }

        return 0;
    }
}
