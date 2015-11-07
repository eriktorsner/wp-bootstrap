<?php
namespace Wpbootstrap;

class Import
{
    public $posts;
    public $taxonomies;
    public $baseUrl;
    public $uploadDir;

    private $bootstrap;
    private $resolver;
    private static $self = false;

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

    public static function getInstance()
    {
        if (!self::$self) {
            self::$self = new Import();
        }

        return self::$self;
    }

    public function import($e = null)
    {
        $this->bootstrap = Bootstrap::getInstance();
        $this->resolver = Resolver::getInstance();
        $this->bootstrap->init($e);

        $this->bootstrap->includeWordPress();
        require_once $this->bootstrap->localSettings->wppath."/wp-admin/includes/image.php";

        $this->baseUrl = get_option('siteurl');
        $this->uploadDir = wp_upload_dir();

        $this->importSettings();
        $this->importContent();

        // references
        $this->resolveMetaReferences();
        $this->resolvePostReferences();
        $this->resolveOptionReferences();
    }

    private function importSettings()
    {
        $wpcmd = $this->bootstrap->getWpCommand();

        $src = BASEPATH.'/bootstrap/config/wpbootstrap.json';
        $trg = $this->bootstrap->localSettings->wppath.'/wp-content/config/wpbootstrap.json';
        @mkdir(dirname($trg), 0777, true);
        copy($src, $trg);

        $cmd = $wpcmd.'config pull wpbootstrap';
        exec($cmd);
    }

    private function importContent()
    {
        $this->taxonomies = new Pushtaxonomies();
        $this->posts = new Pushposts();
        $menus = new Pushmenus();
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
        $appSettings = $this->bootstrap->appSettings;
        if (isset($appSettings->wpbootstrap->references->posts->options)) {
            $options = $appSettings->wpbootstrap->references->posts->options;
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
        $this->resolver->resolveOptionReferences($this->optionPostReferenceNames, 'posts');

        if (isset($appSettings->wpbootstrap->references->terms->options)) {
            $options = $appSettings->wpbootstrap->references->terms->options;
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
        $this->resolver->resolveOptionReferences($this->optionTermReferenceNames, 'terms');
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
