<?php

namespace Eyewitness\Eye\Test\Notifications\Drivers;

use Mockery;
use GuzzleHttp\Client;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Notifications\Recipient;
use Eyewitness\Eye\Notifications\Drivers\Pagerduty;
use Eyewitness\Eye\Notifications\Messages\TestMessage;

class PagerdutyTest extends TestCase
{
    protected $guzzle;

    public function setUp(): void
    {
        parent::setUp();

        $this->guzzle = Mockery::mock(Client::class);
        $this->app->instance(Client::class, $this->guzzle);

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_sends_pagerduty()
    {
        $message = new TestMessage;
        $recipient = new Recipient([
            'type' => 'pagerduty',
            'address' => '12345',
        ]);

        $this->guzzle->shouldReceive('post')->once();

        $hipchat = new Pagerduty;
        $hipchat->fire($recipient, $message);
    }
}
