<?php

namespace Eyewitness\Eye\Test;

use Exception;
use Eyewitness\Eye\Eye;
use Illuminate\Support\Facades\Log;

class LoggerTest extends TestCase
{
    protected $eye;

    public function setUp(): void
    {
        parent::setUp();

        $this->eye = new Eye;
    }

    public function test_can_handle_error()
    {
        Log::shouldReceive('error')->once();
        Log::shouldReceive('debug')->never();

        $this->eye->logger()->error('Test', new Exception);
    }

    public function test_debug_set_to_false_by_default()
    {
        Log::shouldReceive('error')->never();
        Log::shouldReceive('debug')->never();

        $this->eye->logger()->debug('Test', 'Example');
    }

    public function test_can_handle_debug_when_enabled()
    {
        config(['eyewitness.debug' => true]);

        Log::shouldReceive('error')->never();
        Log::shouldReceive('debug')->once();

        $this->eye->logger()->debug('Test', 'Example');
    }
}
