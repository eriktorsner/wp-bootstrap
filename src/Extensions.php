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
        $container = Container::getInstance();
        $appSettings = $container->getAppSettings();
        $log = $container->getLog();

        if (isset($appSettings->extensions) && is_array($appSettings->extensions)) {
            foreach ($appSettings->extensions as $class) {
                if (class_exists($class)) {
                    $this->extensions[] = new $class;
                } else {
                    $log->addError("Error: the class: $class not found");
                    die();
                }
            }
        }

        $this->callFunction('init');
    }

    private function callFunction($functionName)
    {
        $container = Container::getInstance();
        $log = $container->getLog();
        foreach ($this->extensions as $extension) {
            if (method_exists($extension, $functionName)) {
                call_user_func(array($extension, $functionName));
            }
        }
    }
}