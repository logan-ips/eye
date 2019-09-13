<?php

namespace Eyewitness\Eye\Test\Commands;

use Carbon\Carbon;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\History\Queue;
use Illuminate\Support\Facades\Artisan;
use Eyewitness\Eye\Repo\History\Custom;
use Eyewitness\Eye\Repo\History\Database;
use Eyewitness\Eye\Repo\History\Scheduler;
use Eyewitness\Eye\Repo\Notifications\History;

class PruneCommandTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_prune()
    {
        config(['eyewitness.days_to_keep_history' => 10]);

        $this->withoutMockingConsoleOutput()->artisan('eyewitness:prune');

        $output = Artisan::output();

        $this->assertStringContainsString('Eyewitness prune complete', $output);
    }
}
