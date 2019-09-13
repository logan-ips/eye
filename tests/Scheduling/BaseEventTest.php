<?php

namespace Eyewitness\Eye\Test\Scheduling;

use Carbon\Carbon;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Scheduling\BaseEvent;
use Eyewitness\Eye\Repo\History\Scheduler as History;

class BaseEventTest extends TestCase
{
    protected $base;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->base = new myBaseEventMockClass;
    }

    public function test_get_summary_builds_correctly()
    {
        $this->base->expression = '1 2 3';
        $this->base->description = 'fake';

        $this->assertEquals('1 2 3 : fake', $this->base->getSummaryForDisplay());
    }

    public function test_get_command_filters()
    {
        $this->base->command = "'/usr/bin/php7.2' 'artisan' other:weekly --example";
        $this->base->description = "example from command";

        $this->assertEquals('other:weekly --example', $this->base->getCommandName());
        $this->assertEquals("'/usr/bin/php7.2' 'artisan' other:weekly --example", $this->base->getCommandName(false));

        $this->base->command = null;

        $this->assertEquals('example from command', $this->base->getCommandName());

        $this->base->description = null;

        $this->assertEquals('Unnamed Closure', $this->base->getCommandName());
    }

    public function test_run_in_foreground()
    {
        $this->assertFalse($this->base->forceRunInForeground);

        $this->base->runInForeground();

        $this->assertTrue($this->base->forceRunInForeground);
    }

    public function test_record_event_start()
    {
        $this->base->expression = '0 1 * * *';
        $this->base->timezone = 'UTC';
        $this->base->withoutOverlapping = 1;
        $this->base->runInBackground = 0;

        $this->base->recordEventStart();

        $this->assertNotNull($this->base->start);
        $this->assertNotNull($this->base->scheduler);
        $this->assertNotNull($this->base->history);
        $this->assertEquals(1, $this->base->exitcode);

        $this->assertDatabaseHas('eyewitness_io_schedulers', [
            'id' => '1',
            'schedule' => '0 1 * * *',
            'command' => 'Unnamed Closure',
            'timezone' => 'UTC',
            'run_in_background' => '0',
            'without_overlapping' => '1',
        ]);

        $this->assertDatabaseHas('eyewitness_io_history_scheduler', [
            'scheduler_id' => '1',
            'exitcode' => null,
        ]);
    }

    public function test_record_event_finish()
    {
        $this->base->start = microtime(true);
        $this->base->exitcode = 1;
        $this->base->history = History::create([
            'scheduler_id' => 4,
        ]);

        $this->base->recordEventFinish();

        $this->assertDatabaseHas('eyewitness_io_history_scheduler', [
            'scheduler_id' => '4',
            'exitcode' => '1',
            'output' => 'example output',
        ]);
    }
}


class myBaseEventMockClass
{
    use BaseEvent;

    public $command;
    public $expression;
    public $timezone;
    public $runInBackground;
    public $withoutOverlapping;
    public $description;

    public function retrieveOutput()
    {
        return 'example output';
    }
}
