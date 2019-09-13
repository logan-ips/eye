<?php

namespace Eyewitness\Eye\Test\Controllers;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Notifications\History;

class NotificaitonsControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }


    public function test_show_notification_page_loads()
    {
        $history = factory(History::class)->create();

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.notifications.show', $history->id));

        $response->assertStatus(200);
        $response->assertSee($history->title);
        $response->assertSee('Outstanding');
        $response->assertSee('Low');
        $response->assertSee($history->description);
    }

    public function test_notification_can_be_acknowledged()
    {
        $history = factory(History::class)->create(['acknowledged' => 0]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.notifications.update', $history->id), ['_method' => 'put']);

        $response->assertRedirect(route('eyewitness.dashboard').'#notifications');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_history', ['id' => $history->id, 'acknowledged' => 1]);
    }
}
