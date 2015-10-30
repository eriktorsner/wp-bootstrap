<?php
namespace Wpbootstrap;

class Export
{
    private $baseUrl;
    private $uploadDir;
    private $posts;
    private $taxonomies;

    private $bootstrap;
    private $resolver;
    private static $self = false;

    public static function getInstance()
    {
        if (!self::$self) {
            self::$self = new Export();
        }

        return self::$self;
    }

    public function export($e = null)
    {
        $this->bootstrap = Bootstrap::getInstance();
        $this->resolver = Resolver::getInstance();
        $this->bootstrap->init($e);
        $this->bootstrap->includeWordPress();

        $this->baseUrl = get_option('siteurl');
        $this->uploadDir = wp_upload_dir();

        $this->exportSettings();
        $this->exportContent();
    }

    private function exportSettings()
    {
        $wpcmd = $this->bootstrap->getWpCommand();

        $cmd = $wpcmd.'config push wpbootstrap 2>/dev/null';
        exec($cmd);

        $src = $this->bootstrap->localSettings->wppath.'/wp-content/config/wpbootstrap.json';
        $trg = BASEPATH.'/bootstrap/config/wpbootstrap.json';
        if (file_exists($src)) {
            @mkdir(dirname($trg), 0777, true);
            copy($src, $trg);

            // sanity check
            $settings = json_decode(file_get_contents($trg));
            $label = '.label';
            if (is_null($settings->$label)) {
                $settings->$label = 'wpbootstrap';
                file_put_contents($trg, $this->bootstrap->prettyPrint(json_encode($settings)));
            }
        }
    }

    private function exportContent()
    {
        $base = BASEPATH.'/bootstrap';

        $this->bootstrap->recursiveRemoveDirectory($base.'/menus');
        $this->bootstrap->recursiveRemoveDirectory($base.'/posts');
        $this->bootstrap->recursiveRemoveDirectory($base.'/media');
        $this->bootstrap->recursiveRemoveDirectory($base.'/taxonomies');

        $this->posts = new \stdClass();
        if (isset($this->bootstrap->appSettings->wpbootstrap->posts)) {
            foreach ($this->bootstrap->appSettings->wpbootstrap->posts as $postType => $arr) {
                $this->posts->$postType = array();
                foreach ($arr as $post) {
                    $newPost = new \stdClass();
                    $newPost->slug = $post;
                    $newPost->done = false;
                    array_push($this->posts->$postType, $newPost);
                }
            }
        }

        $this->taxonomies = new \stdClass();
        if (isset($this->bootstrap->appSettings->wpbootstrap->taxonomies)) {
            foreach ($this->bootstrap->appSettings->wpbootstrap->taxonomies as $taxonomy => $terms) {
                $this->taxonomies->$taxonomy = array();
                if ($terms == "*") {
                    $allTerms = get_terms($taxonomy);
                    foreach ($allTerms as $term) {
                        $newTerm = new \stdClass();
                        $newTerm->slug = $term->slug;
                        $newTerm->done = false;
                        array_push($this->taxonomies->$taxonomy, $newTerm);
                    }
                } else {
                    foreach ($terms as $term) {
                        $newTerm = new \stdClass();
                        $newTerm->slug = $term;
                        $newTerm->done = false;
                        array_push($this->taxonomies->$taxonomy, $newTerm);
                    }
                }
            }
        }

        $this->exportMenus();
        $count = 999;
        while ($count > 0) {
            $count = $this->exportPosts();
        }

        $count = 999;
        while ($count > 0) {
            $count = $this->exportTaxonomies();
        }
        $this->exportMedia();
    }

    private function exportPosts()
    {
        global $wpdb;
        $count = 0;

        foreach ($this->posts as $postType => &$posts) {
            foreach ($posts as &$post) {
                if ($post->done == true) {
                    continue;
                }
                $postId = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s",
                    $postType,
                    $post->slug
                ));
                $objPost = get_post($postId);
                if (!$objPost) {
                    continue;
                }
                $meta = get_post_meta($objPost->ID);
                $objPost->post_meta = $meta;
                $this->resolver->fieldSearchReplace($objPost, $this->baseUrl, Bootstrap::NETURALURL);

                $file = BASEPATH."/bootstrap/posts/{$objPost->post_type}/{$objPost->post_name}";
                @mkdir(dirname($file), 0777, true);
                file_put_contents($file, serialize($objPost));
                $post->done = true;
                $count++;
            }
        }

        return $count;
    }

    private function exportMedia()
    {
        global $wpdb;
        foreach ($this->posts as $postType => &$posts) {
            foreach ($posts as &$post) {
                $postId = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_type='%s' AND post_name = %s",
                    $postType,
                    $post->slug
                ));
                $media = get_attached_media('', $postId);
                foreach ($media as $item) {
                    $itemMeta = wp_get_attachment_metadata($item->ID, true);
                    $item->meta = $itemMeta;
                    $dir = BASEPATH.'/bootstrap/media/'.$item->post_name;
                    @mkdir($dir, 0777, true);
                    file_put_contents($dir.'/meta', serialize($item));
                    $src = $this->uploadDir['basedir'].'/'.$itemMeta['file'];
                    $trg = $dir.'/'.basename($itemMeta['file']);
                    copy($src, $trg);
                }
            }
        }
    }

    private function exportMenus()
    {
        if (!isset($this->bootstrap->appSettings->wpbootstrap->menus)) {
            return;
        }
        foreach ($this->bootstrap->appSettings->wpbootstrap->menus as $menu => $locations) {
            wp_set_current_user(1);
            $loggedInmenuItems = wp_get_nav_menu_items($menu);
            wp_set_current_user(0);
            $notloggedInmenuItems = wp_get_nav_menu_items($menu);
            $menuItems = array_merge($loggedInmenuItems, $notloggedInmenuItems);
            $menuItems = $this->bootstrap->uniqueObjectArray($menuItems, 'ID');

            $dir = BASEPATH.'/bootstrap/menus/'.$menu;
            array_map('unlink', glob("$dir/*"));
            @mkdir($dir, 0777, true);

            foreach ($menuItems as $menuItem) {
                $obj = get_post($menuItem->ID);
                $obj->post_meta = get_post_meta($obj->ID);

                switch ($obj->post_meta['_menu_item_type'][0]) {
                    case 'post_type':
                        $postType = $obj->post_meta['_menu_item_object'][0];
                        $postId = $obj->post_meta['_menu_item_object_id'][0];
                        $objPost = get_post($postId);
                        $this->addPost($postType, $objPost->post_name);
                        break;
                    case 'taxonomy':
                        $id = $obj->post_meta['_menu_item_object_id'][0];
                        $taxonomy = $obj->post_meta['_menu_item_object'][0];
                        $objTerm = get_term($id, $taxonomy);
                        if (!is_wp_error($objTerm)) {
                            $this->addTerm($taxonomy, $objTerm->slug);
                        }
                        break;
                }
                $this->resolver->fieldSearchReplace($obj, $this->baseUrl, Bootstrap::NETURALURL);

                $file = $dir.'/'.$menuItem->post_name;
                file_put_contents($file, serialize($obj));
            }
        }
    }

    private function exportTaxonomies()
    {
        $count = 0;
        foreach ($this->taxonomies as $taxonomy => &$terms) {
            foreach ($terms as &$term) {
                if ($term->done == true) {
                    continue;
                }

                $objTerm = get_term_by('slug', $term->slug, $taxonomy);
                $file = BASEPATH."/bootstrap/taxonomies/$taxonomy/{$term->slug}";
                @mkdir(dirname($file), 0777, true);
                file_put_contents($file, serialize($objTerm));

                if ($objTerm->parent) {
                    $parentTerm = get_term_by('id', $objTerm->parent, $taxonomy);
                    $this->addTerm($taxonomy, $parentTerm->slug);
                }
                $term->done = true;
                $count++;
            }
        }

        return $count;
    }

    private function addTerm($taxonomy, $slug)
    {
        if (!isset($this->taxonomies->$taxonomy)) {
            $this->taxonomies->$taxonomy = array();
        }
        foreach ($this->taxonomies->$taxonomy as $term) {
            if ($term->slug == $slug) {
                return;
            }
        }

        $newTerm = new \stdClass();
        $newTerm->slug = $slug;
        $newTerm->done = false;
        array_push($this->taxonomies->$taxonomy, $newTerm);
    }

    private function addPost($postType, $slug)
    {
        if (!isset($this->posts->$postType)) {
            $this->posts->$postType = array();
        }
        foreach ($this->posts->$postType as $post) {
            if ($post->slug == $slug) {
                return;
            }
        }

        $newPost = new \stdClass();
        $newPost->slug = $slug;
        $newPost->done = false;
        array_push($this->posts->$postType, $newPost);
    }
}
