<?php

namespace Eyewitness\Eye\Test\Notifications\Drivers;

use Eyewitness\Eye\Test\TestCase;
use Illuminate\Support\Facades\Mail;
use Eyewitness\Eye\Repo\Notifications\Recipient;
use Eyewitness\Eye\Notifications\Drivers\Database;
use Eyewitness\Eye\Notifications\Messages\TestMessage;

class DatabaseTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_inserts_history()
    {
        $message = new TestMessage;
        $recipient = new Recipient;

        $database = new Database;
        $database->fire($recipient, $message);

        $this->assertDatabaseHas('eyewitness_io_notification_history', [
            'type' => 'Test Notification',
            'acknowledged' => '0',
            'isError' => '0',
            'title' => 'Test Notification',
            'description' => 'This is a manually generated notification to check your channel is working correctly.',
            'severity' => 'low'
        ]);
    }
}
