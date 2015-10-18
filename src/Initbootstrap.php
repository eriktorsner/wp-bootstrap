<?php
namespace Wpbootstrap;

class Initbootstrap
{
    private static $scriptMaps = array(
        "wp-bootstrap"     => "Wpbootstrap\\Bootstrap::bootstrap",
        "wp-install"       => "Wpbootstrap\\Bootstrap::install",
        "wp-setup"         => "Wpbootstrap\\Bootstrap::setup",
        "wp-update"        => "Wpbootstrap\\Bootstrap::update",
        "wp-export"        => "Wpbootstrap\\Export::export",
        "wp-import"        => "Wpbootstrap\\Import::import",
        "wp-pullsettings"  => "Wpbootstrap\\Initbootstrap::updateAppSettings",
        "wp-init"          => "Wpbootstrap\\Initbootstrap::init",
        "wp-init-composer" => "Wpbootstrap\\Initbootstrap::initComposer",
    );

    public static function init($e = null)
    {
        if (!file_exists(BASEPATH.'/appsettings.json')) {
            $appSettings = new \stdClass();
            $appSettings->title = "[title]";
            file_put_contents(BASEPATH.'/appsettings.json', Bootstrap::prettyPrint(json_encode($appSettings)));
        }
        if (!file_exists(BASEPATH.'/§.json')) {
            $localSettings = new \stdClass();
            $localSettings->environment = '[environment]';
            $localSettings->url    = '[url]';
            $localSettings->dbhost = '[dbhost]';
            $localSettings->dbname = '[dbname]';
            $localSettings->dbuser = '[dbuser]';
            $localSettings->wpuser = '[wpuser]';
            $localSettings->wppass = '[wppass]';
            $localSettings->wppath = '[wppath]';
            file_put_contents(BASEPATH.'/localsettings.json', Bootstrap::prettyPrint(json_encode($localSettings)));
        }
    }

    public static function initComposer($e = null)
    {
        if (!file_exists(BASEPATH.'/composer.json')) {
            die("Error: composer.json not found in current folder\n");
        }
        $composer = json_decode(file_get_contents(BASEPATH.'/composer.json'));
        if (!$composer) {
            die("Error: composer.json does not contain vaild JSON\n");
        }

        if (!isset($composer->scripts)) {
            $composer->scripts = new \stdClass();
        }

        foreach (self::$scriptMaps as $key => $script) {
            $composer->scripts->$key = $script;
        }

        file_put_contents(BASEPATH.'/composer.json', Bootstrap::prettyPrint(json_encode($composer)));
    }

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
        file_put_contents(BASEPATH.'/appsettings.json', Bootstrap::prettyPrint(Bootstrap::$appSettings->toString()));
    }
}
