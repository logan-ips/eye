<?php

namespace Eyewitness\Eye\Test\Monitors;

use Mockery;
use Exception;
use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Monitors\Dns as Witness;
use Eyewitness\Eye\Test\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Eyewitness\Eye\Repo\History\Dns as History;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Messages\Dns\Change;

class DnsTest extends TestCase
{
    protected $notifier;

    protected $dns;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->notifier = Mockery::mock(Notifier::class);
        $this->app->instance(Notifier::class, $this->notifier);

        $this->dns = Mockery::mock('Eyewitness\Eye\Monitors\Dns[pollDns]', [resolve(Eye::class)])->shouldAllowMockingProtectedMethods();
    }

    public function test_handles_no_configured_domains()
    {
        config(['eyewitness.debug' => true]);
        config(['eyewitness.application_domains' => []]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('debug')->with('Eyewitness: No application domain set for DNS witness', ['data' => null])->once();
        Log::shouldReceive('error')->never();

        $this->dns->poll();
    }

    public function test_handles_bad_dns_lookup()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);
        $e = new Exception;

        $this->dns->shouldReceive('pollDns')->with('example.com.')->once()->andThrow($e);
        $this->notifier->shouldReceive('alert')->never();

        $this->dns->poll();
    }

    public function test_handles_good_initial_dns_lookup()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        $this->dns->shouldReceive('pollDns')->with('example.com.')->once()->andReturn(['example' => ['ttl' => 5, 'cname' => 'tester']]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('error')->never();
        Log::shouldReceive('debug')->never();

        $this->dns->poll();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'dns',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['example' => ['cname' => 'tester']])]);
    }

    public function test_handles_good_dns_lookup_with_no_changes()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        factory(History::class)->create(['meta' => $domain, 'record' => ['example' => ['cname' => 'tester']]]);

        $this->dns->shouldReceive('pollDns')->with('example.com.')->once()->andReturn(['example' => ['ttl' => 5, 'cname' => 'tester']]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('error')->never();
        Log::shouldReceive('debug')->never();

        $this->dns->poll();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'dns',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['example' => ['cname' => 'tester']])]);

        $this->assertCount(1, DB::table('eyewitness_io_history_monitors')->get());
    }

    public function test_handles_dns_lookup_with_temp_failure()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        factory(History::class)->create(['meta' => $domain, 'record' => ['example' => ['cname' => 'tester']]]);

        $this->dns->shouldReceive('pollDns')->with('example.com.')->times(3)->andReturn(['example' => ['ttl' => 5, 'cname' => 'wrong']]);
        $this->dns->shouldReceive('pollDns')->with('example.com.')->once()->andReturn(['example' => ['ttl' => 5, 'cname' => 'tester']]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('error')->never();
        Log::shouldReceive('debug')->never();

        $this->dns->poll();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'dns',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['example' => ['cname' => 'tester']])]);

        $this->assertCount(1, DB::table('eyewitness_io_history_monitors')->get());
    }

    public function test_handles_dns_lookup_with_changes()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        factory(History::class)->create(['meta' => $domain, 'record' => ['example' => ['cname' => 'tester']]]);

        $this->dns->shouldReceive('pollDns')->with('example.com.')->times(6)->andReturn(['example' => ['ttl' => 5, 'cname' => 'changed']]);

        $this->notifier->shouldReceive('alert')->with(Change::class)->once();
        Log::shouldReceive('error')->never();
        Log::shouldReceive('debug')->never();

        $this->dns->poll();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'dns',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['example' => ['cname' => 'tester']])]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'dns',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['example' => ['cname' => 'changed']])]);

        $this->assertCount(2, DB::table('eyewitness_io_history_monitors')->get());
    }
}
