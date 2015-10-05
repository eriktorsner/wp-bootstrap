<?php
namespace Wpbootstrap;

class Export
{
    private static $baseUrl;
    private static $uploadDir;

    public static function export($e)
    {
        Bootstrap::init($e);
        require_once Bootstrap::$localSettings->wppath."/wp-load.php";

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
        @mkdir(dirname($trg), 0777, true);
        copy($src, $trg);
    }

    private static function exportContent()
    {
        $base = BASEPATH.'/bootstrap/config';
        Bootstrap::recursiveRemoveDirectory($base.'/posts');
        Bootstrap::recursiveRemoveDirectory($base.'/media');
        Bootstrap::recursiveRemoveDirectory($base.'/menus');

        self::exportPosts();
        self::exportMedia();
        self::exportMenus();
    }

    private static function exportPosts()
    {
        global $wpdb;

        foreach (Bootstrap::$appSettings->wpbootstrap->posts as $postType => $arr) {
            foreach ($arr as $post) {
                $postId = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s",
                    $postType,
                    $post
                ));
                $post = get_post($postId);

                $meta = get_post_meta($post->ID);
                $post->post_meta = $meta;
                Resolver::fieldSearchReplace($post, self::$baseUrl, Bootstrap::NETURALURL);

                $file = BASEPATH."/bootstrap/posts/{$post->post_type}/{$post->post_name}";
                @mkdir(dirname($file), 0777, true);
                file_put_contents($file, serialize($post));
            }
        }
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
                Resolver::fieldSearchReplace($obj, self::$baseUrl, Bootstrap::NETURALURL);

                $file = $dir.'/'.$menuItem->post_name;
                file_put_contents($file, serialize($obj));
            }
        }
    }
}
