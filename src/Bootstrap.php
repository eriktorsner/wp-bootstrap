<?php
namespace Wpbootstrap;

class Bootstrap
{
    public static $localSettings;
    public static $appSettings;
    public static $fromComposer = false;
    public static $requireSettings = true;
    public static $argv = array();

    const NETURALURL = '@@__NEUTRAL__@@';

    public static function init($e)
    {
        global $argv;

        if (!defined('BASEPATH')) {
            define('BASEPATH', getcwd());
        }

        if (get_class($e) == 'Composer\Script\Event') {
            // We've been called from Composer
            self::$fromComposer = true;
            self::$argv = $e->getArguments();
        } else {
            self::$argv = $argv;
            array_shift(self::$argv);
            array_shift(self::$argv);
        }

        if (self::$requireSettings) {
            self::$localSettings = new Settings('local');
            self::$appSettings = new Settings('app');
            self::validateSettings();
        }
    }

    public static function bootstrap($e = null)
    {
        self::init($e);
        self::install();
        self::setup();
    }

    public static function install($e = null)
    {
        self::init($e);
        $wpcmd = self::getWpCommand();

        $cmd = $wpcmd.'core download --force';
        exec($cmd);

        $cmd = $wpcmd.sprintf(
            "core config --dbname=%s --dbuser=%s --dbpass=%s --quiet",
            self::$localSettings->dbname,
            self::$localSettings->dbuser,
            self::$localSettings->dbpass
        );
        exec($cmd);

        if (!isset(self::$localSettings->wpemail)) {
            self::$localSettings->wpemail = 'admin@local.dev';
        }
        $cmd = $wpcmd.sprintf(
            'core install --url=%s --title="%s" --admin_name=%s --admin_email="%s" --admin_password="%s"',
            self::$localSettings->url,
            self::$appSettings->title,
            self::$localSettings->wpuser,
            self::$localSettings->wpemail,
            self::$localSettings->wppass
        );
        exec($cmd);
    }

    public static function setup($e = null)
    {
        self::init($e);

        self::installPlugins();
        self::installThemes();
        self::applySettings();
    }

    public static function update($e = null)
    {
        self::init($e);
        $wpcmd = self::getWpCommand();

        if (count(self::$argv) == 0) {
            $cmd = $wpcmd.'plugin update --all';
            exec($cmd);
            $cmd = $wpcmd.'theme update --all';
            exec($cmd);
            $cmd = $wpcmd.'core update';
            exec($cmd);
        } elseif (self::$argv[0] == 'plugins') {
            if (count(self::$argv) == 1) {
                $cmd = $wpcmd.'plugin update --all';
                exec($cmd);
            }
        } elseif (self::$argv[0] == 'themes') {
            if (count(self::$argv) == 1) {
                $cmd = $wpcmd.'theme update --all';
                exec($cmd);
            }
        }
    }

    private static function installPlugins()
    {
        $wpcmd = self::getWpCommand();
        if (isset(self::$appSettings->plugins->standard)) {
            $standard = self::$appSettings->plugins->standard;
            foreach ($standard as $plugin) {
                $parts = explode(':', $plugin);
                if (count($parts) == 1 || self::isUrl($plugin)) {
                    $cmd = $wpcmd.'plugin install --activate '.$plugin;
                } else {
                    $cmd = $wpcmd.'plugin install --activate --version='.$parts[1].' '.$parts[0];
                }
                exec($cmd);
            }
        }

        if (isset(self::$appSettings->plugins->local)) {
            $local = self::$appSettings->plugins->local;
            foreach ($local as $plugin) {
                $cmd = sprintf("rm -f %s/wp-content/plugins/%s", self::$localSettings->wppath, $plugin);
                exec($cmd);

                $cmd = sprintf(
                    "ln -s %s/wp-content/plugins/%s %s/wp-content/plugins/%s",
                    BASEPATH,
                    $plugin,
                    self::$localSettings->wppath,
                    $plugin
                );
                exec($cmd);

                $cmd = $wpcmd.'plugin activate '.$plugin;
                exec($cmd);
            }
        }

        if (isset(self::$appSettings->plugins->localcopy)) {
            self::$local = $appSettings->plugins->localcopy;
            foreach ($local as $plugin) {
                $cmd = sprintf("rm -f %s/wp-content/plugins/%s", self::$localSettings->wppath, $plugin);
                exec($cmd);

                $cmd = sprintf(
                    "cp -a %s/wp-content/plugins/%s %s/wp-content/plugins/%s",
                    BASEPATH,
                    $plugin,
                    self::$localSettings->wppath,
                    $plugin
                );
                exec($cmd);

                $cmd = $wpcmd.'plugin activate '.$plugin;
                exec($cmd);
            }
        }
    }

    private static function installThemes()
    {
        $wpcmd = self::getWpCommand();
        if (isset(self::$appSettings->themes->standard)) {
            $standard = self::$appSettings->themes->standard;
            foreach ($standard as $theme) {
                $parts = explode(':', $theme);
                if (count($parts) == 1 || self::isUrl($theme)) {
                    $cmd = $wpcmd.'theme install '.$theme;
                } else {
                    $cmd = $wpcmd.'theme install --version='.$parts[1].' '.$parts[0];
                }
                exec($cmd);
            }
        }

        if (isset(self::$appSettings->themes->local)) {
            $local = self::$appSettings->themes->local;
            foreach ($local as $theme) {
                $cmd = sprintf("rm -f %s/wp-content/themes/%s", self::$localSettings->wppath, $theme);
                exec($cmd);

                $cmd = sprintf(
                    "ln -s %s/wp-content/themes/%s %s/wp-content/themes/%s",
                    BASEPATH,
                    $theme,
                    self::$localSettings->wppath,
                    $theme
                );
                exec($cmd);
            }
        }

        if (isset(self::$appSettings->themes->localcopy)) {
            $local = self::$appSettings->themes->localcopy;
            foreach ($local as $theme) {
                $cmd = sprintf("rm -f %s/wp-content/themes/%s", self::$localSettings->wppath, $theme);
                exec($cmd);

                $cmd = sprintf(
                    "cp -a %s/wp-content/themes/%s %s/wp-content/themes/%s",
                    BASEPATH,
                    $plugin,
                    self::$localSettings->wppath,
                    $plugin
                );
                exec($cmd);
            }
        }

        if (isset(self::$appSettings->themes->active)) {
            $cmd = $wpcmd.'theme activate '.self::$appSettings->themes->active;
            exec($cmd);
        }
    }

    private static function applySettings()
    {
        $wpcmd = self::getWpCommand();
        if (isset(self::$appSettings->settings)) {
            foreach (self::$appSettings->settings as $key => $value) {
                $cmd  = $wpcmd."option update $key ";
                $cmd .= '"'.$value.'"';
                exec($cmd);
            }
        }
    }

    private static function validateSettings()
    {
        $good = true;
        if (!self::$localSettings->isValid()) {
            echo "localsettings.json does not exist or contains invalid JSON\n";
            $good = false;
        }
        if (!self::$appSettings->isValid()) {
            echo "appsettings.json does not exist or contains invalid JSON\n";
            $good = false;
        }
        if (!$good) {
            echo "\nAt least one configuration file is missing or contains invalid JSON\n";
            echo "Consider running command wp-init to set up template setting files\n";
        }
    }

    public static function getWpCommand()
    {
        $wpcmd = 'wp --path='.self::$localSettings->wppath.' --allow-root ';

        return $wpcmd;
    }

    public static function isUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        } else {
            return true;
        }
    }

    public static function recursiveRemoveDirectory($directory)
    {
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                self::recursiveRemoveDirectory($file);
            } else {
                unlink($file);
            }
        }
        if (file_exists($directory)) {
            rmdir($directory);
        }
    }

    public static function uniqueObjectArray($array, $key)
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

    public static function prettyPrint($json)
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

    public static function includeWordPress()
    {
        $old = set_error_handler("\\Wpbootstrap\\Bootstrap::noError");
        require_once self::$localSettings->wppath."/wp-load.php";
        set_error_handler($old);
    }

    public static function noError($errno, $errstr, $errfile, $errline)
    {
    }
}
