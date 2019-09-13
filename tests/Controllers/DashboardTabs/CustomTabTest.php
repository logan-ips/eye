<?php

namespace Eyewitness\Eye\Test\Controllers\DashboardTabs;

use Eyewitness\Eye\Test\TestCase;

class CustomTabTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_example_custom_monitor_loads_with_custom_monitor()
    {
        config(['eyewitness.custom_witnesses' => [\Eyewitness\Eye\Test\Controllers\DashboardTabs\MyWitness::class]]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('My Example Test!');
    }
}

class MyWitness extends \Eyewitness\Eye\Monitors\Custom
{
    public $displayName = 'My Example Test!';

    public function run()
    {
        //
    }
}
