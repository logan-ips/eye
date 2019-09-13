<?php

namespace Eyewitness\Eye\Test\Monitors;

use Mockery;
use Exception;
use Eyewitness\Eye\Api;
use Eyewitness\Eye\Monitors\Ssl;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Statuses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Eyewitness\Eye\Repo\History\Ssl as History;
use Illuminate\Support\Facades\Cache;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Messages\Ssl\Revoked;
use Eyewitness\Eye\Notifications\Messages\Ssl\Invalid;
use Eyewitness\Eye\Notifications\Messages\Ssl\Expiring;
use Eyewitness\Eye\Notifications\Messages\Ssl\GradeChange;

class SslTest extends TestCase
{
    protected $notifier;

    protected $ssl;

    protected $api;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->notifier = Mockery::mock(Notifier::class);
        $this->app->instance(Notifier::class, $this->notifier);

        $this->api = Mockery::mock(Api::class);
        $this->app->instance(Api::class, $this->api);

        $this->ssl = resolve(Ssl::class);
    }

    public function test_poll_handles_no_configured_domains()
    {
        config(['eyewitness.debug' => true]);
        config(['eyewitness.application_domains' => []]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('debug')->with('Eyewitness: No application domain set for SSL monitor', ['data' => null])->once();

        $this->ssl->poll();
    }

    public function test_poll_handles_bad_ssl_lookup()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        $this->api->shouldReceive('sslStart')->with($domain)->once()->andReturn(['error' => 'Example Error']);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('error')->with('Eyewitness: SSL API scan error', ['exception' => 'Example Error', 'data' => $domain])->once();

        $this->ssl->poll();
    }

    public function test_poll_handles_good_ssl_lookup()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        $this->api->shouldReceive('sslStart')->with($domain)->once()->andReturn(['job_id' => 'job123', 'status_id' => 1]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('error')->never();
        Cache::shouldReceive('put')->with('eyewitness_ssl_job_id_http://example.com', 'job123', 50)->once();

        $this->ssl->poll();
    }

    public function test_result_handles_no_configured_domains()
    {
        config(['eyewitness.debug' => true]);
        config(['eyewitness.application_domains' => []]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('debug')->with('Eyewitness: No application domain set for SSL monitor', ['data' => null])->once();

        $this->ssl->result();
    }

    public function test_result_handles_no_cached_job()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        Cache::shouldReceive('has')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn(false);
        Log::shouldReceive('error')->never();
        $this->api->shouldReceive('sslResult')->never();
        $this->notifier->shouldReceive('alert')->never();

        $this->ssl->result();
    }

    public function test_result_handles_bad_api_result()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        Cache::shouldReceive('has')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn(true);
        Cache::shouldReceive('pull')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn('job123');
        Log::shouldReceive('error')->with('Eyewitness: SSL API result error', ['exception' => 'Example Error', 'data' => $domain])->once();

        $this->api->shouldReceive('sslResult')->with('job123')->once()->andReturn(['error' => 'Example Error']);
        $this->notifier->shouldReceive('alert')->never();

        $this->ssl->result();
    }

    public function test_does_not_alert_on_inital_lookup_and_saves_record()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        Cache::shouldReceive('has')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn(true);
        Cache::shouldReceive('pull')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn('job123');

        $this->api->shouldReceive('sslResult')->with('job123')->once()->andReturn(['results' => ['grade' => 'A+'],
                                                                            'internals' => ['short_id' => 'abcdef'],
                                                                            'certificates' => ['information' => [0 => [
                                                                                'valid_now' => false,
                                                                                'valid_from' => '12345',
                                                                                'valid_to' => '67890',
                                                                                'revoked' => true,
                                                                                'expires_soon' => true,
                                                                                'issuer_cn' => 'EyeCA'
                                                                            ]]]]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('error')->never();

        $this->ssl->result();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'ssl',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['grade' => 'A+',
                                                                                             'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                                                             'valid' => false,
                                                                                             'valid_from' => '12345',
                                                                                             'valid_to' => '67890',
                                                                                             'revoked' => true,
                                                                                             'expires_soon' => true,
                                                                                             'issuer' => 'EyeCA']
                                                                     )]);
    }

    public function test_does_not_alert_when_no_changes_and_saves_record()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        Cache::shouldReceive('has')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn(true);
        Cache::shouldReceive('pull')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn('job123');

        factory(History::class)->create(['meta' => $domain,
                                         'record' => ['grade' => 'A+',
                                                      'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                      'valid' => false,
                                                      'valid_from' => '12345',
                                                      'valid_to' => '67890',
                                                      'revoked' => true,
                                                      'expires_soon' => true,
                                                      'issuer' => 'EyeCA']]);

        $this->api->shouldReceive('sslResult')->with('job123')->once()->andReturn(['results' => ['grade' => 'A+'],
                                                                            'internals' => ['short_id' => 'abcdef'],
                                                                            'certificates' => ['information' => [0 => [
                                                                                'valid_now' => false,
                                                                                'valid_from' => '12345',
                                                                                'valid_to' => '67890',
                                                                                'revoked' => true,
                                                                                'expires_soon' => true,
                                                                                'issuer_cn' => 'EyeCA'
                                                                            ]]]]);

        $this->notifier->shouldReceive('alert')->never();
        Log::shouldReceive('error')->never();

        $this->ssl->result();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'ssl',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['grade' => 'A+',
                                                                                             'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                                                             'valid' => false,
                                                                                             'valid_from' => '12345',
                                                                                             'valid_to' => '67890',
                                                                                             'revoked' => true,
                                                                                             'expires_soon' => true,
                                                                                             'issuer' => 'EyeCA']
                                                                     )]);

        $this->assertCount(1, DB::table('eyewitness_io_history_monitors')->where('type', 'ssl')->get());

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'ssl_'.$domain,
                                                            'healthy' => 1]);
    }

    public function test_detects_revoked_certificate()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        Cache::shouldReceive('has')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn(true);
        Cache::shouldReceive('pull')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn('job123');

        factory(History::class)->create(['meta' => $domain,
                                         'record' => ['grade' => 'A+',
                                                      'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                      'valid' => false,
                                                      'valid_from' => '12345',
                                                      'valid_to' => '67890',
                                                      'revoked' => false,
                                                      'expires_soon' => true,
                                                      'issuer' => 'EyeCA']]);

        $this->api->shouldReceive('sslResult')->with('job123')->once()->andReturn(['results' => ['grade' => 'A+'],
                                                                            'internals' => ['short_id' => 'abcdef'],
                                                                            'certificates' => ['information' => [0 => [
                                                                                'valid_now' => false,
                                                                                'valid_from' => '12345',
                                                                                'valid_to' => '67890',
                                                                                'revoked' => true,
                                                                                'expires_soon' => true,
                                                                                'issuer_cn' => 'EyeCA'
                                                                            ]]]]);

        $this->notifier->shouldReceive('alert')->with(Revoked::class)->once();
        Log::shouldReceive('error')->never();

        $this->ssl->result();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'ssl',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['grade' => 'A+',
                                                                                             'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                                                             'valid' => false,
                                                                                             'valid_from' => '12345',
                                                                                             'valid_to' => '67890',
                                                                                             'revoked' => true,
                                                                                             'expires_soon' => true,
                                                                                             'issuer' => 'EyeCA']
                                                                     )]);

        $this->assertCount(1, DB::table('eyewitness_io_history_monitors')->where('type', 'ssl')->get());

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'ssl_'.$domain,
                                                            'healthy' => 0]);
    }

    public function test_detects_expiring_certificate()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        Cache::shouldReceive('has')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn(true);
        Cache::shouldReceive('pull')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn('job123');

        factory(History::class)->create(['meta' => $domain,
                                         'record' => ['grade' => 'A+',
                                                      'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                      'valid' => false,
                                                      'valid_from' => '12345',
                                                      'valid_to' => '67890',
                                                      'revoked' => false,
                                                      'expires_soon' => false,
                                                      'issuer' => 'EyeCA']]);

        $this->api->shouldReceive('sslResult')->with('job123')->once()->andReturn(['results' => ['grade' => 'A+'],
                                                                            'internals' => ['short_id' => 'abcdef'],
                                                                            'certificates' => ['information' => [0 => [
                                                                                'valid_now' => false,
                                                                                'valid_from' => '12345',
                                                                                'valid_to' => '67890',
                                                                                'revoked' => false,
                                                                                'expires_soon' => true,
                                                                                'issuer_cn' => 'EyeCA'
                                                                            ]]]]);

        $this->notifier->shouldReceive('alert')->with(Expiring::class)->once();
        Log::shouldReceive('error')->never();

        $this->ssl->result();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'ssl',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['grade' => 'A+',
                                                                                             'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                                                             'valid' => false,
                                                                                             'valid_from' => '12345',
                                                                                             'valid_to' => '67890',
                                                                                             'revoked' => false,
                                                                                             'expires_soon' => true,
                                                                                             'issuer' => 'EyeCA']
                                                                     )]);

        $this->assertCount(1, DB::table('eyewitness_io_history_monitors')->where('type', 'ssl')->get());

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'ssl_'.$domain,
                                                            'healthy' => 0]);
    }

    public function test_detects_invalid_certificate()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        Cache::shouldReceive('has')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn(true);
        Cache::shouldReceive('pull')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn('job123');

        factory(History::class)->create(['meta' => $domain,
                                         'record' => ['grade' => 'A+',
                                                      'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                      'valid' => true,
                                                      'valid_from' => '12345',
                                                      'valid_to' => '67890',
                                                      'revoked' => false,
                                                      'expires_soon' => true,
                                                      'issuer' => 'EyeCA']]);

        $this->api->shouldReceive('sslResult')->with('job123')->once()->andReturn(['results' => ['grade' => 'A+'],
                                                                            'internals' => ['short_id' => 'abcdef'],
                                                                            'certificates' => ['information' => [0 => [
                                                                                'valid_now' => false,
                                                                                'valid_from' => '12345',
                                                                                'valid_to' => '67890',
                                                                                'revoked' => false,
                                                                                'expires_soon' => true,
                                                                                'issuer_cn' => 'EyeCA'
                                                                            ]]]]);

        $this->notifier->shouldReceive('alert')->with(Invalid::class)->once();
        Log::shouldReceive('error')->never();

        $this->ssl->result();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'ssl',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['grade' => 'A+',
                                                                                             'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                                                             'valid' => false,
                                                                                             'valid_from' => '12345',
                                                                                             'valid_to' => '67890',
                                                                                             'revoked' => false,
                                                                                             'expires_soon' => true,
                                                                                             'issuer' => 'EyeCA']
                                                                     )]);

        $this->assertCount(1, DB::table('eyewitness_io_history_monitors')->where('type', 'ssl')->get());

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'ssl_'.$domain,
                                                            'healthy' => 0]);
    }

    public function test_detects_grade_change()
    {
        $domain = 'http://example.com';
        config(['eyewitness.application_domains' => [$domain]]);

        Cache::shouldReceive('has')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn(true);
        Cache::shouldReceive('pull')->with('eyewitness_ssl_job_id_http://example.com')->once()->andReturn('job123');

        factory(History::class)->create(['meta' => $domain,
                                         'record' => ['grade' => 'A+',
                                                      'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                      'valid' => true,
                                                      'valid_from' => '12345',
                                                      'valid_to' => '67890',
                                                      'revoked' => false,
                                                      'expires_soon' => true,
                                                      'issuer' => 'EyeCA']]);

        $this->api->shouldReceive('sslResult')->with('job123')->once()->andReturn(['results' => ['grade' => 'B'],
                                                                            'internals' => ['short_id' => 'abcdef'],
                                                                            'certificates' => ['information' => [0 => [
                                                                                'valid_now' => true,
                                                                                'valid_from' => '12345',
                                                                                'valid_to' => '67890',
                                                                                'revoked' => false,
                                                                                'expires_soon' => true,
                                                                                'issuer_cn' => 'EyeCA'
                                                                            ]]]]);

        $this->notifier->shouldReceive('alert')->with(GradeChange::class)->once();
        Log::shouldReceive('error')->never();

        $this->ssl->result();

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'ssl',
                                                                    'meta' => $domain,
                                                                    'record' => json_encode(['grade' => 'B',
                                                                                             'results_url' => 'https://www.htbridge.com/ssl/?id=abcdef',
                                                                                             'valid' => true,
                                                                                             'valid_from' => '12345',
                                                                                             'valid_to' => '67890',
                                                                                             'revoked' => false,
                                                                                             'expires_soon' => true,
                                                                                             'issuer' => 'EyeCA']
                                                            )]);

        $this->assertCount(1, DB::table('eyewitness_io_history_monitors')->where('type', 'ssl')->get());

        $this->assertDatabaseMissing('eyewitness_io_statuses', ['monitor' => 'ssl_'.$domain]);
    }
}
