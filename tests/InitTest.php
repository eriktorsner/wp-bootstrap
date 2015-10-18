<?php

namespace Wpbootstrap;

class InitTest extends \PHPUnit_Framework_TestCase
{
    public function testInit()
    {
        require_once __DIR__.'/../vendor/autoload.php';

        // ensure no previous files exists
        $local = './localsettings.json';
        $app = './appsettings.json';
        @unlink($local);
        @unlink($app);

        $this->assertFalse(file_exists($local));
        $this->assertFalse(file_exists($app));

        Initbootstrap::init();

        $this->assertTrue(file_exists($local));
        $this->assertTrue(file_exists($app));

        $this->assertJson(file_get_contents($local));
        $this->assertJson(file_get_contents($app));

        @unlink($local);
        @unlink($app);
    }

    public function testComposerInit()
    {
        $composerFile = './composer.json';
        $this->assertJson(file_get_contents($composerFile));

        Initbootstrap::initComposer();
        $composer = json_decode(file_get_contents($composerFile));

        $names = array('wp-bootstrap', 'wp-install', 'wp-setup', 'wp-import', 'wp-export', 'wp-init');
        foreach ($names as $name) {
            $this->assertTrue(isset($composer->scripts->$name));
        }
    }
}
