<?php

namespace Eyewitness\Eye\Test\Tools;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Scheduler;
use Eyewitness\Eye\Monitors\Custom;
use Eyewitness\Eye\Repo\History\Database;
use Eyewitness\Eye\Repo\History\Scheduler as History;
use Eyewitness\Eye\Tools\ChartTransformer;
use Eyewitness\Eye\Repo\History\Custom as CustomHistory;

class ChartTransformerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_generates_scheduler_chart_data()
    {
        $scheduler = factory(Scheduler::class)->create();
        factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => 2]);
        factory(History::class)->create(['scheduler_id' => $scheduler->id, 'time_to_run' => 6]);

        $transformer = new ChartTransformer;

        $data = $transformer->generateScheduler($scheduler);

        $this->assertCount(21, $data['day']);
        $this->assertCount(21, $data['count']);
        $this->assertEquals(4, $data['count'][20]);
    }

    public function test_generates_custom_chart_data()
    {
        $witness = resolve(MyMock::class);
        factory(CustomHistory::class)->create(['value' => 2, 'meta' => $witness->getSafeName()]);
        factory(CustomHistory::class)->create(['value' => 6, 'meta' => $witness->getSafeName()]);

        $transformer = new ChartTransformer;

        $data = $transformer->generateCustom($witness);

        $this->assertCount(21, $data['day']);
        $this->assertCount(21, $data['count']);
        $this->assertEquals(4, $data['count'][20]);
    }

    public function test_generates_database_chart_data()
    {
        factory(Database::class)->create(['meta' => 'example', 'value' => 4]);
        factory(Database::class)->create(['meta' => 'example', 'value' => 2]);

        $transformer = new ChartTransformer;

        $data = $transformer->generateDatabase('example');

        $this->assertCount(21, $data['day']);
        $this->assertCount(21, $data['count']);
        $this->assertEquals(3, $data['count'][20]);
    }
}



class MyMock extends Custom {
    public function run()
    {
        //
    }
}
