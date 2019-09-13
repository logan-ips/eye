<?php

namespace Eyewitness\Eye\Test\Controllers;

use Eyewitness\Eye\Test\TestCase;

class DashboardControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_default_route_when_logged_in()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home);

        $response->assertRedirect($this->home.'/dashboard#overview');
    }

    public function test_dashboard_requires_auth()
    {
        $response = $this->get($this->home.'/dashboard');

        $response->assertRedirect($this->home);
        $response->assertSessionMissing('eyewitness:auth');
        $response->assertSessionHas('warning', 'Sorry - you must login to Eyewitness first.');
    }

    public function test_dashboard_loads()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
    }

    public function test_dashboard_loads_with_tutorial()
    {
        config(['eyewitness.display_helpers' => true]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Eyewitness will automatically track each of your scheduled crons. They are added automatically tracked and added here when they run, so no need to manually add anything yourself');
    }

    public function test_dashboard_loads_without_tutorial()
    {
        config(['eyewitness.display_helpers' => false]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('Eyewitness will automatically track each of your scheduled crons. They are added automatically tracked and added here when they run, so no need to manually add anything yourself');
    }
}
