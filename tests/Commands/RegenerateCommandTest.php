<?php

namespace Eyewitness\Eye\Test\Commands;

use Mockery;
use Eyewitness\Eye\Test\TestCase;
use Illuminate\Support\Facades\Artisan;

class RegenerateCommandTest extends TestCase
{
    protected $command;

    public function setUp(): void
    {
        parent::setUp();

        $this->command = Mockery::mock('\Eyewitness\Eye\Commands\RegenerateCommand[getEnvFile,writeEnvFile,generateRandom]');
        $this->app->instance(RegenerateCommand::class, $this->command);

        $this->app['Illuminate\Contracts\Console\Kernel']->registerCommand($this->command);
    }

    public function test_command_has_confirmation_check()
    {
        $this->command->shouldReceive('getEnvFile')->never();
        $this->command->shouldReceive('writeEnvFile')->never();
        $this->command->shouldReceive('generateRandom')->never();

        $this->withoutMockingConsoleOutput()->artisan('eyewitness:regenerate', ['--no-interaction' => true]);

        $output = Artisan::output();

        $this->assertStringContainsString('Aborted.', $output);
    }

    public function test_command_inserts_when_no_previous_key_exists()
    {
        $this->command->shouldReceive('generateRandom')->with(16)->once()->andReturn('TOKEN123');
        $this->command->shouldReceive('generateRandom')->with(32)->once()->andReturn('KEY123');

        $this->command->shouldReceive('getEnvFile')->times(6)->andReturn('APP_TEST=EXAMPLE');
        $this->command->shouldReceive('writeEnvFile')->with('APP_TEST=EXAMPLE'.PHP_EOL.'EYEWITNESS_APP_TOKEN=TOKEN123')->once();
        $this->command->shouldReceive('writeEnvFile')->with('APP_TEST=EXAMPLE'.PHP_EOL.'EYEWITNESS_SECRET_KEY=KEY123')->once();
        $this->command->shouldReceive('writeEnvFile')->with('APP_TEST=EXAMPLE'.PHP_EOL.'EYEWITNESS_SUBSCRIPTION_KEY=')->once();

        $this->withoutMockingConsoleOutput()->artisan('eyewitness:regenerate', ['--force' => true, '--no-interaction' => true]);

        $output = Artisan::output();

        $this->assertStringContainsString('Your new Eyewitness API keys have been updated and saved to your .env file', $output);
        $this->assertStringContainsString('TOKEN123', $output);
        $this->assertStringContainsString('KEY123', $output);
    }

    public function test_command_updates_previous_keys()
    {
        $this->command->shouldReceive('generateRandom')->with(16)->once()->andReturn('TOKEN123');
        $this->command->shouldReceive('generateRandom')->with(32)->once()->andReturn('KEY123');

        config(['eyewitness.app_token' => 'OLD456',
                'eyewitness.secret_key' => 'OLD789']);

        $this->command->shouldReceive('getEnvFile')->times(2)->andReturn('APP_TEST=EXAMPLE'.PHP_EOL.
                                                                         'EYEWITNESS_APP_TOKEN=OLD456'.PHP_EOL.
                                                                         'EYEWITNESS_SECRET_KEY=OLD789');
        $this->command->shouldReceive('writeEnvFile')->with('APP_TEST=EXAMPLE'.PHP_EOL.
                                                            'EYEWITNESS_APP_TOKEN=TOKEN123'.PHP_EOL.
                                                            'EYEWITNESS_SECRET_KEY=OLD789')->once();
        $this->command->shouldReceive('getEnvFile')->times(4)->andReturn('APP_TEST=EXAMPLE'.PHP_EOL.
                                                                         'EYEWITNESS_APP_TOKEN=TOKEN123'.PHP_EOL.
                                                                         'EYEWITNESS_SECRET_KEY=OLD789');
        $this->command->shouldReceive('writeEnvFile')->with('APP_TEST=EXAMPLE'.PHP_EOL.
                                                            'EYEWITNESS_APP_TOKEN=TOKEN123'.PHP_EOL.
                                                            'EYEWITNESS_SECRET_KEY=KEY123')->once();
        $this->command->shouldReceive('writeEnvFile')->with('APP_TEST=EXAMPLE'.PHP_EOL.
                                                            'EYEWITNESS_APP_TOKEN=TOKEN123'.PHP_EOL.
                                                            'EYEWITNESS_SECRET_KEY=OLD789'.PHP_EOL.
                                                            'EYEWITNESS_SUBSCRIPTION_KEY=')->once();

        $this->withoutMockingConsoleOutput()->artisan('eyewitness:regenerate', ['--force' => true, '--no-interaction' => true]);

        $output = Artisan::output();

        $this->assertStringContainsString('Your new Eyewitness API keys have been updated and saved to your .env file', $output);
        $this->assertStringContainsString('TOKEN123', $output);
        $this->assertStringContainsString('KEY123', $output);
        $this->assertEquals('TOKEN123', config('eyewitness.app_token'));
        $this->assertEquals('KEY123', config('eyewitness.secret_key'));
    }
}
