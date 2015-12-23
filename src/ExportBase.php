<?php

namespace Wpbootstrap;

class ExportBase
{
    protected $localSettings;
    protected $appSettings;

    protected $exportTaxonomies;
    protected $exportMedia;
    protected $extractMedia;
    protected $exportPosts;
    protected $exportSidebars;

    protected $log;
    protected $helpers;
    protected $utils;
    protected $baseUrl;

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
