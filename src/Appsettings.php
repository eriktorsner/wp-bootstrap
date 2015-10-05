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

    private static function prettyPrint($json)
    {
        $result = '';
        $level = 0;
        $in_quotes = false;
        $in_escape = false;
        $ends_line_level = null;
        $json_length = strlen($json);

        for ($i = 0; $i < $json_length; $i++) {
            $char = $json[$i];
            $new_line_level = null;
            $post = "";
            if ($ends_line_level !== null) {
                $new_line_level = $ends_line_level;
                $ends_line_level = null;
            }
            if ($in_escape) {
                $in_escape = false;
            } elseif ($char === '"') {
                $in_quotes = !$in_quotes;
            } elseif (!$in_quotes) {
                switch ($char) {
                    case '}':
                    case ']':
                        $level--;
                        $ends_line_level = null;
                        $new_line_level = $level;
                        break;

                    case '{':
                    case '[':
                        $level++;
                        // intentonal
                    case ',':
                        $ends_line_level = $level;
                        break;

                    case ':':
                        $post = " ";
                        break;

                    case " ":
                    case "\t":
                    case "\n":
                    case "\r":
                        $char = "";
                        $ends_line_level = $new_line_level;
                        $new_line_level = null;
                        break;
                }
            } elseif ($char === '\\') {
                $in_escape = true;
            }
            if ($new_line_level !== null) {
                $result .= "\n".str_repeat("\t", $new_line_level);
            }
            $result .= $char.$post;
        }

        return $result;
    }
}
