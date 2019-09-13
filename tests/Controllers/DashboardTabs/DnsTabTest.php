<?php

namespace Eyewitness\Eye\Test\Controllers\DashboardTabs;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\History\Dns;

class DnsTabTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_dns_tab_disables()
    {
        config(['eyewitness.monitor_dns' => false]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('DNS');
    }

    public function test_dns_tab_with_no_domains()
    {
        config(['eyewitness.application_domains' => []]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('DNS monitoring not active');
        $response->assertSee('Eyewitness keeps an eye on your application DNS. If any changes are detected, you will receive an alert');
    }

    public function test_dns_tab_with_no_history()
    {
        config(['eyewitness.application_domains' => ['http://example.com']]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('No DNS records found yet');
        $response->assertSee('Your domains are scheduled to be checked in a short time (usually within an hour).');
    }

    public function test_dns_tab_with_history()
    {
        config(['eyewitness.application_domains' => ['http://example.com']]);

        $history = factory(Dns::class)->create(['meta' => 'http://example.com', 'record' => [['txt' => 'i_am_example']]]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('http://example.com');
        $response->assertSee('i_am_example');
    }
}

