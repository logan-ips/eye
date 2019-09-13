<?php

namespace Eyewitness\Eye\Test\Notifications\Drivers;

use Mockery;
use GuzzleHttp\Client;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Notifications\Drivers\Nexmo;
use Eyewitness\Eye\Repo\Notifications\Recipient;
use Eyewitness\Eye\Notifications\Messages\TestMessage;

class NexmoTest extends TestCase
{
    protected $guzzle;

    public function setUp(): void
    {
        parent::setUp();

        $this->guzzle = Mockery::mock(Client::class);
        $this->app->instance(Client::class, $this->guzzle);

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_sends_nexmo()
    {
        $message = new TestMessage;
        $recipient = new Recipient([
            'type' => 'nexmo',
            'address' => '12345',
            'meta' => [
                'api_key' => '67890',
                'api_secret' => 'abc',
            ]
        ]);

        $this->guzzle->shouldReceive('post')->once();

        $hipchat = new Nexmo;
        $hipchat->fire($recipient, $message);
    }
}
