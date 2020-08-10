<?php

namespace Eyewitness\Eye;

use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Queue\Worker;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Route;
use Eyewitness\Eye\Queue\WorkerLegacy;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\ServiceProvider;
use Eyewitness\Eye\Commands\PollCommand;
use Eyewitness\Eye\Tools\BladeDirectives;
use Eyewitness\Eye\Commands\DebugCommand;
use Eyewitness\Eye\Commands\PruneCommand;
use Eyewitness\Eye\Commands\CustomCommand;
use Eyewitness\Eye\Commands\InstallCommand;
use Illuminate\Console\Scheduling\Schedule;
use Eyewitness\Eye\Commands\RegenerateCommand;
use Eyewitness\Eye\Commands\WitnessMakeCommand;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Eyewitness\Eye\Http\Middleware\Authenticate;
use Eyewitness\Eye\Commands\Monitors\SslCommand;
use Eyewitness\Eye\Commands\Monitors\DnsCommand;
use Eyewitness\Eye\Commands\BackgroundRunCommand;
use Eyewitness\Eye\Commands\Framework\WorkCommand;
use Eyewitness\Eye\Commands\Monitors\ComposerCommand;
use Eyewitness\Eye\Commands\Framework\LegacyWorkCommand;
use Eyewitness\Eye\Commands\Framework\ScheduleRunCommand;

class EyeServiceProvider extends ServiceProvider
{
    /**
     * The eye instance.
     *
     * @var \Eyewitness\Eye\Eye;
     */
    protected $eye;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eyewitness.php', 'eyewitness');

        $this->app->singleton(Eye::class);
    }

    /**
     * Bootstrap the package.
     *
     * @return void
     */
    public function boot()
    {
        $this->eye = app(Eye::class);

        $this->loadTools();
        $this->loadRoutes();
        $this->loadViews();
        $this->loadConsole();
    }

    /**
     * Load the required tools.
     *
     * @return void
     */
    protected function loadTools()
    {
        (new BladeDirectives)->load();
    }

    /**
     * Load the package routes.
     *
     * @return void
     */
    protected function loadRoutes()
    {
        if ($this->eye->laravelVersionIs('>=', '5.4.0')) {
            Route::aliasMiddleware('eyewitness_auth', Authenticate::class);
        } else {
            Route::middleware('eyewitness_auth', Authenticate::class);
        }

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/web.php';
            require __DIR__.'/../routes/assets.php';
            require __DIR__.'/../routes/api.php';
            require __DIR__.'/../routes/auth.php';
        }
    }

    /**
     * Load the package views.
     *
     * @return void
     */
    protected function loadViews()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'eyewitness');
    }

    /**
     * Load the console.
     *
     * @return void
     */
    protected function loadConsole()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/eyewitness.php' => config_path('eyewitness.php')]);

        if ($this->eye->laravelVersionIs('>=', '5.4.0')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        } else {
            $this->publishes([__DIR__.'/../database/migrations/' => database_path('migrations')], 'eyewitness');
        }

        $this->commands([BackgroundRunCommand::class,
                         WitnessMakeCommand::class,
                         RegenerateCommand::class,
                         ComposerCommand::class,
                         InstallCommand::class,
                         CustomCommand::class,
                         DebugCommand::class,
                         PruneCommand::class,
                         PollCommand::class,
                         SslCommand::class,
                         DnsCommand::class]);

        $this->loadEyewitnessSchedules();
        $this->loadSchedulerMonitor();
        $this->loadQueueMonitor();
    }

    /**
     * Load the eyewitness schedules into the scheduler. We use the config seeds to help spread
     * workload across different times for every installation. This is kind to the servers, and
     * to the 3rd party APIs.
     *
     * @return void
     */
    protected function loadEyewitnessSchedules()
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            if ($this->eye->laravelVersionIs('>=', '5.2.32') && config('eyewitness.enable_scheduler_background')) {
                $schedule->command('eyewitness:poll')->cron('* * * * *')->runInBackground();

                if (config('eyewitness.monitor_database')) {
					$schedule->command('eyewitness:poll-database')->cron('0 0 * * *')->runInBackground();
				}

                if ($this->eye->getCustomWitnesses()->count() > 0) {
					$schedule->command('eyewitness:custom')->cron('* * * * *')->runInBackground();
				}

                $schedule->command('eyewitness:prune')->cron('56 1 * * *')->runInBackground();
            } else {
                $schedule->command('eyewitness:poll')->cron('* * * * *');

				if (config('eyewitness.monitor_database')) {
					$schedule->command('eyewitness:poll-database')->cron('0 0 * * *');
				}

				if ($this->eye->getCustomWitnesses()->count() > 0) {
					$schedule->command('eyewitness:custom')->cron('* * * * *');
				}

                $schedule->command('eyewitness:prune')->cron('56 1 * * *');
            }

            if (config('eyewitness.monitor_ssl')) {
                if ($this->eye->laravelVersionIs('>=', '5.2.32') && config('eyewitness.enable_scheduler_background')) {
                    $schedule->command('eyewitness:monitor-ssl')->cron($this->eye->getMinuteSeed(1).' * * * *')->runInBackground();
                    $schedule->command('eyewitness:monitor-ssl --result')->cron($this->eye->getMinuteSeed(31).' * * * *')->runInBackground();
                } else {
                    $schedule->command('eyewitness:monitor-ssl')->cron($this->eye->getMinuteSeed(1).' * * * *');
                    $schedule->command('eyewitness:monitor-ssl --result')->cron($this->eye->getMinuteSeed(31).' * * * *');
                }
            }

            if (config('eyewitness.monitor_dns')) {
                if ($this->eye->laravelVersionIs('>=', '5.2.32') && config('eyewitness.enable_scheduler_background')) {
                    $schedule->command('eyewitness:monitor-dns')->cron($this->eye->getMinuteSeed(10).' * * * *')->runInBackground();
                } else {
                    $schedule->command('eyewitness:monitor-dns')->cron($this->eye->getMinuteSeed(10).' * * * *');
                }
            }

            if (config('eyewitness.monitor_composer')) {
                if ($this->eye->laravelVersionIs('>=', '5.2.32') && config('eyewitness.enable_scheduler_background')) {
                    $schedule->command('eyewitness:monitor-composer')->cron($this->eye->getMinuteSeed(20).' '.$this->eye->getHourSeed().' * * *')->runInBackground();
                } else {
                    $schedule->command('eyewitness:monitor-composer')->cron($this->eye->getMinuteSeed(20).' '.$this->eye->getHourSeed().' * * *');
                }
            }
        });
    }

    /**
     * Load the scheduler monitor. This extends the default schedule:run command
     * and extends it with our version, providing the ability to automatically
     * ping and heartbeat as it is running.
     *
     * @return void
     */
    protected function loadSchedulerMonitor()
    {
        if (config('eyewitness.monitor_scheduler')) {
            $this->app->extend('Illuminate\Console\Scheduling\ScheduleRunCommand', function () {
                return new ScheduleRunCommand();
            });
        }
    }

    /**
     * Load the queue monitor.
     *
     * @return void
     */
    protected function loadQueueMonitor()
    {
        if (! config('eyewitness.monitor_queue')) {
            return;
        }

        $this->registerFailingQueueHandler();
        $this->registerQueueWorker();
        $this->registerWorkCommand();
    }

    /**
     * Register a failing queue hander. We need to take the Laravel version into
     * account, because <=5.1 fires a different failing event to >=5.2.
     *
     * @return void
     */
    protected function registerFailingQueueHandler()
    {
        if ($this->eye->laravelVersionIs('>=', '5.2.0')) {
            app(QueueManager::class)->failing(function (JobFailed $e) {
                $this->eye->queue()->failedQueue($e->connectionName,
                                                      $e->job->resolveName(),
                                                      $e->job->getQueue());
            });
        } else {
            app(QueueManager::class)->failing(function ($connection, $job, $data) {
                $this->eye->queue()->failedQueue($connection,
                                                      $this->eye->queue()->resolveLegacyName($job),
                                                      $job->getQueue());
            });
        }
    }

    /**
     * Register a new queue worker. This allows us to capture heartbeats of
     * the queue actually working. We need to take the Laravel version into
     * account, because <=5.2 uses a different worker construct compared
     * to >=5.3.
     *
     * @return void
     */
    protected function registerQueueWorker()
    {
        $this->app->singleton('queue.worker', function () {
            if ($this->eye->laravelVersionIs('>=', '5.3.0')) {
                return new Worker($this->app['queue'], $this->app['events'], $this->app[ExceptionHandler::class], function () { return app()->isDownForMaintenance(); });
            } else {
                return new WorkerLegacy($this->app['queue'], $this->app['queue.failer'], $this->app['events']);
            }
        });
    }

    /**
     * Register a new queue:work command.
     *
     * @return void
     */
    protected function registerWorkCommand()
    {
        $this->app->extend('command.queue.work', function () {
            if ($this->eye->laravelVersionIs('>=', '5.3.0')) {
                return new WorkCommand($this->app['queue.worker'], $this->app['cache.store']);
            } else {
                return new LegacyWorkCommand($this->app['queue.worker']);
            }
        });
    }
}
