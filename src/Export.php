<?php
namespace Wpbootstrap;

class Export
{
    private static $baseUrl;
    private static $uploadDir;
    private static $posts;
    private static $taxonomies;

    public static function export($e = null)
    {
        Bootstrap::init($e);
        Bootstrap::includeWordPress();

        self::$baseUrl = get_option('siteurl');
        self::$uploadDir = wp_upload_dir();

        self::exportSettings();
        self::exportContent();

    }

    private static function exportSettings()
    {
        $wpcmd = Bootstrap::getWpCommand();

        $cmd = $wpcmd.'config push wpbootstrap';
        exec($cmd);

        $src = Bootstrap::$localSettings->wppath.'/wp-content/config/wpbootstrap.json';
        $trg = BASEPATH.'/bootstrap/config/wpbootstrap.json';
        if (file_exists($src)) {
            @mkdir(dirname($trg), 0777, true);
            copy($src, $trg);

            // sanity check
            $settings = json_decode(file_get_contents($trg));
            $label = '.label';
            if (is_null($settings->$label)) {
                $settings->$label = 'wpbootstrap';
                file_put_contents($trg, Bootstrap::prettyPrint(json_encode($settings)));
            }
        }
    }

    private static function exportContent()
    {
        $base = BASEPATH.'/bootstrap/config';

        Bootstrap::recursiveRemoveDirectory($base.'/menus');
        Bootstrap::recursiveRemoveDirectory($base.'/posts');
        Bootstrap::recursiveRemoveDirectory($base.'/media');
    	Bootstrap::recursiveRemoveDirectory($base.'/taxonomies');

        self::$posts = new \stdClass();
        if(isset(Bootstrap::$appSettings->wpbootstrap->posts)) {
            foreach (Bootstrap::$appSettings->wpbootstrap->posts as $postType => $arr) {
                self::$posts->$postType = array();
                foreach($arr as $post) {
                    $newPost = new \stdClass();
                    $newPost->slug = $post;
                    $newPost->done = false;
                    array_push(self::$posts->$postType, $newPost);
                }
            }
        }

        self::$taxonomies = new \stdClass();
        if(isset(Bootstrap::$appSettings->wpbootstrap->taxonomies)) {
            foreach(Bootstrap::$appSettings->wpbootstrap->taxonomies as $taxonomy => $terms) {
                self::$taxonomies->$taxonomy = array();
                echo "Terms: $terms \n";
                if($terms == "*") {
                    $allTerms = get_terms($taxonomy);
                    foreach($allTerms as $term) {
                        $newTerm = new \stdClass();
                        $newTerm->slug = $term->slug;
                        $newTerm->done = false;                 
                        array_push(self::$taxonomies->$taxonomy, $newTerm);   
                    }
                } else {
                    print_r($terms);
                    foreach($terms as $term) {
                        $newTerm = new \stdClass();
                        $newTerm->slug = $term;
                        $newTerm->done = false;
                        array_push(self::$taxonomies->$taxonomy, $newTerm);   
                    }
                }
                
            }
        }

        self::exportMenus();
        $count = 999;
        while($count > 0) {
            $count = self::exportPosts();
        }

        $count = 999;
        while($count > 0) {
            $count = self::exportTaxonomies();
        }
        self::exportMedia();

    }

    private static function exportPosts()
    {
        global $wpdb;
        $count = 0;

        foreach (self::$posts as $postType => &$posts) {
            foreach ($posts as &$post) {
                if($post->done == true) {
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
                Resolver::fieldSearchReplace($objPost, self::$baseUrl, Bootstrap::NETURALURL);

                $file = BASEPATH."/bootstrap/posts/{$objPost->post_type}/{$objPost->post_name}";
                @mkdir(dirname($file), 0777, true);
                file_put_contents($file, serialize($objPost));
                $post->done = true;
                $count++;
            }
        }
        return $count;
    }

    private static function exportMedia()
    {
        global $wpdb;
        foreach (Bootstrap::$appSettings->wpbootstrap->posts as $postType => $arr) {
            foreach ($arr as $post) {
                $postId = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_type='%s' AND post_name = %s",
                    $postType,
                    $post
                ));
                $media = get_attached_media('', $postId);
                foreach ($media as $item) {
                    $itemMeta = wp_get_attachment_metadata($item->ID, true);
                    $item->meta = $itemMeta;
                    $dir = BASEPATH.'/bootstrap/media/'.$item->post_name;
                    @mkdir($dir, 0777, true);
                    file_put_contents($dir.'/meta', serialize($item));
                    $src = self::$uploadDir['basedir'].'/'.$itemMeta['file'];
                    $trg = $dir.'/'.basename($itemMeta['file']);
                    copy($src, $trg);
                }
            }
        }
    }

    private static function exportMenus()
    {
        foreach (Bootstrap::$appSettings->wpbootstrap->menus as $menu => $locations) {
            wp_set_current_user(1);
            $loggedInmenuItems = wp_get_nav_menu_items($menu);
            wp_set_current_user(0);
            $notloggedInmenuItems = wp_get_nav_menu_items($menu);
            $menuItems = array_merge($loggedInmenuItems, $notloggedInmenuItems);
            $menuItems = Bootstrap::uniqueObjectArray($menuItems, 'ID');

            $dir = BASEPATH.'/bootstrap/menus/'.$menu;
            array_map('unlink', glob("$dir/*"));
            @mkdir($dir, 0777, true);

            foreach ($menuItems as $menuItem) {
                $obj = get_post($menuItem->ID);
                $obj->post_meta = get_post_meta($obj->ID);

		        switch($obj->post_meta['_menu_item_type'][0]) {
			        case 'post_type':
                        $postType = $obj->post_meta['_menu_item_object'][0];
                        $postId = $obj->post_meta['_menu_item_object_id'][0];
                        $objPost = get_post($postId);
                        self::addPost($postType, $objPost->post_name);
				        break;
			        case 'taxonomy':
				        $id = $obj->post_meta['_menu_item_object_id'][0];
				        $taxonomy = $obj->post_meta['_menu_item_object'][0];
				        $objTerm = get_term($id, $taxonomy);
                        self::addTerm($taxonomy, $objTerm->slug);
				        break;
		        }
                Resolver::fieldSearchReplace($obj, self::$baseUrl, Bootstrap::NETURALURL);

                $file = $dir.'/'.$menuItem->post_name;
                file_put_contents($file, serialize($obj));
            }
        }
    }

    private static function exportTaxonomies()
    {
        $count = 0;
        foreach(self::$taxonomies as $taxonomy => &$terms) {
            foreach($terms as &$term) {
                if($term->done == true) {
                    continue;
                }

                $objTerm = get_term_by('slug', $term->slug, $taxonomy);
                $file = BASEPATH."/bootstrap/taxonomies/$taxonomy/{$term->slug}";
                @mkdir(dirname($file), 0777, true);
                file_put_contents($file, serialize($objTerm));

                if($objTerm->parent) {
                    $parentTerm = get_term_by('it', $objTerm->parent, $taxonomy);
                    self::addTerm($taxonomy, $parentTerm->slug);
                }
                $term->done = true;
                $count++;
            }
        }
    
        return $count;
    }

    private static function addTerm($taxonomy, $slug) {
        if(!isset(self::$taxonomies->$taxonomy)) {
            self::$taxonomies->$taxonomy = array();
        }
        foreach(self::$taxonomies->$taxonomy as $term) {
            if($term->slug == $slug) {
                return;
            }
        }

        $newTerm = new \stdClass();
        $newTerm->slug = $slug;
        $newTerm->done = false;
        array_push(self::$taxonomies->$taxonomy, $newTerm);
    }
    
    private static function addPost($postType, $slug) {
        if(!isset(self::$posts->$postType)) {
            self::$posts->$postType = array();
        }
        foreach(self::$posts->$postType as $post) {
            if($post->slug == $slug) {
                return;
            }
        }

        $newPost = new \stdClass();
        $newPost->slug = $post;
        $newPost->done = false;
        array_push(self::$posts->$postType, $newPost);
    }
}
