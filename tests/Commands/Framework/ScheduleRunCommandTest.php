<?php

namespace Eyewitness\Eye\Test\Commands\Framework;

use Mockery;
use Eyewitness\Eye\Test\TestCase;
use Illuminate\Support\Facades\DB;
use Eyewitness\Eye\Scheduling\Event;
use Illuminate\Support\Facades\Artisan;
use Eyewitness\Eye\Commands\Framework\ScheduleRunCommand;

class ScheduleRunCommandTest extends TestCase
{
    protected $schedule;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        Artisan::command('example:one', function () {
            $this->info('From example:one command');
        })->describe('Example one');

        Artisan::command('example:two', function () {
            $this->info('From example:two command');
        })->describe('Example two');

        Artisan::command('example:bad', function () {
            abort(500);
        })->describe('Example bad');

        $this->schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
    }

    public function test_no_commands_due_to_run()
    {
        $this->schedule->command('example:one')->cron('1 1 1 1 1');

        Artisan::call('schedule:run');

        $output = Artisan::output();

        $this->assertStringContainsString('No scheduled commands are ready to run', $output);
    }

    public function test_commands_inserted_into_database_first_time()
    {
        $this->schedule->command('example:one')->cron('* * * * *')->withoutOverlapping();
        $this->schedule->command('example:two --force')->cron('* * * * *');

        $this->assertDatabaseMissing('eyewitness_io_schedulers', [
            'schedule' => '* * * * *',
            'command' => 'example:one',
            'without_overlapping' => '1',
        ]);

        $this->assertDatabaseMissing('eyewitness_io_schedulers', [
            'schedule' => '* * * * *',
            'command' => 'example:two --force',
            'without_overlapping' => '0',
        ]);

        Artisan::call('schedule:run');

        $output = Artisan::output();

        $this->assertDatabaseHas('eyewitness_io_schedulers', [
            'schedule' => '* * * * *',
            'command' => 'example:one',
            'without_overlapping' => '1',
        ]);

        $this->assertDatabaseHas('eyewitness_io_schedulers', [
            'schedule' => '* * * * *',
            'command' => 'example:two --force',
            'without_overlapping' => '0',
        ]);

        $this->assertStringContainsString('Running scheduled command: * * * * *', $output);
        $this->assertStringContainsString('example:one', $output);
        $this->assertStringContainsString('example:two --force', $output);
    }

    public function test_pings_inserted_into_database()
    {
        $this->schedule->command('example:one')->cron('* * * * *')->withoutOverlapping();

        Artisan::call('schedule:run');

        $output = Artisan::output();

        $scheduler = DB::table('eyewitness_io_schedulers')->first();

        $ping = DB::table('eyewitness_io_history_scheduler')->first();

        $this->assertEquals($scheduler->id, $ping->scheduler_id);
        $this->assertGreaterThan(0, $ping->time_to_run);
        //$this->assertEquals("1", $ping->exitcode);
        $this->assertStringContainsString("Could not open input file: artisan", $ping->output);
    }

    public function test_pings_honour_cron_capture_config()
    {
        config(['eyewitness.capture_cron_output' => false]);

        $this->schedule->command('example:one')->cron('* * * * *')->withoutOverlapping();

        Artisan::call('schedule:run');

        $ping = DB::table('eyewitness_io_history_scheduler')->first();

        $this->assertNull($ping->output);
    }
}
