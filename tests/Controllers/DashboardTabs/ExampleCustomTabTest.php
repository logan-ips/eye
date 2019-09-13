<?php

namespace Eyewitness\Eye\Test\Controllers\DashboardTabs;

use Eyewitness\Eye\Test\TestCase;

class ExampleCustomTabTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_example_custom_monitor_loads_with_helpers()
    {
        config(['eyewitness.display_helpers' => true]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('This could be your custom monitor');
    }

    public function test_example_custom_monitor_does_not_load_when_helper_disabled()
    {
        config(['eyewitness.display_helpers' => false]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('This could be your custom monitor');
    }
}
