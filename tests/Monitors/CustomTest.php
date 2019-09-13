<?php

namespace Eyewitness\Eye\Test\Monitors;

use Mockery;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Statuses;
use Eyewitness\Eye\Monitors\Custom;
use Eyewitness\Eye\Repo\History\Custom as History;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Messages\Custom\Passed;
use Eyewitness\Eye\Notifications\Messages\Custom\Failed;

class CustomTest extends TestCase
{
    protected $custom;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->notifier = Mockery::mock(Notifier::class);
        $this->app->instance(Notifier::class, $this->notifier);

        $this->custom = resolve(MyWitness::class);
    }

    public function test_safe_name()
    {
        $this->assertEquals('eyewitness_eye_test_monitors_mywitness', $this->custom->getSafeName());
    }

    public function test_history_empty()
    {
        $this->assertEmpty($this->custom->history());
    }

    public function test_history_loads()
    {
        factory(History::class)->create(['meta' => $this->custom->getSafeName()]);
        factory(History::class)->create(['meta' => $this->custom->getSafeName()]);

        $this->assertCount(2, $this->custom->history());
    }

    public function test_history_saves()
    {
        $this->custom->overrideValue(53);
        $this->custom->saveHistory(true);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'custom',
                                                                    'meta' => $this->custom->getSafeName(),
                                                                    'value' => 53,
                                                                    'record' => json_encode(['status' => true])]);
    }

    public function test_check_health_now_sick_sends_alert()
    {
        factory(Statuses::class)->create(['monitor' => 'custom_'.$this->custom->getSafeName(),
                                          'healthy' => 1]);

        $this->notifier->shouldReceive('alert')->with(Passed::class)->never();
        $this->notifier->shouldReceive('alert')->with(Failed::class)->once();

        $this->custom->checkHealth(false);

        $this->assertTrue($this->custom->failed);
        $this->assertFalse($this->custom->recovered);
    }

    public function test_check_health_second_failure_does_not_send_alert()
    {
        factory(Statuses::class)->create(['monitor' => 'custom_'.$this->custom->getSafeName(),
                                          'healthy' => 0]);

        $this->notifier->shouldReceive('alert')->with(Passed::class)->never();
        $this->notifier->shouldReceive('alert')->with(Failed::class)->never();

        $this->custom->checkHealth(false);

        $this->assertFalse($this->custom->failed);
        $this->assertFalse($this->custom->recovered);
    }

    public function test_check_health_now_healthy_sends_alert()
    {
        factory(Statuses::class)->create(['monitor' => 'custom_'.$this->custom->getSafeName(),
                                          'healthy' => 0]);

        $this->notifier->shouldReceive('alert')->with(Passed::class)->once();
        $this->notifier->shouldReceive('alert')->with(Failed::class)->never();

        $this->custom->checkHealth(true);

        $this->assertFalse($this->custom->failed);
        $this->assertTrue($this->custom->recovered);
    }

    public function test_check_health_second_healthy_does_not_send_alert()
    {
        factory(Statuses::class)->create(['monitor' => 'custom_'.$this->custom->getSafeName(),
                                          'healthy' => 1]);

        $this->notifier->shouldReceive('alert')->with(Passed::class)->never();
        $this->notifier->shouldReceive('alert')->with(Failed::class)->never();

        $this->custom->checkHealth(true);

        $this->assertFalse($this->custom->failed);
        $this->assertFalse($this->custom->recovered);
    }
}


class MyWitness extends Custom
{
    public $recovered = false;
    public $failed = false;

    public function run()
    {
        //
    }

    public function recovering()
    {
        $this->recovered = true;
    }

    public function failing()
    {
        $this->failed = true;
    }

    public function overrideValue($value)
    {
        $this->value = $value;
    }
}
