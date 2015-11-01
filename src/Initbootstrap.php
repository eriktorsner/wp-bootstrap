<?php
namespace Wpbootstrap;

class Initbootstrap
{
    private $bootstrap;
    private static $self = false;

    public function getInstance()
    {
        if (!self::$self) {
            self::$self = new Initbootstrap();
        }

        return self::$self;
    }

    public function __construct()
    {
        $this->bootstrap = Bootstrap::getInstance();
    }

    private $scriptMaps = array(
        "wp-bootstrap"     => "vendor/bin/wpbootstrap wp-bootstrap",
        "wp-install"       => "vendor/bin/wpbootstrap wp-install",
        "wp-setup"         => "vendor/bin/wpbootstrap wp-setup",
        "wp-update"        => "vendor/bin/wpbootstrap wp-update",
        "wp-export"        => "vendor/bin/wpbootstrap wp-export",
        "wp-import"        => "vendor/bin/wpbootstrap wp-import",
        "wp-pullsettings"  => "vendor/bin/wpbootstrap wp-updateAppSettings",
        "wp-init"          => "vendor/bin/wpbootstrap wp-init",
        "wp-init-composer" => "vendor/bin/wpbootstrap wp-initComposer",
    );

    public function init($e = null)
    {
        $this->bootstrap->requireSettings = false;
        $this->bootstrap->init($e);

        if (!file_exists(BASEPATH.'/appsettings.json')) {
            $appSettings = new \stdClass();
            $appSettings->title = "[title]";
            file_put_contents(BASEPATH.'/appsettings.json', $this->bootstrap->prettyPrint(json_encode($appSettings)));
        }
        if (!file_exists(BASEPATH.'/localsettings.json')) {
            $localSettings = new \stdClass();
            $localSettings->environment = '[environment]';
            $localSettings->url    = '[url]';
            $localSettings->dbhost = '[dbhost]';
            $localSettings->dbname = '[dbname]';
            $localSettings->dbuser = '[dbuser]';
            $localSettings->dbpass = '[dbpass]';
            $localSettings->wpuser = '[wpuser]';
            $localSettings->wppass = '[wppass]';
            $localSettings->wppath = '[wppath]';
            file_put_contents(BASEPATH.'/localsettings.json', $this->bootstrap->prettyPrint(json_encode($localSettings)));
        }
    }

    public function initComposer($e = null)
    {
        $this->bootstrap->requireSettings = false;
        $this->bootstrap->init($e);
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

        foreach ($this->scriptMaps as $key => $script) {
            $composer->scripts->$key = $script;
        }

        file_put_contents(BASEPATH.'/composer.json', $this->bootstrap->prettyPrint(json_encode($composer)));
    }

    public function updateAppSettings($e = null)
    {
        $this->bootstrap->init($e);
        $this->bootstrap->includeWordPress();

        if (!isset($this->bootstrap->appSettings->wpbootstrap)) {
            $this->bootstrap->appSettings->wpbootstrap = new \stdClass();
        }
        if (!isset($this->bootstrap->appSettings->wpbootstrap->posts)) {
            $this->bootstrap->appSettings->wpbootstrap->posts = new \stdClass();
        }
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'wpbootstrap_export',
                    'value' => 1,
                ),
            ),
            'posts_per_page' => -1,
            'post_type' => 'any',
        );
        $posts = new \WP_Query($args);
        foreach ($posts->posts as $post) {
            $postType = $post->post_type;
            $slug = $post->post_name;
            if (!isset($this->bootstrap->appSettings->wpbootstrap->posts->$postType)) {
                $this->bootstrap->appSettings->wpbootstrap->posts->$postType = array();
            }
            if (!in_array($slug, $this->bootstrap->appSettings->wpbootstrap->posts->$postType)) {
                array_push($this->bootstrap->appSettings->wpbootstrap->posts->$postType, $slug);
            }
        }
        file_put_contents(BASEPATH.'/appsettings.json', $this->bootstrap->prettyPrint($this->bootstrap->appSettings->toString()));
    }
}
