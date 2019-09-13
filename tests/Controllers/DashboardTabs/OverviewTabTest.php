<?php

namespace Eyewitness\Eye\Test\Controllers\DashboardTabs;

use Eyewitness\Eye\Test\TestCase;

class OverviewTabTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_overview_tab()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('PHP version');
        $response->assertSee('Laravel version');
        $response->assertSee(app()->version());
        $response->assertSee('Route cache');
    }

    public function test_application_debug_mode()
    {
        config(['app.debug' => true]);
        config(['eyewitness.debug' => false]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Enabled');
        $response->assertDontSee('Eyewitness debug');
    }

    public function test_eyewitness_debug_mode()
    {
        config(['app.debug' => false]);
        config(['eyewitness.debug' => true]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Eyewitness debug');
        $response->assertSee('Enabled');
    }
}
