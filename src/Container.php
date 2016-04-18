<?php

namespace Wpbootstrap;


class Container
{
    /**
     * @var \Pimple\Container
     */
    public $app;

    /**
     * @var bool | Container
     */
    private static $self = false;

    private function __construct($config = null)
    {
        $pimpleContainer = new \Pimple\Container;
    }

    /**
     * Get the global instance
     *
     * @param $config
     *
     * @return \Wpbootstrap\Container
     */
    public static function getInstance($config = null)
    {
        if (!self::$self) {
            self::$self = new self($config);
        }
        return self::$self;
    }



}