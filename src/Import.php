<?php

namespace Wpbootstrap;

class Import
{
    public $posts;
    public $taxonomies;
    public $menus;
    public $sidebars;

    public $baseUrl;
    public $uploadDir;

    private $log;

    private $postPostMetaReferenceNames = array(
        '_thumbnail_id',
    );
    private $termPostMetaReferenceNames = array(
    );

    private $optionPostReferenceNames = array(
        'page_on_front',
    );

    private $optionTermReferenceNames = array(
    );

    public function import()
    {
        error_reporting(-1);

        $container = Container::getInstance();

        $localSettings = Container::getInstance()->getLocalSettings();

        Container::getInstance()->getUtils()->includeWordPress();
        require_once $localSettings->wppath.'/wp-admin/includes/image.php';

        $this->baseUrl = get_option('siteurl');
        $this->uploadDir = wp_upload_dir();
        $this->log = $container->getLog();

        // Run the import
        $this->log->addDebug('Importing settings');
        $this->importSettings();
        $this->log->addDebug('Importing content');
        $this->importContent();

        // references
        $this->resolvePostMetaReferences();
        $this->resolveOptionReferences();
    }

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
            $cmd = $wpcmd.'config pull wpbootstrap';

            // deneutralize
            $settings = json_decode(file_get_contents($trg));
            $helpers->fieldSearchReplace($settings, Bootstrap::NEUTRALURL, $this->baseUrl);
            file_put_contents($trg, $helpers->prettyPrint(json_encode($settings)));

            $utils->exec($cmd);
        }
    }

    private function importContent()
    {
        $this->posts = new ImportPosts();
        $this->taxonomies = new ImportTaxonomies();
        $this->menus = new ImportMenus();
        $this->sidebars = new ImportSidebars();
        $this->taxonomies->assignObjects();
    }

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

    private function resolveOptionReferences()
    {
        $container = Container::getInstance();
        $appSettings = $container->getAppSettings();
        $resolver = $container->getResolver();

        if (isset($appSettings->references->posts->options)) {
            $options = $appSettings->references->posts->options;
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
        }

        $resolver->resolveOptionReferences($this->optionPostReferenceNames, 'posts');

        if (isset($appSettings->references->terms->options)) {
            $options = $appSettings->references->terms->options;
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
        }
        $resolver->resolveOptionReferences($this->optionTermReferenceNames, 'terms');
    }

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
