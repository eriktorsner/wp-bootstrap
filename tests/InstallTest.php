<?php

namespace Wpbootstrap;

use Pimple\Container;

class BasicTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        \WP_Mock::setUp();
    }

    public function tearDown()
    {
        \WP_Mock::tearDown();
    }

    public function testInstallerWithoutEnv()
    {
        $app = new Container();
        $app->register(new Providers\DefaultObjectProvider());
        $app['environment'] = 'test';
        $app['path'] = '/dev/null/wordpress';

        $stub = $this->getMockCliWrapper();
        $stub->expects($this->once())
            ->method('log');
        $stub->expects($this->once())
            ->method('warning');
        $app['cli'] = $stub;

        WpCli::setApplication($app);

        $installer = $app['install'];
        $installer->run(null, null);
    }

    private function getMockCliWrapper()
    {
        $stub = $this->getMockBuilder('CliWrapper')
            ->setMethods(['get_runner', 'log', 'warning'])
            ->getMock();

        $stub->method('get_runner')->willReturn('foo');
        $stub->method('log')->willReturn(null);
        $stub->method('warning')->willReturn(null);
        return $stub;
    }
}