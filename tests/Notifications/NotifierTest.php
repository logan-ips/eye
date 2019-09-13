<?php

namespace Eyewitness\Eye\Test\Notifications;

use Mockery;
use Eyewitness\Eye\Test\TestCase;
use Illuminate\Support\Facades\Cache;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Drivers\Email;
use Eyewitness\Eye\Repo\Notifications\Recipient;
use Eyewitness\Eye\Notifications\Messages\TestMessage;

class NotifierTest extends TestCase
{
    protected $channel;

    public function setUp(): void
    {
        parent::setUp();

        $this->channel = Mockery::mock(Email::class);
        $this->app->instance('\\Eyewitness\\Eye\\Notifications\\Drivers\\Email', $this->channel);

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_send_fires_correct_channel()
    {
        $recipient = factory(Recipient::class)->create();
        $message = new TestMessage;

        $this->channel->shouldReceive('fire')->with($recipient, $message)->once();

        $notifier = new Notifier;
        $notifier->sendTo($recipient, $message);
    }

    public function test_alert_does_not_send_if_debounce_active_but_still_stores_in_database()
    {
        $recipient = factory(Recipient::class)->create(['type' => 'email']);
        $message = new TestMessage;

        $this->channel->shouldReceive('fire')->never();
        Cache::shouldReceive('has')->with('eyewitness_debounce_test_notification_testmessage')->once()->andReturn(true);
        Cache::shouldReceive('add')->with('eyewitness_debounce_test_notification_testmessage')->never();

        $notifier = new Notifier;
        $notifier->alert($message);

        $this->assertDatabaseHas('eyewitness_io_notification_history', [
            'type' => 'Test Notification',
        ]);
    }

    public function test_alert_sends_to_all_recipients()
    {
        $recipient1 = factory(Recipient::class)->create(['type' => 'email']);
        $recipient2 = factory(Recipient::class)->create(['type' => 'email']);
        $message = new TestMessage;

        $this->channel->shouldReceive('fire')->twice();
        Cache::shouldReceive('has')->with('eyewitness_debounce_test_notification_testmessage')->once()->andReturn(false);
        Cache::shouldReceive('add')->with('eyewitness_debounce_test_notification_testmessage', 1, 1)->once();

        $notifier = new Notifier;
        $notifier->alert($message);
    }

    public function test_alert_sends_only_to_correct_severity_recipients()
    {
        $recipient1 = factory(Recipient::class)->create(['type' => 'email', 'low' => 1]);
        $recipient2 = factory(Recipient::class)->create(['type' => 'email', 'low' => 1]);
        $recipient3 = factory(Recipient::class)->create(['type' => 'email', 'low' => 0]);
        $message = new TestMessage;

        $this->channel->shouldReceive('fire')->twice();
        Cache::shouldReceive('has')->with('eyewitness_debounce_test_notification_testmessage')->once()->andReturn(false);
        Cache::shouldReceive('add')->with('eyewitness_debounce_test_notification_testmessage', 1, 1)->once();

        $notifier = new Notifier;
        $notifier->alert($message);
    }

    public function test_alert_inserts_database_record()
    {
        $message = new TestMessage;

        $notifier = new Notifier;
        $notifier->alert($message);

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
