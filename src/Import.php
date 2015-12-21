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

    private $utils;

    private $metaPostReferenceNames = array(
        '_thumbnail_id',
    );
    private $metaTermReferenceNames = array(
    );

    private $postPostReferenceNames = array(
    );

    private $postTermReferenceNames = array(
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

        // Run the import
        $this->importSettings();
        $this->importContent();

        // references
        $this->resolveMetaReferences();
        $this->resolvePostReferences();
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
            $helpers->fieldSearchReplace($settings, Bootstrap::NETURALURL, $this->baseUrl);
            file_put_contents($trg, $helpers->prettyPrint(json_encode($settings)));

            $utils->exec($cmd);
        }
    }

    private function importContent()
    {
        $this->taxonomies = new ImportTaxonomies();
        $this->posts = new ImportPosts();
        $this->menus = new ImportMenus();
        $this->sidebars = new ImportSidebars();
        $this->taxonomies->assignObjects();
    }

    private function resolveMetaReferences()
    {
        foreach ($this->metaPostReferenceNames as $refName) {
            foreach ($this->posts->posts as $post) {
                if (isset($post->post->post_meta[$refName])) {
                    foreach ($post->post->post_meta[$refName] as $item) {
                        $newId = $this->findTargetObjectId($item, 'posts');
                        if ($newId != 0) {
                            update_post_meta($post->id, $refName, $newId, $item);
                        }
                    }
                }
            }
        }
        foreach ($this->metaTermReferenceNames as $refName) {
            foreach ($this->posts->posts as $post) {
                if (isset($post->post->post_meta[$refName])) {
                    foreach ($post->post->post_meta[$refName] as $item) {
                        $newId = $this->findTargetObjectId($item, 'terms');
                        if ($newId != 0) {
                            update_post_meta($post->id, $refName, $newId, $item);
                        }
                    }
                }
            }
        }
    }

    private function resolvePostReferences()
    {
        foreach ($this->postPostReferenceNames as $refName) {
            foreach ($this->posts->posts as $post) {
                if (isset($post->post->$refName)) {
                    $newId = $this->findTargetObjectId($post->post->$refName, 'posts');
                    if ($newId > 0) {
                        $args = array('ID' => $post->id, $refName => $newId);
                        wp_update_post($args);
                    }
                }
            }
        }
        foreach ($this->postTermReferenceNames as $refName) {
            foreach ($this->posts->posts as $post) {
                if (isset($post->post->$refName)) {
                    $newId = $this->findTargetObjectId($post->post->$refName, 'terms');
                    if ($newId > 0) {
                        $args = array('ID' => $post->id, $refName => $newId);
                        wp_update_post($args);
                    }
                }
            }
        }
    }

    private function resolveOptionReferences()
    {
        $container = Container::getInstance();
        $appSettings = $container->getAppSettings();
        $resolver = $container->getResolver();

        if (isset($appSettings->content->references->posts->options)) {
            $options = $appSettings->content->references->posts->options;
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

        if (isset($appSettings->content->references->terms->options)) {
            $options = $appSettings->content->references->terms->options;
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
