<?php
namespace Wpbootstrap;

class Bootstrap
{
    public $localSettings;
    public $appSettings;
    public $fromComposer = false;
    public $requireSettings = true;
    public $argv = array();

    private static $self = false;

    const NETURALURL = '@@__NEUTRAL__@@';

    public static function getInstance()
    {
        if (!self::$self) {
            self::$self = new Bootstrap();
        }

        return self::$self;
    }

    public function init($e)
    {
        global $argv;

        if (!defined('BASEPATH')) {
            define('BASEPATH', getcwd());
        }

        if (get_class($e) == 'Composer\Script\Event') {
            // We've been called from Composer
            $this->fromComposer = true;
            $this->argv = $e->getArguments();
        } else {
            $this->argv = $argv;
            array_shift($this->argv);
            array_shift($this->argv);
        }

        if ($this->requireSettings) {
            $this->localSettings = new Settings('local');
            $this->appSettings = new Settings('app');
            $this->validateSettings();
        }
    }

    public function bootstrap($e = null)
    {
        $this->init($e);
        $this->install();
        $this->setup();
    }

    public function install($e = null)
    {
        $this->init($e);
        $wpcmd = $this->getWpCommand();

        $cmd = $wpcmd.'core download --force';
        exec($cmd);

        $cmd = $wpcmd.sprintf(
            "core config --dbname=%s --dbuser=%s --dbpass=%s --quiet",
            $this->localSettings->dbname,
            $this->localSettings->dbuser,
            $this->localSettings->dbpass
        );
        exec($cmd);

        if (!isset($this->localSettings->wpemail)) {
            $this->localSettings->wpemail = 'admin@local.dev';
        }
        $cmd = $wpcmd.sprintf(
            'core install --url=%s --title="%s" --admin_name=%s --admin_email="%s" --admin_password="%s"',
            $this->localSettings->url,
            $this->appSettings->title,
            $this->localSettings->wpuser,
            $this->localSettings->wpemail,
            $this->localSettings->wppass
        );
        exec($cmd);
    }

    public function setup($e = null)
    {
        $this->init($e);

        $this->installPlugins();
        $this->installThemes();
        $this->applySettings();
    }

    public function update($e = null)
    {
        $this->init($e);
        $wpcmd = $this->getWpCommand();

        if (count($this->argv) == 0) {
            $cmd = $wpcmd.'plugin update --all';
            exec($cmd);
            $cmd = $wpcmd.'theme update --all';
            exec($cmd);
            $cmd = $wpcmd.'core update';
            exec($cmd);
        } elseif ($this->argv[0] == 'plugins') {
            if (count($this->argv) == 1) {
                $cmd = $wpcmd.'plugin update --all';
                exec($cmd);
            }
        } elseif ($this->argv[0] == 'themes') {
            if (count($this->argv) == 1) {
                $cmd = $wpcmd.'theme update --all';
                exec($cmd);
            }
        }
    }

    private function installPlugins()
    {
        $wpcmd = $this->getWpCommand();
        if (isset($this->appSettings->plugins->standard)) {
            $standard = $this->appSettings->plugins->standard;
            foreach ($standard as $plugin) {
                $parts = explode(':', $plugin);
                if (count($parts) == 1 || $this->isUrl($plugin)) {
                    $cmd = $wpcmd.'plugin install --activate '.$plugin;
                } else {
                    $cmd = $wpcmd.'plugin install --activate --version='.$parts[1].' '.$parts[0];
                }
                exec($cmd);
            }
        }

        if (isset($this->appSettings->plugins->local)) {
            $local = $this->appSettings->plugins->local;
            foreach ($local as $plugin) {
                $cmd = sprintf("rm -f %s/wp-content/plugins/%s", $this->localSettings->wppath, $plugin);
                exec($cmd);

                $cmd = sprintf(
                    "ln -s %s/wp-content/plugins/%s %s/wp-content/plugins/%s",
                    BASEPATH,
                    $plugin,
                    $this->localSettings->wppath,
                    $plugin
                );
                exec($cmd);

                $cmd = $wpcmd.'plugin activate '.$plugin;
                exec($cmd);
            }
        }

        if (isset($this->appSettings->plugins->localcopy)) {
            $this->local = $appSettings->plugins->localcopy;
            foreach ($local as $plugin) {
                $cmd = sprintf("rm -f %s/wp-content/plugins/%s", $this->localSettings->wppath, $plugin);
                exec($cmd);

                $cmd = sprintf(
                    "cp -a %s/wp-content/plugins/%s %s/wp-content/plugins/%s",
                    BASEPATH,
                    $plugin,
                    $this->localSettings->wppath,
                    $plugin
                );
                exec($cmd);

                $cmd = $wpcmd.'plugin activate '.$plugin;
                exec($cmd);
            }
        }
    }

    private function installThemes()
    {
        $wpcmd = $this->getWpCommand();
        if (isset($this->appSettings->themes->standard)) {
            $standard = $this->appSettings->themes->standard;
            foreach ($standard as $theme) {
                $parts = explode(':', $theme);
                if (count($parts) == 1 || $this->isUrl($theme)) {
                    $cmd = $wpcmd.'theme install '.$theme;
                } else {
                    $cmd = $wpcmd.'theme install --version='.$parts[1].' '.$parts[0];
                }
                exec($cmd);
            }
        }

        if (isset($this->appSettings->themes->local)) {
            $local = $this->appSettings->themes->local;
            foreach ($local as $theme) {
                $cmd = sprintf("rm -f %s/wp-content/themes/%s", $this->localSettings->wppath, $theme);
                exec($cmd);

                $cmd = sprintf(
                    "ln -s %s/wp-content/themes/%s %s/wp-content/themes/%s",
                    BASEPATH,
                    $theme,
                    $this->localSettings->wppath,
                    $theme
                );
                exec($cmd);
            }
        }

        if (isset($this->appSettings->themes->localcopy)) {
            $local = $this->appSettings->themes->localcopy;
            foreach ($local as $theme) {
                $cmd = sprintf("rm -f %s/wp-content/themes/%s", $this->localSettings->wppath, $theme);
                exec($cmd);

                $cmd = sprintf(
                    "cp -a %s/wp-content/themes/%s %s/wp-content/themes/%s",
                    BASEPATH,
                    $plugin,
                    $this->localSettings->wppath,
                    $plugin
                );
                exec($cmd);
            }
        }

        if (isset($this->appSettings->themes->active)) {
            $cmd = $wpcmd.'theme activate '.$this->appSettings->themes->active;
            exec($cmd);
        }
    }

    private function applySettings()
    {
        $wpcmd = $this->getWpCommand();
        if (isset($this->appSettings->settings)) {
            foreach ($this->appSettings->settings as $key => $value) {
                $cmd  = $wpcmd."option update $key ";
                $cmd .= '"'.$value.'"';
                exec($cmd);
            }
        }
    }

    private function validateSettings()
    {
        $good = true;
        if (!$this->localSettings->isValid()) {
            echo "localsettings.json does not exist or contains invalid JSON\n";
            $good = false;
        }
        if (!$this->appSettings->isValid()) {
            echo "appsettings.json does not exist or contains invalid JSON\n";
            $good = false;
        }
        if (!$good) {
            echo "\nAt least one configuration file is missing or contains invalid JSON\n";
            echo "Consider running command wp-init to set up template setting files\n";
        }
    }

    public function getWpCommand()
    {
        $wpcmd = 'wp --path='.$this->localSettings->wppath.' --allow-root ';

        return $wpcmd;
    }

    public function isUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        } else {
            return true;
        }
    }

    public function recursiveRemoveDirectory($directory)
    {
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                $this->recursiveRemoveDirectory($file);
            } else {
                unlink($file);
            }
        }
        if (file_exists($directory)) {
            rmdir($directory);
        }
    }

    public function uniqueObjectArray($array, $key)
    {
        $temp_array = array();
        $i = 0;
        $key_array = array();

        foreach ($array as $val) {
            if (!in_array($val->$key, $key_array)) {
                $key_array[$i] = $val->$key;
                $temp_array[$i] = $val;
            }
            $i++;
        }

        return $temp_array;
    }

    public function prettyPrint($json)
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

    public function includeWordPress()
    {
        $old = set_error_handler("\\Wpbootstrap\\Bootstrap::noError");
        require_once $this->localSettings->wppath."/wp-load.php";
        set_error_handler($old);
    }

    public static function noError($errno, $errstr, $errfile, $errline)
    {
    }
}
