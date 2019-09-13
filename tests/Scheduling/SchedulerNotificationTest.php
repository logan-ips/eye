<?php

namespace Eyewitness\Eye\Test\Scheduling;

use Mockery;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Scheduler;
use Eyewitness\Eye\Scheduling\BaseEvent;
use Eyewitness\Eye\Repo\History\Scheduler as History;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Fast;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Slow;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Error;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Working;

class SchedulerNotificationTest extends TestCase
{
    protected $notifier;

    protected $base;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->notifier = Mockery::mock(Notifier::class);
        $this->app->instance(Notifier::class, $this->notifier);

        $this->base = new OtherBaseEventMockClass;
        $this->base->scheduler = factory(Scheduler::class)->create();
    }

    public function test_handles_good_exit()
    {
        $this->base->exitcode = 0;
        $this->base->scheduler->alert_on_fail = 1;

        $this->notifier->shouldReceive('alert')->never();

        $this->base->testHandleNotifications();

        $this->assertDatabaseMissing('eyewitness_io_notification_history', [
            'type' => 'Scheduler',
        ]);
    }

    public function test_captures_failed_event_finish()
    {
        $this->base->exitcode = 1;
        $this->base->scheduler->alert_on_fail = 1;

        $this->notifier->shouldReceive('alert')->with(Error::class)->once();
        $this->notifier->shouldReceive('alert')->with(Slow::class)->never();
        $this->notifier->shouldReceive('alert')->with(Fast::class)->never();
        $this->notifier->shouldReceive('alert')->with(Working::class)->never();

        $this->base->testHandleNotifications();
    }

    public function test_honors_no_alert_on_fail()
    {
        $this->base->exitcode = 1;
        $this->base->scheduler->alert_on_fail = 0;

        $this->notifier->shouldReceive('alert')->with(Error::class)->never();
        $this->notifier->shouldReceive('alert')->with(Slow::class)->never();
        $this->notifier->shouldReceive('alert')->with(Fast::class)->never();
        $this->notifier->shouldReceive('alert')->with(Working::class)->never();

        $this->base->testHandleNotifications();
    }

    public function test_handles_disabled_speed_checks()
    {
        $this->base->exitcode = 0;
        $this->base->history = factory(History::class)->create(['time_to_run' => 8]);
        $this->base->scheduler->alert_run_time_less_than = 0;
        $this->base->scheduler->alert_run_time_greater_than = 0;

        $this->notifier->shouldReceive('alert')->with(Error::class)->never();
        $this->notifier->shouldReceive('alert')->with(Slow::class)->never();
        $this->notifier->shouldReceive('alert')->with(Fast::class)->never();
        $this->notifier->shouldReceive('alert')->with(Working::class)->never();

        $this->base->testHandleNotifications();
    }

    public function test_handles_normal_run_speed()
    {
        $this->base->exitcode = 0;
        $this->base->history = factory(History::class)->create(['time_to_run' => 8]);
        $this->base->scheduler->alert_run_time_less_than = 5;
        $this->base->scheduler->alert_run_time_greater_than = 10;

        $this->notifier->shouldReceive('alert')->with(Error::class)->never();
        $this->notifier->shouldReceive('alert')->with(Slow::class)->never();
        $this->notifier->shouldReceive('alert')->with(Fast::class)->never();
        $this->notifier->shouldReceive('alert')->with(Working::class)->never();

        $this->base->testHandleNotifications();
    }

    public function test_alerts_when_too_fast()
    {
        $this->base->exitcode = 0;
        $this->base->history = factory(History::class)->create(['time_to_run' => 2]);
        $this->base->scheduler->alert_run_time_less_than = 5;
        $this->base->scheduler->alert_run_time_greater_than = 10;

        $this->notifier->shouldReceive('alert')->with(Error::class)->never();
        $this->notifier->shouldReceive('alert')->with(Slow::class)->never();
        $this->notifier->shouldReceive('alert')->with(Fast::class)->once();
        $this->notifier->shouldReceive('alert')->with(Working::class)->never();

        $this->base->testHandleNotifications();
    }

    public function test_handles_when_too_slow()
    {
        $this->base->exitcode = 0;
        $this->base->history = factory(History::class)->create(['time_to_run' => 12]);
        $this->base->scheduler->alert_run_time_less_than = 5;
        $this->base->scheduler->alert_run_time_greater_than = 10;

        $this->notifier->shouldReceive('alert')->with(Error::class)->never();
        $this->notifier->shouldReceive('alert')->with(Slow::class)->once();
        $this->notifier->shouldReceive('alert')->with(Fast::class)->never();
        $this->notifier->shouldReceive('alert')->with(Working::class)->never();

        $this->base->testHandleNotifications();
    }
}


class OtherBaseEventMockClass
{
    use BaseEvent;

    public $scheduler;
    public $command;
    public $expression;
    public $timezone;
    public $runInBackground;
    public $withoutOverlapping;
    public $description;

    public function testHandleNotifications()
    {
        return $this->handleNotifications();
    }
}
