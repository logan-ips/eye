<?php

namespace Eyewitness\Eye\Test\Notifications\Messages;

use Eyewitness\Eye\Notifications\Messages\Scheduler\Fast;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Missed;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Overdue;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Slow;
use Eyewitness\Eye\Repo\History\Scheduler as History;
use Eyewitness\Eye\Repo\Scheduler;
use Eyewitness\Eye\Test\TestCase;

class NotificationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_overdue_message_has_metadata()
    {
        factory(Scheduler::class)->create();
        $history = factory(History::class)->create();

        $message = new Overdue(['scheduler' => $history->scheduler]);

        $this->assertNotEmpty($message->meta());
    }

    public function test_fast_message_has_metadata()
    {
        $scheduler = factory(Scheduler::class)->create();
        $history = factory(History::class)->create();

        $message = new Fast([
            'scheduler' => $scheduler,
            'time_to_run' => $history->time_to_run
        ]);

        $this->assertNotEmpty($message->meta());
    }

    public function test_slow_message_has_metadata()
    {
        $scheduler = factory(Scheduler::class)->create();
        $history = factory(History::class)->create();

        $message = new Slow([
            'scheduler' => $scheduler,
            'time_to_run' => $history->time_to_run
        ]);

        $this->assertNotEmpty($message->meta());
    }

    public function test_missed_message_has_metadata()
    {
        $scheduler = factory(Scheduler::class)->create();

        $message = new Missed([
            'scheduler' => $scheduler,
        ]);

        $this->assertNotEmpty($message->meta());
    }
}
