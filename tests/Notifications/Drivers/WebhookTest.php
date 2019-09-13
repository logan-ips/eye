<?php

namespace Eyewitness\Eye\Test\Notifications\Drivers;

use Mockery;
use GuzzleHttp\Client;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Notifications\Recipient;
use Eyewitness\Eye\Notifications\Drivers\Webhook;
use Eyewitness\Eye\Notifications\Messages\TestMessage;

class WebhookTest extends TestCase
{
    protected $guzzle;

    public function setUp(): void
    {
        parent::setUp();

        $this->guzzle = Mockery::mock(Client::class);
        $this->app->instance(Client::class, $this->guzzle);

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_sends_webhook()
    {
        $message = new TestMessage;
        $recipient = new Recipient([
            'type' => 'webhook',
            'address' => '12345',
            'meta' => [
                'token' => 'abcde'
            ]
        ]);

        $this->guzzle->shouldReceive('post')->once();

        $hipchat = new Webhook;
        $hipchat->fire($recipient, $message);
    }
}
