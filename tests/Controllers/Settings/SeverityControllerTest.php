<?php

namespace Eyewitness\Eye\Test\Controllers\Settings;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Notifications\Severity;

class SeverityControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_severity_does_validation()
    {
        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->post(route('eyewitness.severity.update'),
                                ['_method' => 'put',
                                'notification' => [
                                    '1' => 'high',
                                    '2' => 'medium',
                                    '3' => 'low',
                                    '4' => 'disabled',
                                    '5' => 'fake']
                                ]);

        $response->assertRedirect(route('eyewitness.settings.index').'#severity');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('eyewitness_io_notification_severities', ['id' => 1, 'severity' => 'high']);
        $this->assertDatabaseHas('eyewitness_io_notification_severities', ['id' => 2, 'severity' => 'medium']);
        $this->assertDatabaseHas('eyewitness_io_notification_severities', ['id' => 3, 'severity' => 'low']);
        $this->assertDatabaseHas('eyewitness_io_notification_severities', ['id' => 4, 'severity' => 'disabled']);

        $this->assertDatabaseMissing('eyewitness_io_notification_severities', ['id' => 5,'severity' => 'fake']);
    }
}
