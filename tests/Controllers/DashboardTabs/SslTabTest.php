<?php

namespace Eyewitness\Eye\Test\Controllers\DashboardTabs;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\History\Ssl;

class SslTabTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_ssl_tab_disables()
    {
        config(['eyewitness.monitor_ssl' => false]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('SSL');
    }

    public function test_ssl_tab_with_no_domains()
    {
        config(['eyewitness.application_domains' => []]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('SSL monitoring not active');
        $response->assertSee('yewitness keeps an eye on your application SSL using the HtBridge API. If any changes are detected, you will receive an alert.');
    }

    public function test_ssl_tab_with_no_history()
    {
        config(['eyewitness.application_domains' => ['http://example.com']]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('No SSL records found yet');
        $response->assertSee('Your domains are scheduled to be checked in a short time (usually within an hour).');
    }

    public function test_ssl_tab_with_history()
    {
        config(['eyewitness.application_domains' => ['http://example.com']]);

        $history = factory(Ssl::class)->create(['meta' => 'http://example.com',
                                                'record' => [
                                                    'grade' => 'A+',
                                                    'results_url' => 'http://example.com',
                                                    'valid' => true,
                                                    'valid_from' => '12345',
                                                    'valid_to' => '67890',
                                                    'revoked' => false,
                                                    'expires_soon' => false,
                                                    'issuer' => 'EyeCA'
                                                ]]);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get($this->home.'/dashboard');

        $response->assertStatus(200);
        $response->assertSee('http://example.com');
        $response->assertSee('A+');
        $response->assertSee('EyeCA');
    }
}

