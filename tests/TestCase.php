<?php

namespace Eyewitness\Eye\Test;

use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;
use Eyewitness\Eye\EyeServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The base API route to use while testing the application.
     *
     * @var string
     */
    protected $api;

    /**
     * The base URI route to use while testing the application.
     *
     * @var string
     */
    protected $home;

    /**
     * The app_token to use while testing the application.
     *
     * @var string
     */
    protected $app_token;

    /**
     * The secret_key to use while testing the application.
     *
     * @var string
     */
    protected $secret_key;

    /**
     * The auth string to authorize routes.
     *
     * @var string
     */
    protected $auth;

    /**
     * Setup the application.
     *
     * @return void
     */
    public function setUp(): void
    {
        if (! defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        parent::setUp();

        $this->home = config('eyewitness.base_uri');
        $this->app_token = config('eyewitness.app_token');
        $this->secret_key = config('eyewitness.secret_key');

        $this->withFactories(__DIR__.'/../database/factories');
    }

    public function tearDown(): void
    {
        $config = app('config');

        parent::tearDown();

        app()->instance('config', $config);
    }

    /**
     * Add the Eyewitness Service Provider to the bootstrapped application.
     *
     * @eturn array
     */
    protected function getPackageProviders($app)
    {
        return [EyeServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

}
