<?php
namespace Wpbootstrap;

class Appsettings
{
    public static function updateAppSettings($e = null)
    {
        Bootstrap::init($e);
        require_once Bootstrap::$localSettings->wppath."/wp-load.php";

        if (!isset(Bootstrap::$appSettings->wpbootstrap)) {
            Bootstrap::$appSettings->wpbootstrap = new \stdClass();
        }
        if (!isset(Bootstrap::$appSettings->wpbootstrap->posts)) {
            Bootstrap::$appSettings->wpbootstrap->posts = new \stdClass();
        }
        $args = [
            'meta_query' => [
                [
                    'key' => 'wpbootstrap_export',
                    'value' => 1,
                ],
            ],
            'posts_per_page' => -1,
            'post_type' => 'any',
        ];
        $posts = new \WP_Query($args);
        foreach ($posts->posts as $post) {
            $postType = $post->post_type;
            $slug = $post->post_name;
            if (!isset(Bootstrap::$appSettings->wpbootstrap->posts->$postType)) {
                Bootstrap::$appSettings->wpbootstrap->posts->$postType = [];
            }
            if (!in_array($slug, Bootstrap::$appSettings->wpbootstrap->posts->$postType)) {
                array_push(Bootstrap::$appSettings->wpbootstrap->posts->$postType, $slug);
            }
        }
        file_put_contents(BASEPATH.'/appsettings.json', self::prettyPrint(Bootstrap::$appSettings->toString()));
    }
}
