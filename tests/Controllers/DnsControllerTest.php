<?php

namespace Eyewitness\Eye\Test\Controllers;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\History\Dns;

class DnsControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_requires_domain()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.dns.show'));

        $response->assertStatus(302);
        $response->assertSessionHas('errors');
    }

    public function test_checks_dns_history()
    {
        $dns = factory(Dns::class)->create(['meta' => 'http://example.com']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.dns.show', ['domain' => 'http://wrong.com']));

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Sorry - we could not find any DNS history for that domain. If you have just added the record, you should wait for the first test to run (usaully within an hour).');
    }

    public function test_loads_dns_history()
    {
        $dns = factory(Dns::class)->create(['meta' => 'http://example.com']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.dns.show', ['domain' => $dns->meta]));

        $response->assertStatus(200);
        $response->assertSee($dns->meta);
    }
}
