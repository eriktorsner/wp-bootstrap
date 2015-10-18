<?php

namespace Wpbootstrap;

class SettingsTest extends \PHPUnit_Framework_TestCase
{
    public function testReadSettings()
    {
        // ensure no previous files exists
        $local = './localsettings.json';
        $app = './appsettings.json';
        @unlink($local);
        @unlink($app);

        $localSettings = new Settings('local');
        $this->assertFalse($localSettings->isValid());

        $appSettings = new Settings('app');
        $this->assertFalse($appSettings->isValid());

        Initbootstrap::init();

        $localSettings = new Settings('local');
        $this->assertTrue($localSettings->isValid());
        $this->assertTrue(isset($localSettings->environment));
        $this->assertTrue(isset($localSettings->url));
        $this->assertTrue(isset($localSettings->dbhost));
        $this->assertTrue(isset($localSettings->dbname));
        $this->assertTrue(isset($localSettings->dbuser));
        $this->assertTrue(isset($localSettings->dbpass));
        $this->assertTrue(isset($localSettings->wpuser));
        $this->assertTrue(isset($localSettings->wppass));
        $this->assertTrue(isset($localSettings->wppath));

        $appSettings = new Settings('app');
        $this->assertTrue($appSettings->isValid());
        $this->assertTrue(isset($appSettings->title));
    }

    public function testTestmode()
    {
        $local = './localsettings.json';
        @unlink($local);

        Initbootstrap::init();
        $localSettings = new Settings('local');
        $localSettings->wppath_test = 'foobar';
        file_put_contents($local, $localSettings->toString());

        define('TESTMODE', true);
        $localSettings = new Settings('local');
        //print_r($localSettings);
        $this->assertEquals('foobar', $localSettings->wppath);
    }
}
