<?php

namespace Wpbootstrap;

class Extensions
{
    /**
     * All extensions defined in Appsettings
     *
     * @var array
     */
    private $extensions = [];

    public function init()
    {
        $app = Bootstrap::getApplication();
        $setting = $app['settings'];
        $cli = $app['cli'];

        if (isset($setting['extensions']) && is_array($setting['extensions'])) {
            foreach ($setting['extensions'] as $class) {
                if (class_exists($class)) {
                    $this->extensions[] = new $class;
                } else {
                    $cli->error("Error: the class: $class not found");
                }
            }
        }

        $this->callFunction('init');
    }

    private function callFunction($functionName)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        foreach ($this->extensions as $extension) {
            if (method_exists($extension, $functionName)) {
                $cli->debug("Calling $functionName on extension ". get_class($extension));
                call_user_func(array($extension, $functionName));
            }
        }
    }
}