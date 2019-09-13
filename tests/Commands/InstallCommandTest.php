<?php

namespace Eyewitness\Eye\Test\Commands;

use Mockery;
use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Monitors\Scheduler;
use Illuminate\Support\Facades\Artisan;
use Eyewitness\Eye\Commands\PollCommand;
use Eyewitness\Eye\Commands\RegenerateCommand;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Illuminate\Database\Console\Migrations\MigrateCommand;

class InstallCommandTest extends TestCase
{
    protected $regenerateCommand;

    protected $migrateCommand;

    protected $publishCommand;

    protected $pollCommand;

    protected $scheduler;

    public function setUp(): void
    {
        parent::setUp();

        $this->regenerateCommand = Mockery::mock('\Eyewitness\Eye\Commands\RegenerateCommand[handle]');
        $this->app->instance(RegenerateCommand::class, $this->regenerateCommand);
        $this->app['Illuminate\Contracts\Console\Kernel']->registerCommand($this->regenerateCommand);

        $this->pollCommand = Mockery::mock('\Eyewitness\Eye\Commands\PollCommand[handle]', [resolve(Eye::class)]);
        $this->app->instance(PollCommand::class, $this->pollCommand);
        $this->app['Illuminate\Contracts\Console\Kernel']->registerCommand($this->pollCommand);

        $this->publishCommand = Mockery::mock('\Eyewitness\Eye\Test\Commands\MockVendorPublishCommand[handle]');
        $this->app->instance(VendorPublishCommand::class, $this->publishCommand);
        $this->app['Illuminate\Contracts\Console\Kernel']->registerCommand($this->publishCommand);

        $this->migrateCommand = Mockery::mock('\Eyewitness\Eye\Test\Commands\MockMigrationCommand[handle]');
        $this->app->instance(MigrateCommand::class, $this->migrateCommand);
        $this->app['Illuminate\Contracts\Console\Kernel']->registerCommand($this->migrateCommand);

        $this->scheduler = Mockery::mock(Scheduler::class);
        $this->app->instance(Scheduler::class, $this->scheduler);
    }

    public function test_command_checks_if_already_installed()
    {
        config(['eyewitness.app_token' => 'OLD456',
                'eyewitness.secret_key' => 'OLD789']);

        $this->regenerateCommand->shouldReceive('handle')->never();
        $this->migrateCommand->shouldReceive('handle')->never();
        $this->publishCommand->shouldReceive('handle')->never();
        $this->pollCommand->shouldReceive('handle')->never();
        $this->scheduler->shouldReceive('install')->never();

        $this->withoutMockingConsoleOutput()->artisan('eyewitness:install', ['--no-interaction' => true]);

        $output = Artisan::output();

        $this->assertStringContainsString('It appears that the Eyewitness package has already been installed.', $output);
        $this->assertStringContainsString('Aborted. No changes were made.', $output);
    }

    public function test_command_installs()
    {
        config(['eyewitness.app_token' => '',
                'eyewitness.secret_key' => '']);

        $this->regenerateCommand->shouldReceive('handle')->once();
        $this->migrateCommand->shouldReceive('handle')->once();
        $this->publishCommand->shouldReceive('handle')->once();
        $this->pollCommand->shouldReceive('handle')->once();
        $this->scheduler->shouldReceive('install')->once();

        $this->withoutMockingConsoleOutput()->artisan('eyewitness:install', ['--no-interaction' => true]);

        $output = Artisan::output();

        $this->assertStringContainsString('Installing and configuring Eyewitness.io package...', $output);
        $this->assertStringContainsString('You can now visit', $output);
        $this->assertStringContainsString(config('app.url').'/'.config('eyewitness.base_uri'), $output);
        $this->assertStringContainsString(route('eyewitness.login'), $output);
    }
}

class MockMigrationCommand extends \Illuminate\Console\Command {
    protected $signature = 'migrate';

    public function handle() {}
}

class MockVendorPublishCommand extends \Illuminate\Console\Command {
    protected $signature = 'vendor:publish
                    {--force : Overwrite any existing files.}
                    {--provider= : The service provider that has assets you want to publish.}';

    public function handle() {}
}
