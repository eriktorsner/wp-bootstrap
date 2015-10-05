<?php
namespace Wpbootstrap;

class Import
{
    public static $posts;
    public static $baseUrl;
    public static $uploadDir;

    private static $metaReferenceNames = [
        '_thumbnail_id',
    ];
    private static $postReferenceNames = [
    ];

    public static function import($e)
    {
        Bootstrap::init($e);
        require_once Bootstrap::$localSettings->wppath."/wp-load.php";
        require_once Bootstrap::$localSettings->wppath."/wp-admin/includes/image.php";

        self::$baseUrl = get_option('siteurl');
        self::$uploadDir = wp_upload_dir();

        self::importSettings();
        self::importContent();
        self::resolveReferences();
    }

    private static function importSettings()
    {
        $wpcmd = Bootstrap::getWpCommand();

        $cmd = $wpcmd.'config pull wpbootstrap';
        exec($cmd);

        $src = BASEPATH.'/bootstrap/config/wpbootstrap.json';
        $trg = Bootstrap::$localSettings->wppath.'/wp-content/config/wpbootstrap.json';
        @mkdir(dirname($trg), 0777, true);
        copy($src, $trg);
    }

    private static function importContent()
    {
        self::$posts = new Pushposts();
        $menus = new Pushmenus();
    }

    private static function resolveReferences()
    {
        // iterate metadata on all our managed posts and menuitems
        // and look out for stuff that might be a post reference
        // _thumbnail_id for instance...

        foreach (self::$metaReferenceNames as $refName) {
            foreach (self::$posts->posts as $post) {
                if (isset($post->post->post_meta[$refName])) {
                    foreach ($post->post->post_meta[$refName] as $item) {
                        $newId = self::$posts->findTargetPostId($item);
                        update_post_meta($post->id, $refName, $newId, $item);
                    }
                }
            }
        }
        foreach (self::$postReferenceNames as $refName) {
            foreach (self::$posts->posts as $post) {
                if (isset($post->post->$refName)) {
                    $newId = self::$posts->findTargetPostId($post->post->$refName);
                    $args = [
                        'ID' => $post->id,
                        $refName => $newId,
                    ];
                    wp_update_post($args);
                }
            }
        }
    }
}
