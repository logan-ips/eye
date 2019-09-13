<?php

namespace Eyewitness\Eye\Test\Monitors;

use Mockery;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Statuses;
use Eyewitness\Eye\Monitors\Debug;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Messages\Debug\Enabled;
use Eyewitness\Eye\Notifications\Messages\Debug\Disabled;

class DebugTest extends TestCase
{
    protected $notifier;

    protected $debug;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->notifier = Mockery::mock(Notifier::class);
        $this->app->instance(Notifier::class, $this->notifier);

        $this->debug = resolve(Debug::class);
    }

    public function test_handles_local_environment()
    {
        app()->env = 'local';
        config(['app.debug' => true]);

        $this->notifier->shouldReceive('alert')->never();

        $this->debug->poll();
    }

    public function test_handles_first_status_healthy()
    {
        app()->env = 'production';
        config(['app.debug' => false]);

        $this->notifier->shouldReceive('alert')->never();

        $this->debug->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'debug',
                                                            'healthy' => 1]);
    }

    public function test_handles_first_status_sick()
    {
        app()->env = 'production';
        config(['app.debug' => true]);

        $this->notifier->shouldReceive('alert')->never();

        $this->debug->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'debug',
                                                            'healthy' => 0]);
    }

    public function test_handles_switch_to_healthy_status()
    {
        factory(Statuses::class)->create(['monitor' => 'debug', 'healthy' => 0]);

        app()->env = 'production';
        config(['app.debug' => false]);

        $this->notifier->shouldReceive('alert')->with(Disabled::class)->once();
        $this->notifier->shouldReceive('alert')->with(Enabled::class)->never();

        $this->debug->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'debug',
                                                            'healthy' => 1]);
    }

    public function test_handles_switch_to_sick_status()
    {
        factory(Statuses::class)->create(['monitor' => 'debug', 'healthy' => 1]);

        app()->env = 'production';
        config(['app.debug' => true]);

        $this->notifier->shouldReceive('alert')->with(Disabled::class)->never();
        $this->notifier->shouldReceive('alert')->with(Enabled::class)->once();

        $this->debug->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'debug',
                                                            'healthy' => 0]);
    }
}
