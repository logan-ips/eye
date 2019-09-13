<?php

namespace Eyewitness\Eye\Test;

use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Monitors\Custom;

class EyeTest extends TestCase
{
    protected $eye;

    public function setUp(): void
    {
        parent::setUp();

        $this->eye = new Eye;
    }

    public function test_eye_is_constructed_correctly()
    {
        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->assertInstanceOf(\Eyewitness\Eye\Notifications\Notifier::class, $this->eye->notifier());
        $this->assertInstanceOf(\Eyewitness\Eye\Monitors\Application::class, $this->eye->application());
        $this->assertInstanceOf(\Eyewitness\Eye\Monitors\Scheduler::class, $this->eye->scheduler());
        $this->assertInstanceOf(\Eyewitness\Eye\Monitors\Database::class, $this->eye->database());
        $this->assertInstanceOf(\Eyewitness\Eye\Monitors\Composer::class, $this->eye->composer());
        $this->assertInstanceOf(\Eyewitness\Eye\Monitors\Queue::class, $this->eye->queue());
        $this->assertInstanceOf(\Eyewitness\Eye\Monitors\Debug::class, $this->eye->debug());
        $this->assertInstanceOf(\Eyewitness\Eye\Monitors\Ssl::class, $this->eye->ssl());
        $this->assertInstanceOf(\Eyewitness\Eye\Monitors\Dns::class, $this->eye->dns());
        $this->assertInstanceOf(\Eyewitness\Eye\Status::class, $this->eye->status());
        $this->assertInstanceOf(\Eyewitness\Eye\Logger::class, $this->eye->logger());
        $this->assertInstanceOf(\Eyewitness\Eye\Api::class, $this->eye->api());
    }

    public function test_config_correctly_detects_if_settings_are_valid()
    {
        $this->app['config']->set('eyewitness.app_token', '');
        $this->app['config']->set('eyewitness.secret_key', '');
        $this->assertFalse($this->eye->checkConfig());

        $this->app['config']->set('eyewitness.app_token', null);
        $this->app['config']->set('eyewitness.secret_key', null);
        $this->assertFalse($this->eye->checkConfig());

        $this->app['config']->set('eyewitness.app_token', 'blah');
        $this->app['config']->set('eyewitness.secret_key', 'blah');
        $this->assertTrue($this->eye->checkConfig());
    }

    public function test_laravel_version_checks()
    {
        $this->assertFalse(Eye::laravelVersionIs('>=', '5.5.0', '5.4.3'));
        $this->assertFalse(Eye::laravelVersionIs('>=', '5.4.4', '5.4.3'));
        $this->assertTrue(Eye::laravelVersionIs('>=', '5.4.3', '5.4.3'));
        $this->assertTrue(Eye::laravelVersionIs('>=', '5.3.0', '5.4.3'));

        $this->assertFalse(Eye::laravelVersionIs('>=', '5.5.1', '5.4.3'));
        $this->assertTrue(Eye::laravelVersionIs('>=', '4.2.4', '5.4.3'));
        $this->assertTrue(Eye::laravelVersionIs('>=', '5.4.10', '5.4.30'));

        $this->assertFalse(Eye::laravelVersionIs('>=', '5.1.41', '5.1.40 (LTS)'));
        $this->assertTrue(Eye::laravelVersionIs('>=', '5.1.40', '5.1.40 (LTS)'));
        $this->assertTrue(Eye::laravelVersionIs('>=', '5.1.39', '5.1.40 (LTS)'));
        $this->assertTrue(Eye::laravelVersionIs('>=', '5.0.30', '5.1.40 (LTS)'));
        $this->assertTrue(Eye::laravelVersionIs('>=', '5.0.50', '5.1.40 (LTS)'));

        $this->assertFalse(Eye::laravelVersionIs('>=', '5.2.30', '5.1.40 (LTS)'));
        $this->assertFalse(Eye::laravelVersionIs('>=', '6.0.30', '5.1.40 (LTS)'));
        $this->assertFalse(Eye::laravelVersionIs('>=', '6.0.50', '5.1.40 (LTS)'));
    }

    public function test_eye_loads_custom_witnesses()
    {
        config(['eyewitness.custom_witnesses' => [\Eyewitness\Eye\Test\MyMock::class]]);

        $list = $this->eye->getCustomWitnesses();

        $this->assertCount(1, $list);
        $this->assertInstanceOf(MyMock::class, $list[0]);

        config(['eyewitness.custom_witnesses' => [\Eyewitness\Eye\Test\MyMock::class,
                                                  \Eyewitness\Eye\Test\MyMockAgain::class,
        ]]);

        $list = $this->eye->getCustomWitnesses();

        $this->assertCount(2, $list);
        $this->assertInstanceOf(MyMock::class, $list[0]);
        $this->assertInstanceOf(MyMockAgain::class, $list[1]);
    }

    public function test_eye_filters_custom_witnesses()
    {
        config(['eyewitness.custom_witnesses' => [\Eyewitness\Eye\Test\MyMock::class,
                                                  \Eyewitness\Eye\Test\MyMockAgain::class,
        ]]);

        $list = $this->eye->getCustomWitnesses(true);

        $this->assertCount(1, $list);
        $this->assertInstanceOf(MyMockAgain::class, $list[0]);
    }
}


class MyMock extends Custom
{
    public $schedule = '1 1 1 1 1';

    public function run()
    {
        //
    }
}


class MyMockAgain extends Custom
{
    public $schedule = '* * * * *';

    public function run()
    {
        //
    }
}
