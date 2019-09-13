<?php

namespace Eyewitness\Eye\Test\Controllers\DashboardTabs;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Statuses;
use Eyewitness\Eye\Repo\History\Composer;

class ComposerTabTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_composer_tab_disables()
    {
        config(['eyewitness.monitor_composer' => false]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('Composer.lock');
    }

    public function test_composer_tab_no_check()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Composer check not yet completed');
    }

    public function test_composer_tab_healthy()
    {
        factory(Statuses::class)->create(['monitor' => 'composer', 'healthy' => 1]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Your Composer.lock has no known vulnerabilities');
    }

    public function test_composer_tab_sick()
    {
        factory(Statuses::class)->create(['monitor' => 'composer', 'healthy' => 0]);
        factory(Composer::class)->create();

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Your Composer.lock has known vulnerabilities');
    }
}

