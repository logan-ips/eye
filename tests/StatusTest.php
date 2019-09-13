<?php

namespace Eyewitness\Eye\Test;

use Eyewitness\Eye\Status;
use Eyewitness\Eye\Repo\Statuses;

class StatusTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_get_status()
    {
        factory(Statuses::class)->create(['monitor' => 'dns', 'healthy' => true]);
        factory(Statuses::class)->create(['monitor' => 'ssl', 'healthy' => false]);
        factory(Statuses::class)->create(['monitor' => 'other', 'healthy' => true]);

        $status = resolve(Status::class);

        $this->assertFalse($status->getStatus('ssl')->healthy);
        $this->assertTrue($status->getStatus('dns')->healthy);
        $this->assertTrue($status->getStatus('other')->healthy);
        $this->assertNull($status->getStatus('wrong'));
    }

    public function test_healthy_check_for_non_existant_monitor()
    {
        $status = resolve(Status::class);

        $this->assertFalse($status->isHealthy('ssl'));
    }

    public function test_healthy_check_for_healthly_monitor()
    {
        factory(Statuses::class)->create(['monitor' => 'ssl', 'healthy' => true]);

        $status = resolve(Status::class);

        $this->assertTrue($status->isHealthy('ssl'));
    }

    public function test_healthy_check_for_sick_monitor()
    {
        factory(Statuses::class)->create(['monitor' => 'ssl', 'healthy' => false]);

        $status = resolve(Status::class);

        $this->assertFalse($status->isHealthy('ssl'));
    }

    public function test_sick_check_for_non_existant_monitor()
    {
        $status = resolve(Status::class);

        $this->assertFalse($status->isSick('ssl'));
    }

    public function test_sick_check_for_healthly_monitor()
    {
        factory(Statuses::class)->create(['monitor' => 'ssl', 'healthy' => true]);

        $status = resolve(Status::class);

        $this->assertFalse($status->isSick('ssl'));
    }

    public function test_sick_check_for_sick_monitor()
    {
        factory(Statuses::class)->create(['monitor' => 'ssl', 'healthy' => false]);

        $status = resolve(Status::class);

        $this->assertTrue($status->isSick('ssl'));
    }

    public function test_set_status()
    {
        $status = resolve(Status::class);

        $status->setStatus('ssl', true);

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'ssl',
                                                            'healthy' => 1]);
    }

    public function test_set_healthy_status()
    {
        $status = resolve(Status::class);

        $status->setHealthy('ssl');

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'ssl',
                                                            'healthy' => 1]);
    }

    public function test_set_sick_status()
    {
        $status = resolve(Status::class);

        $status->setSick('ssl');

        $this->assertDatabaseHas('eyewitness_io_statuses', ['monitor' => 'ssl',
                                                            'healthy' => 0]);
    }
}
