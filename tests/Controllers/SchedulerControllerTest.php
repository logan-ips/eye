<?php

namespace Eyewitness\Eye\Test\Controllers;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Scheduler;
use Eyewitness\Eye\Repo\History\Scheduler as History;

class SchedulerControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_show_scheduler_page_loads_with_no_pagination()
    {
        $scheduler = factory(Scheduler::class)->create();
        $history1 = factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => '0.845']);
        $history2 = factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => '0.234']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.schedulers.show', $scheduler));

        $response->assertStatus(200);
        $response->assertSee($scheduler->command);
        $response->assertSee($scheduler->schedule);
        $response->assertSee((string) round($history1->time_to_run, 1));
        $response->assertSee((string) round($history2->time_to_run, 1));
        $response->assertSee($history1->output);
        $response->assertSee($history2->output);
        $response->assertDontSee('Load More');
    }

    public function test_show_scheduler_page_loads_with_pagination()
    {
        config(['eyewitness.pagination_size' => 4]);

        $scheduler = factory(Scheduler::class)->create();
        $history1 = factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => '0.845']);
        $history2 = factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => '0.234']);
        $history3 = factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => '0.234']);
        $history4 = factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => '0.234']);
        $history5 = factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => '0.987']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.schedulers.show', $scheduler));

        $response->assertStatus(200);
        $response->assertSee($history1->output);
        $response->assertSee($history2->output);
        $response->assertSee('Load More');
        $response->assertDontSee('0.987');
    }

    public function test_update_scheduler_validation()
    {
        $scheduler = factory(Scheduler::class)->create();

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.schedulers.update', $scheduler),
                                ['_method' => 'put',
                                 'alert_on_missed' => true,
                                 'alert_on_fail' => true,
                                 'alert_run_time_greater_than' => 0,
                                 'alert_run_time_less_than' => -1,
                                 'alert_last_run_greater_than' => 0]);

         $response->assertStatus(302);
         $response->assertSessionHas('errors');
    }

    public function test_update_scheduler_saves_data()
    {
        $scheduler = factory(Scheduler::class)->create(['alert_on_missed' => true,
                                                        'alert_on_fail' => true,
                                                        'alert_run_time_greater_than' => 0,
                                                        'alert_run_time_less_than' => 0,
                                                        'alert_last_run_greater_than' => 0]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.schedulers.update', $scheduler),
                                ['_method' => 'put',
                                 'alert_on_missed' => false,
                                 'alert_on_fail' => false,
                                 'alert_run_time_greater_than' => 1,
                                 'alert_run_time_less_than' => 2,
                                 'alert_last_run_greater_than' => 3]);

         $response->assertRedirect(route('eyewitness.schedulers.show', $scheduler).'#settings');
         $response->assertSessionHas('success');

         $this->assertDatabaseHas('eyewitness_io_schedulers',
                                  ['id' => $scheduler->id,
                                   'alert_on_missed' => false,
                                   'alert_on_fail' => false,
                                   'alert_run_time_greater_than' => 1,
                                   'alert_run_time_less_than' => 2,
                                   'alert_last_run_greater_than' => 3]);
    }

    public function test_destroy_scheduler()
    {
        $scheduler1 = factory(Scheduler::class)->create();
        $history1 = factory(History::class)->create(['scheduler_id' => $scheduler1->id, 'time_to_run' => '0.845']);
        $history2 = factory(History::class)->create(['scheduler_id' => $scheduler1->id, 'time_to_run' => '0.234']);

        $scheduler2 = factory(Scheduler::class)->create();
        $history3 = factory(History::class)->create(['scheduler_id' => $scheduler2->id, 'time_to_run' => '0.234']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.schedulers.destroy', $scheduler1), ['_method' => 'delete']);

        $response->assertRedirect(route('eyewitness.dashboard').'#scheduler');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('eyewitness_io_schedulers', ['id' => $scheduler1->id]);
        $this->assertDatabaseMissing('eyewitness_io_history_scheduler', ['id' => $history1->id]);
        $this->assertDatabaseMissing('eyewitness_io_history_scheduler', ['id' => $history2->id]);

        $this->assertDatabaseHas('eyewitness_io_schedulers', ['id' => $scheduler2->id]);
        $this->assertDatabaseHas('eyewitness_io_history_scheduler', ['id' => $history3->id]);
    }
}
