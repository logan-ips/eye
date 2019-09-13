<?php

namespace Eyewitness\Eye\Test\Controllers\DashboardTabs;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Scheduler;

class SchedulerTabTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_schedulers_tab_disables()
    {
        $scheduler1 = factory(Scheduler::class)->create();

        config(['eyewitness.monitor_scheduler' => false]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee($scheduler1->command);
        $response->assertDontSee('Scheduler');
    }

    public function test_schedulers_tab()
    {
        $scheduler1 = factory(Scheduler::class)->create();
        $scheduler2 = factory(Scheduler::class)->create(['schedule' => '* 3 5 34 3']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee($scheduler1->command);
        $response->assertSee($scheduler2->command);
        $response->assertSee($scheduler1->schedule);
        $response->assertSee($scheduler2->schedule);
    }

    public function test_schedulers_tab_with_no_last_run()
    {
        $scheduler1 = factory(Scheduler::class)->create(['schedule' => '* 3 5 34 3', 'last_run' => null]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Never');
    }

    public function test_schedulers_tab_with_previous_run()
    {
        $scheduler1 = factory(Scheduler::class)->create(['schedule' => '* 3 5 34 3', 'last_run' => '2016-01-01 01:01:01']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('Never');
    }
}

