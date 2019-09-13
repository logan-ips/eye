<?php

namespace Eyewitness\Eye\Test\Monitors;

use Mockery;
use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Statuses;
use Eyewitness\Eye\Monitors\Database;
use Eyewitness\Eye\Notifications\Notifier;
use Eyewitness\Eye\Notifications\Messages\Database\SizeOk;
use Eyewitness\Eye\Notifications\Messages\Database\Online;
use Eyewitness\Eye\Notifications\Messages\Database\Offline;
use Eyewitness\Eye\Notifications\Messages\Database\SizeSmall;
use Eyewitness\Eye\Notifications\Messages\Database\SizeLarge;

class DatabaseTest extends TestCase
{
    protected $notifier;

    protected $database;

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);

        $this->notifier = Mockery::mock(Notifier::class);
        $this->app->instance(Notifier::class, $this->notifier);

        $this->database = Mockery::mock('Eyewitness\Eye\Monitors\Database[getDatabases]', [resolve(Eye::class)])->shouldAllowMockingProtectedMethods();

    }

    public function test_can_get_database_details()
    {
        config(['eyewitness.database_connections' => [[
            'connection' => 'testbench',
            'alert_greater_than_mb' => 5,
            'alert_less_than_mb' => 3
        ]]]);

        $result = resolve(Database::class)->getDatabases();

        $this->assertEquals('testbench', $result[0]['connection']);
        $this->assertEquals('5', $result[0]['alert_greater_than_mb']);
        $this->assertEquals('3', $result[0]['alert_less_than_mb']);
        $this->assertTrue($result[0]['status']);
    }

    public function test_handles_initial_database_check_offline()
    {
        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 0,
            'alert_less_than_mb' => 0,
            'driver' => 'mysql',
            'status' => false,
            'size' => 4,
        ]]);

        $this->notifier->shouldReceive('alert')->never();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_status_test',
                                                            'healthy' => 0]);
    }

    public function test_handles_database_going_offline()
    {
        factory(Statuses::class)->create(['monitor' => 'database_status_test',
                                          'healthy' => 1]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 0,
            'alert_less_than_mb' => 0,
            'driver' => 'mysql',
            'status' => false,
            'size' => 4,
        ]]);

        $this->notifier->shouldReceive('alert')->with(Offline::class)->once();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_status_test',
                                                            'healthy' => 0]);
    }

    public function test_handles_database_already_offline()
    {
        factory(Statuses::class)->create(['monitor' => 'database_status_test',
                                          'healthy' => 0]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 0,
            'alert_less_than_mb' => 0,
            'driver' => 'mysql',
            'status' => false,
            'size' => 4,
        ]]);

        $this->notifier->shouldReceive('alert')->never();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_status_test',
                                                            'healthy' => 0]);
    }

    public function test_handles_initial_database_check_online()
    {
        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 0,
            'alert_less_than_mb' => 0,
            'driver' => 'mysql',
            'status' => true,
            'size' => 4,
        ]]);

        $this->notifier->shouldReceive('alert')->never();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_status_test',
                                                            'healthy' => 1]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 4]);
    }

    public function test_handles_database_going_online()
    {
        factory(Statuses::class)->create(['monitor' => 'database_status_test',
                                          'healthy' => 0]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 0,
            'alert_less_than_mb' => 0,
            'driver' => 'mysql',
            'status' => true,
            'size' => 4,
        ]]);

        $this->notifier->shouldReceive('alert')->with(Online::class)->once();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_status_test',
                                                            'healthy' => 1]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 4]);
    }

    public function test_handles_database_already_online()
    {
        factory(Statuses::class)->create(['monitor' => 'database_status_test',
                                          'healthy' => 1]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 0,
            'alert_less_than_mb' => 0,
            'driver' => 'mysql',
            'status' => true,
            'size' => 4,
        ]]);

        $this->notifier->shouldReceive('alert')->never();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_status_test',
                                                            'healthy' => 1]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 4]);
    }

    public function test_handles_database_size_normal()
    {
        factory(Statuses::class)->create(['monitor' => 'database_size_test',
                                          'healthy' => 1]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 10,
            'alert_less_than_mb' => 5,
            'driver' => 'mysql',
            'status' => true,
            'size' => 7,
        ]]);

        $this->notifier->shouldReceive('alert')->never();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_size_test',
                                                            'healthy' => 1]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 7]);
    }

    public function test_handles_database_size_returning_to_normal()
    {
        factory(Statuses::class)->create(['monitor' => 'database_size_test',
                                          'healthy' => 0]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 10,
            'alert_less_than_mb' => 5,
            'driver' => 'mysql',
            'status' => true,
            'size' => 7,
        ]]);

        $this->notifier->shouldReceive('alert')->with(SizeOk::class)->once();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_size_test',
                                                            'healthy' => 1]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 7]);
    }

    public function test_handles_database_size_becoming_large()
    {
        factory(Statuses::class)->create(['monitor' => 'database_size_test',
                                          'healthy' => 1]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 10,
            'alert_less_than_mb' => 5,
            'driver' => 'mysql',
            'status' => true,
            'size' => 15,
        ]]);

        $this->notifier->shouldReceive('alert')->with(SizeLarge::class)->once();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_size_test',
                                                            'healthy' => 0]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 15]);
    }

    public function test_handles_database_size_already_large()
    {
        factory(Statuses::class)->create(['monitor' => 'database_size_test',
                                          'healthy' => 0]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 10,
            'alert_less_than_mb' => 5,
            'driver' => 'mysql',
            'status' => true,
            'size' => 15,
        ]]);

        $this->notifier->shouldReceive('alert')->never();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_size_test',
                                                            'healthy' => 0]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 15]);
    }

    public function test_handles_database_size_becoming_small()
    {
        factory(Statuses::class)->create(['monitor' => 'database_size_test',
                                          'healthy' => 1]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 10,
            'alert_less_than_mb' => 5,
            'driver' => 'mysql',
            'status' => true,
            'size' => 2,
        ]]);

        $this->notifier->shouldReceive('alert')->with(SizeSmall::class)->once();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_size_test',
                                                            'healthy' => 0]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 2]);
    }

    public function test_handles_database_size_already_small()
    {
        factory(Statuses::class)->create(['monitor' => 'database_size_test',
                                          'healthy' => 0]);

        $this->database->shouldReceive('getDatabases')->andReturn([[
            'connection' => 'test',
            'alert_greater_than_mb' => 10,
            'alert_less_than_mb' => 5,
            'driver' => 'mysql',
            'status' => true,
            'size' => 2,
        ]]);

        $this->notifier->shouldReceive('alert')->never();

        $this->database->poll();

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'database_size_test',
                                                            'healthy' => 0]);

        $this->assertDatabaseHas('eyewitness_io_history_monitors', ['type' => 'database',
                                                                    'meta' => 'test',
                                                                    'record' => json_encode([]),
                                                                    'value' => 2]);
    }
}
