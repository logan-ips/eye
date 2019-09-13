<?php

namespace Eyewitness\Eye\Test\Commands;

use Mockery;
use Eyewitness\Eye\Api;
use Eyewitness\Eye\Test\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class DebugCommandTest extends TestCase
{
    protected $api;

    public function setUp(): void
    {
        parent::setUp();

        $this->api = Mockery::mock(Api::class);
        $this->app->instance(Api::class, $this->api);
    }

    public function test_command_displays_config()
    {
        $this->api->shouldReceive('sendTestPing')->once()->andReturn(['pass' => 'true',
                                                                      'code' => 200,
                                                                      'message' => 'okping']);

        $this->api->shouldReceive('sendTestAuthenticate')->once()->andReturn(['pass' => 'true',
                                                                              'code' => 200,
                                                                              'message' => 'okauth']);

        $this->withoutMockingConsoleOutput()->artisan('eyewitness:debug');

        $output = Artisan::output();

        $this->assertTrue(Str::contains($output, 'APP_TEST'));
        $this->assertTrue(Str::contains($output, 'SECRET_TEST'));

        $this->assertTrue(Str::contains($output, 'Status Code:'));
        $this->assertTrue(Str::contains($output, '200'));
        $this->assertTrue(Str::contains($output, 'Status Message'));
        $this->assertTrue(Str::contains($output, 'okping'));
        $this->assertTrue(Str::contains($output, 'okauth'));
    }
}
