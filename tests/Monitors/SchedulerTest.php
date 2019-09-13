<?php

namespace Eyewitness\Eye\Test\Monitors;

use Mockery;
use Carbon\Carbon;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Scheduler;
use Eyewitness\Eye\Monitors\Scheduler as Monitor;
use Eyewitness\Eye\Repo\History\Scheduler as History;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Fast;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Slow;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Error;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Missed;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Overdue;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Working;

class SchedulerTest extends TestCase
{
    protected $notifier;

    protected $monitor;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->notifier = Mockery::mock(Notifier::class);
        $this->app->instance(Notifier::class, $this->notifier);

        $this->monitor = resolve(Monitor::class);
    }

    public function test_handles_no_schedulers()
    {
        $this->notifier->shouldReceive('alert')->never();

        $this->monitor->poll();
    }

    public function test_ignores_new_schedules()
    {
        $schedule = factory(Scheduler::class)->create(['next_run_due' => Carbon::now()->subDay(),
                                                       'next_check_due' => Carbon::now()->subDay(),
                                                       'alert_on_fail' => 1,
                                                       'healthy' => null]);

        $this->notifier->shouldReceive('alert')->never();

        $this->monitor->poll();
    }

    public function test_alerts_for_missed_schedules()
    {
        $schedule = factory(Scheduler::class)->create(['next_run_due' => Carbon::now()->subDay(),
                                                       'next_check_due' => Carbon::now()->subDay(),
                                                       'alert_on_fail' => 1,
                                                       'healthy' => 1]);

        $this->notifier->shouldReceive('alert')->with(Missed::class)->once();

        $this->monitor->poll();
    }

    public function test_does_not_alert_if_scheduler_already_missed()
    {
        $schedule = factory(Scheduler::class)->create(['next_run_due' => Carbon::now()->subDay(),
                                                       'next_check_due' => Carbon::now()->subDay(),
                                                       'alert_on_fail' => 1,
                                                       'healthy' => 0]);

        $this->notifier->shouldReceive('alert')->with(Missed::class)->never();

        $this->monitor->poll();
    }

    public function test_detects_overdue_running_scheduler()
    {
        $schedule = factory(Scheduler::class)->create(['next_run_due' => Carbon::now()->addDay(),
                                                       'next_check_due' => Carbon::now()->addDay(),
                                                       'alert_on_fail' => 1]);

        $history = factory(History::class)->create(['exitcode' => null,
                                                    'expected_completion' => Carbon::now()->subDay()]);

        $this->notifier->shouldReceive('alert')->with(Missed::class)->never();
        $this->notifier->shouldReceive('alert')->with(Overdue::class)->once();

        $this->monitor->poll();
    }

    public function test_does_not_alert_if_disabled_for_overdue_scheduler()
    {
        $schedule = factory(Scheduler::class)->create(['next_run_due' => Carbon::now()->addDay(),
                                                       'next_check_due' => Carbon::now()->addDay(),
                                                       'alert_on_fail' => 0,
                                                       'alert_run_time_greater_than' => 0]);

        $history = factory(History::class)->create(['exitcode' => null,
                                                    'expected_completion' => Carbon::now()->subDay()]);

        $this->notifier->shouldReceive('alert')->with(Missed::class)->never();
        $this->notifier->shouldReceive('alert')->with(Overdue::class)->never();

        $this->monitor->poll();
    }

    public function test_alerts_for_conditional_schedules()
    {
        $schedule = factory(Scheduler::class)->create(['next_run_due' => Carbon::now()->addDay(),
                                                       'next_check_due' => Carbon::now()->addDay(),
                                                       'last_run' => Carbon::now()->subDay(),
                                                       'alert_on_fail' => 1,
                                                       'healthy' => 1,
                                                       'alert_last_run_greater_than' => 10]);

        $this->notifier->shouldReceive('alert')->with(Missed::class)->once();

        $this->monitor->poll();
    }

    public function test_alerts_for_conditional_schedules_already_overdue()
    {
        $schedule = factory(Scheduler::class)->create(['next_run_due' => Carbon::now()->addDay(),
                                                       'next_check_due' => Carbon::now()->addDay(),
                                                       'last_run' => Carbon::now()->subDay(),
                                                       'alert_on_fail' => 1,
                                                       'healthy' => 0,
                                                       'alert_last_run_greater_than' => 10]);

        $this->notifier->shouldReceive('alert')->with(Missed::class)->never();

        $this->monitor->poll();
    }

    public function test_alerts_for_conditional_schedules_not_overdue()
    {
        $schedule = factory(Scheduler::class)->create(['next_run_due' => Carbon::now()->addDay(),
                                                       'next_check_due' => Carbon::now()->addDay(),
                                                       'last_run' => Carbon::now(),
                                                       'alert_on_fail' => 1,
                                                       'healthy' => 0,
                                                       'alert_last_run_greater_than' => 10]);

        $this->notifier->shouldReceive('alert')->with(Missed::class)->never();

        $this->monitor->poll();
    }
}
