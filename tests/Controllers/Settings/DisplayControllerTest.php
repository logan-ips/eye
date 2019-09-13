<?php

namespace Eyewitness\Eye\Test\Controllers\Settings;

use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Repo\Notifications\Severity;

class DisplayControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput()->artisan('migrate', ['--database' => 'testbench']);
    }

    public function test_settings_page_loads_with_no_severities()
    {
        Severity::truncate();

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.settings.index'));

        $response->assertStatus(200);
        $response->assertSee('No severity settings found');
    }

    public function test_settings_page_loads_and_displays_severities_with_spaces_between_caps()
    {
        factory(Severity::class)->create(['notification' => 'ExampleNotifcation']);

        $response = $this->withSession(['eyewitness:auth' => 1])
                         ->get(route('eyewitness.settings.index'));

        $response->assertStatus(200);
        $response->assertSee('Example Notifcation');
    }
}
