<?php

namespace Wpbootstrap;

/**
 * Class ExportBase
 * @package Wpbootstrap
 */
class ExportBase
{
    /**
     * @var Settings
     */
    protected $localSettings;

    /**
     * @var Settings
     */
    protected $appSettings;

    /**
     * @var ExportTaxonomies
     */
    protected $exportTaxonomies;

    /**
     * @var ExportMedia
     */
    protected $exportMedia;

    /**
     * @var ExtractMedia
     */
    protected $extractMedia;

    /**
     * @var ExportPosts
     */
    protected $exportPosts;

    /**
     * @var ExportSidebars
     */
    protected $exportSidebars;

    /**
     * @var \Monolog\Logger
     */
    protected $log;

    /**
     * @var Helpers
     */
    protected $helpers;

    /**
     * @var Utils
     */
    protected $utils;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * ExportBase constructor.
     */
    public function __construct()
    {
        $container = Container::getInstance();
        $this->localSettings = $container->getLocalSettings();
        $this->appSettings = $container->getAppSettings();

        $this->log = $container->getLog();
        $this->helpers = $container->getHelpers();
        $this->utils = $container->getUtils();

        $this->utils->includeWordPress();
        $this->baseUrl = get_option('siteurl');

    }
}
