<?php

namespace Eyewitness\Eye\Test\Scheduling;

use Carbon\Carbon;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Scheduling\RunDue;

class RunDueTest extends TestCase
{
    protected $c;

    public function setUp(): void
    {
        parent::setUp();

        $this->c = new myRunDueMockClass;
    }

    public function test_get_next_run_due()
    {
        $this->assertEquals(Carbon::now()->addDay(1)->hour(0)->minute(1)->second(0)->micro(0), $this->c->getNextRunDue('1 0 * * *', null));
    }

    public function test_get_next_check_due()
    {
        $this->assertEquals(Carbon::now()->addDay(1)->hour(0)->minute(1)->second(0)->addMinutes(5)->micro(0), $this->c->getNextCheckDue('1 0 * * *', null));
    }
}


class myRunDueMockClass
{
    use RunDue;
}
