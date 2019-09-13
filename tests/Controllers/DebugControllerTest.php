<?php

namespace Eyewitness\Eye\Test\Controllers;

use Eyewitness\Eye\Test\TestCase;

class DebugControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_debug_mode_must_be_enabled_to_view_page()
    {
        config(['eyewitness.debug' => false]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/debug');

        $response->assertRedirect($this->home.'/dashboard#overview');
        $response->assertSessionHas('error', 'Sorry, but you need to enable eyewitness.debug mode to be able to view the debug page');
    }

    public function test_removes_senstive_data()
    {
        config(['eyewitness.debug' => true]);
        config(['app.secret' => 'example789']);
        config(['app.password' => 'password789']);
        config(['app.other' => 'other789']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/debug');

        $response->assertStatus(200);
        $response->assertSee('other789');
        $response->assertDontSee('example789');
        $response->assertDontSee('password789');
    }
}
