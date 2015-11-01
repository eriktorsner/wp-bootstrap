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

    private $metaReferenceNames = array(
        '_thumbnail_id',
    );
    private $postReferenceNames = array(
    );
    private $optionReferenceNames = array(
        'page_on_front',
        'rcp_settings' => "['foobar']",
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
        $this->resolveReferences();
    }

    private function importSettings()
    {
        $wpcmd = $this->bootstrap->getWpCommand();

        $cmd = $wpcmd.'config pull wpbootstrap';
        exec($cmd);

        $src = BASEPATH.'/bootstrap/config/wpbootstrap.json';
        $trg = $this->bootstrap->localSettings->wppath.'/wp-content/config/wpbootstrap.json';
        @mkdir(dirname($trg), 0777, true);
        copy($src, $trg);
    }

    private function importContent()
    {
        $this->taxonomies = new Pushtaxonomies();
        $this->posts = new Pushposts();
        $menus = new Pushmenus();
    }

    private function resolveReferences()
    {
        // iterate metadata on all our managed posts and menuitems
        // and look out for stuff that might be a post reference
        // _thumbnail_id for instance...

        foreach ($this->metaReferenceNames as $refName) {
            foreach ($this->posts->posts as $post) {
                if (isset($post->post->post_meta[$refName])) {
                    foreach ($post->post->post_meta[$refName] as $item) {
                        $newId = $this->posts->findTargetPostId($item);
                        update_post_meta($post->id, $refName, $newId, $item);
                    }
                }
            }
        }
        foreach ($this->postReferenceNames as $refName) {
            foreach ($this->posts->posts as $post) {
                if (isset($post->post->$refName)) {
                    $newId = $this->posts->findTargetPostId($post->post->$refName);
                    $args = array(
                        'ID' => $post->id,
                        $refName => $newId,
                    );
                    wp_update_post($args);
                }
            }
        }

        $this->resolver->resolveReferences($this->optionReferenceNames);
    }

    public function findTargetPostId($target)
    {
        foreach ($this->posts->posts as $post) {
            if ($post->post->ID == $target) {
                return $post->id;
            }
        }

        return 0;
    }
}
