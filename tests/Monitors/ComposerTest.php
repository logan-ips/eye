<?php

namespace Eyewitness\Eye\Test\Monitors;

use Mockery;
use Eyewitness\Eye\Api;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Statuses;
use Eyewitness\Eye\Monitors\Composer;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Messages\Composer\Risk;
use Eyewitness\Eye\Notifications\Messages\Composer\Safe;

class ComposerTest extends TestCase
{
    protected $notifier;

    protected $composer;

    protected $api;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->notifier = Mockery::mock(Notifier::class);
        $this->app->instance(Notifier::class, $this->notifier);

        $this->api = Mockery::mock(Api::class);
        $this->app->instance(Api::class, $this->api);

        $this->composer = resolve(Composer::class);
    }

    public function test_handles_first_status_healthy()
    {
        $this->api->shouldReceive('composer')->once()->andReturn([]);

        $this->notifier->shouldReceive('alert')->never();

        $this->composer->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'composer',
                                                            'healthy' => 1]);
    }

    public function test_handles_first_status_sick()
    {
        $this->api->shouldReceive('composer')->once()->andReturn($this->getBadResult());

        $this->notifier->shouldReceive('alert')->never();

        $this->composer->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'composer',
                                                            'healthy' => 0]);
    }

    public function test_handles_turning_to_healthy()
    {
        factory(Statuses::class)->create(['monitor' => 'composer', 'healthy' => 0]);

        $this->api->shouldReceive('composer')->once()->andReturn([]);

        $this->notifier->shouldReceive('alert')->with(Safe::class)->once();

        $this->composer->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'composer',
                                                            'healthy' => 1]);
    }

    public function test_handles_turning_to_sick()
    {
        factory(Statuses::class)->create(['monitor' => 'composer', 'healthy' => 1]);

        $this->api->shouldReceive('composer')->once()->andReturn($this->getBadResult());

        $this->notifier->shouldReceive('alert')->with(Risk::class)->once();

        $this->composer->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'composer',
                                                            'healthy' => 0]);
    }

    protected function getBadResult()
    {
        return ['test/package' => [
                'version' => '2.0.0',
                'advisories' => [
                    'test/pacakge/2017-05-09.yaml' => [
                        'title' => 'Example of Composer Problem',
                        'link' => 'https://example.com'
        ]]]];
    }
}
