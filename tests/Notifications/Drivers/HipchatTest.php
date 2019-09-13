<?php

namespace Eyewitness\Eye\Test\Notifications\Drivers;

use Mockery;
use GuzzleHttp\Client;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Notifications\Recipient;
use Eyewitness\Eye\Notifications\Drivers\Hipchat;
use Eyewitness\Eye\Notifications\Messages\TestMessage;

class HipchatTest extends TestCase
{
    protected $guzzle;

    public function setUp(): void
    {
        parent::setUp();

        $this->guzzle = Mockery::mock(Client::class);
        $this->app->instance(Client::class, $this->guzzle);

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_sends_hipchat()
    {
        $message = new TestMessage;
        $recipient = new Recipient([
            'type' => 'hipchat',
            'address' => '12345',
            'meta' => [
                'token' => '67890'
            ]
        ]);

        $this->guzzle->shouldReceive('post')->once();

        $hipchat = new Hipchat;
        $hipchat->fire($recipient, $message);
    }
}
