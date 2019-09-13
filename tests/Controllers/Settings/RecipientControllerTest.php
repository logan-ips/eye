<?php

namespace Eyewitness\Eye\Test\Controllers\Settings;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Notifications\Severity;

class RecipientControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_create_recipient_page_loads()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.recipients.create'));

        $response->assertStatus(200);
        $response->assertSee('1. Please choose a notification type:');
    }

    public function test_email()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.recipients.create.email'),
                                ['email' => 'test@example.com',
                                 'low' => 1,
                                 'medium' => 1,
                                 'high' => 0
                                ]);

        $response->assertRedirect(route('eyewitness.settings.index').'#recipients');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_recipients',
                                 ['type' => 'email',
                                  'address' => 'test@example.com',
                                  'low' => 1,
                                  'medium' => 1,
                                  'high' => 0]);
    }

    public function test_slack()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.recipients.create.slack'),
                                ['slackurl' => 'https://example.com/123456',
                                 'low' => 0,
                                 'medium' => 1,
                                 'high' => 1
                                ]);

        $response->assertRedirect(route('eyewitness.settings.index').'#recipients');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_recipients',
                                 ['type' => 'slack',
                                  'address' => 'https://example.com/123456',
                                  'low' => 0,
                                  'medium' => 1,
                                  'high' => 1]);
    }

    public function test_pushover()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.recipients.create.pushover'),
                                ['pushoverkey' => 'key',
                                 'pushoverapi' => 'example',
                                 'low' => 1,
                                 'medium' => 0,
                                 'high' => 1
                                ]);

        $response->assertRedirect(route('eyewitness.settings.index').'#recipients');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_recipients',
                                 ['type' => 'pushover',
                                  'address' => 'key',
                                  'meta' => json_encode(['token' => 'example']),
                                  'low' => 1,
                                  'medium' => 0,
                                  'high' => 1]);
    }

    public function test_pagerduty()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.recipients.create.pagerduty'),
                                ['pagerdutykey' => 'abcdefghilajifkjewf',
                                 'low' => 0,
                                 'medium' => 0,
                                 'high' => 1
                                ]);

        $response->assertRedirect(route('eyewitness.settings.index').'#recipients');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_recipients',
                                 ['type' => 'pagerduty',
                                  'address' => 'abcdefghilajifkjewf',
                                  'low' => 0,
                                  'medium' => 0,
                                  'high' => 1]);
    }

    public function test_hipchat()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.recipients.create.hipchat'),
                                ['hipchattoken' => 'abcdefghilajifkjewf',
                                 'roomid' => '123456',
                                 'low' => 1,
                                 'medium' => 0,
                                 'high' => 0
                                ]);

        $response->assertRedirect(route('eyewitness.settings.index').'#recipients');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_recipients',
                                 ['type' => 'hipchat',
                                  'address' => '123456',
                                  'meta' => json_encode(['token' => 'abcdefghilajifkjewf']),
                                  'low' => 1,
                                  'medium' => 0,
                                  'high' => 0]);
    }

    public function test_webhook()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.recipients.create.webhook'),
                                ['webhook' => 'https://example.com/123456',
                                 'low' => 0,
                                 'medium' => 1,
                                 'high' => 1
                                ]);

        $response->assertRedirect(route('eyewitness.settings.index').'#recipients');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_recipients',
                                 ['type' => 'webhook',
                                  'address' => 'https://example.com/123456',
                                  'low' => 0,
                                  'medium' => 1,
                                  'high' => 1]);
    }

    public function test_nexmo()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.recipients.create.nexmo'),
                                ['nexmo_phone' => '1234567890',
                                 'nexmo_api_key' => '1234',
                                 'nexmo_api_secret'=> '5678',
                                 'low' => 1,
                                 'medium' => 0,
                                 'high' => 1
                                ]);

        $response->assertRedirect(route('eyewitness.settings.index').'#recipients');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_recipients',
                                 ['type' => 'nexmo',
                                  'address' => '1234567890',
                                  'meta' => json_encode([
                                    'api_key' => '1234',
                                    'api_secret' => '5678'
                                  ]),
                                  'low' => 1,
                                  'medium' => 0,
                                  'high' => 1]);
    }
}
