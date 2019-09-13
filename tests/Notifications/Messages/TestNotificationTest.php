<?php

namespace Eyewitness\Eye\Test\Notifications\Messages;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Notifications\Severity;
use Eyewitness\Eye\Notifications\Messages\TestMessage;

class TestMessageTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_base_message_does_safe_type_conversion()
    {
        $message = new TestMessage;

        $this->assertEquals('test_notification', $message->safeType());
    }

    public function test_base_message_does_severity_check_and_uses_default_if_no_record_found()
    {
        Severity::truncate();

        $message = new TestMessage;

        $this->assertEquals('low', $message->severity());
    }

    public function test_base_message_does_severity_check_and_uses_database_record()
    {
        Severity::truncate();

        factory(Severity::class)->create(['namespace' => 'test_notification',
                                          'notification' => 'Eyewitness\Eye\Notifications\Messages\TestMessage',
                                          'severity' => 'high']);

        $message = new TestMessage;

        $this->assertEquals('high', $message->severity());
    }
}
