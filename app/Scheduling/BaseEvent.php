<?php

namespace Eyewitness\Eye\Scheduling;

use Exception;
use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Repo\Scheduler;
use Eyewitness\Eye\Repo\History\Scheduler as History;
use Illuminate\Contracts\Container\Container;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Slow;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Fast;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Error;
use Eyewitness\Eye\Notifications\Messages\Scheduler\Working;
use Illuminate\Support\Str;

trait BaseEvent
{
    use RunDue;

    /**
     * The eye instance.
     *
     * @var \Eyewitness\Eye\Eye;
     */
    protected $eye;

    /**
     * Force the command to run regardless of mutex.
     *
     * @var mixed
     */
    public $ignoreMutex = false;

    /**
     * Remember if the event wanted to run in the background.
     *
     * @var mixed
     */
    public $forceRunInForeground = false;

    /**
     * Indicates if the command should only be allowed to run on one server each cron expression.
     *
     * @var bool
     */
    public $onOneServer = false;

    /**
     * The start time of the job.
     *
     * @var float
     */
    public $start;

    /**
     * The exit code of the job.
     *
     * @var string
     */
    public $exitcode;

    /**
     * The scheduler repo for this job.
     *
     * @var \Eyewitness\Eye\Repo\Scheduler
     */
    public $scheduler;

    /**
     * The scheduler history repo for this job
     *
     * @var \Eyewitness\Eye\Repo\History\Scheduler
     */
    public $history;

    /**
     * Calculate run time for this event.
     *
     * @return float
     */
    public function calculateRunTime()
    {
        return round(microtime(true) - $this->start, 4);
    }

    /**
     * Get the summary of the event for display.
     *
     * @return string
     */
    public function getSummaryForDisplay()
    {
        return $this->expression.' : '.$this->getCommandName(false);
    }

    /**
     * Get the summary of the event for display.
     *
     * @param  bool  $filtered
     * @return string
     */
    public function getCommandName($filtered = true)
    {
        if ($this->command) {
            if ($filtered) {
                return $this->filterArtisanCommand($this->command);
            } else {
                return $this->command;
            }
        }

        if ($this->description) {
            return $this->description;
        }

        if (isset($this->callback) && is_string($this->callback)) {
            return $this->callback;
        }

        return 'Unnamed Closure';
    }

    /**
     * State that the command should be forced to run regardless of mutex.
     *
     * @param  $force
     * @return $this
     */
    public function ignoreMutex($force = true)
    {
        $this->ignoreMutex = $force;

        return $this;
    }

    /**
     * State that the command should run in the foreground.
     *
     * @return $this
     */
    public function runInForeground()
    {
        $this->forceRunInForeground = true;

        return $this;
    }

    /**
     * Check if output is being captured.
     *
     * @return bool
     */
    protected function outputNotBeingCaptured()
    {
        if (is_null($this->output)) {
            return true;
        }

        if (is_callable([$this, 'getDefaultOutput'])) {
            return $this->output === $this->getDefaultOutput();
        }

        return in_array($this->output, ['NUL', '/dev/null']);
    }

    /**
     * Set everything and pass the start of the job to Eyewitness to record.
     *
     * @return void
     */
    public function recordEventStart()
    {
        try {
            $this->scheduler = Scheduler::firstOrNew(['mutex' => $this->mutexName()]);

            if (! $this->scheduler->exists) {
                $this->scheduler->fill([
                    'schedule' => $this->expression,
                    'command' => $this->getCommandName(),
                    'timezone' => $this->timezone,
                    'run_in_background' => $this->runInBackground,
                    'without_overlapping' => $this->withoutOverlapping,
                    'on_one_server' => $this->onOneServer,
                    'next_run_due' => $this->getNextRunDue($this->expression, $this->timezone),
                    'next_check_due' => $this->getNextCheckDue($this->expression, $this->timezone),
                ]);
            } else {
                $this->scheduler->fill([
                    'last_run' => date("Y-m-d H:i:s"),
                    'next_run_due' => $this->getNextRunDue($this->expression, $this->timezone),
                    'next_check_due' => $this->getNextCheckDue($this->expression, $this->timezone),
                ]);
            }
            $this->scheduler->save();

            $this->history = History::create([
                'scheduler_id' => $this->scheduler->id,
                'expected_completion' => $this->determineExpectedCompletion($this->scheduler),
            ]);
        } catch (Exception $e) {
            app(Eye::class)->logger()->error('Unable to track scheduler',
                                             $e->getMessage(),
                                             ['expresison' => $this->expression,
                                              'command_name' => $this->getCommandName()]);
        }

        $this->start = microtime(true);
        $this->exitcode = 1;
    }

    /**
     * Pass the results of the of the job to Eyewitness to record.
     *
     * @return void
     */
    public function recordEventFinish()
    {
        if (is_null($this->history)) {
            return;
        }

        $this->history->time_to_run = $this->calculateRunTime();
        $this->history->exitcode = $this->exitcode;
        $this->history->output = $this->retrieveOutput();
        $this->history->save();
    }

    /**
     * Handle any required notifications from the job.
     *
     * @return void
     */
    protected function handleNotifications()
    {
        if (($this->exitcode > 0) && $this->scheduler->alert_on_fail) {
            return app(Eye::class)->notifier()->alert(new Error(['scheduler' => $this->scheduler,
                                                                 'exitcode' => $this->exitcode]));
        }

        if (($this->scheduler->alert_run_time_greater_than > 0) && ($this->history->time_to_run > $this->scheduler->alert_run_time_greater_than)) {
            return app(Eye::class)->notifier()->alert(new Slow(['scheduler' => $this->scheduler,
                                                                'time_to_run' => $this->history->time_to_run]));
        }

        if (($this->scheduler->alert_run_time_less_than > 0) && ($this->history->time_to_run < $this->scheduler->alert_run_time_less_than)) {
            return app(Eye::class)->notifier()->alert(new Fast(['scheduler' => $this->scheduler,
                                                                'time_to_run' => $this->history->time_to_run]));
        }

        if (! $this->scheduler->healthy) {
            if (! is_null($this->scheduler->healthy)) {
                app(Eye::class)->notifier()->alert(new Working(['scheduler' => $this->scheduler]));
            }

            $this->scheduler->healthy = true;
            $this->scheduler->save();
        }
    }

    /**
     * Run the command in the foreground.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return void
     */
    protected function runCommandInForeground(Container $container)
    {
        register_shutdown_function(function () {
            $this->forgetMutex();
        });

        $this->ensureOutputIsBeingCapturedForEyewitness();

        $this->recordEventStart();

        $this->callBeforeCallbacks($container);

        try {
            $this->runForegroundProcess($container);
        } finally {
            $this->callAfterCallbacks($container);
            $this->recordEventFinish();
            $this->forgetMutex();
            $this->handleNotifications();
        }
    }

    /**
     * Get the mutex name for the scheduled command.
     *
     * @return string
     */
    public function mutexName()
    {
        if (Eye::laravelVersionIs('>=', '5.2.0')) {
            return 'framework'.DIRECTORY_SEPARATOR.'schedule-'.sha1($this->expression.$this->command);
        }

        return 'framework/schedule-'.md5($this->expression.$this->command);
    }

    /**
     * Get the mutex path for the scheduled command.
     *
     * @return string
     */
    public function mutexPath()
    {
        return storage_path($this->mutexName());
    }

    /**
     * Run the correct shutdown function based upon the Laravel version.
     *
     * @return void
     */
    public function forgetMutex()
    {
        if (Eye::laravelVersionIs('<', '5.4.0')) {
            if (file_exists($this->mutexPath())) {
                unlink($this->mutexPath());
            }
        } elseif (Eye::laravelVersionIs('<', '5.4.17')) {
            $this->cache->forget($this->mutexName());
        } else {
            $this->mutex->forget($this);
        }
    }


    /**
     * Run the correct mutex based upon the Laravel version.
     *
     * @return bool
     */
    protected function setMutex()
    {
        if (Eye::laravelVersionIs('<', '5.4.0')) {
            return touch($this->mutexPath());
        } elseif (Eye::laravelVersionIs('<', '5.4.17')) {
            return $this->cache->add($this->mutexName(), true, 1440);
        }

        return $this->mutex->create($this);
    }

    /**
     * Get the command name without the junk.
     *
     * @param  string  $command
     * @return string
     */
    protected function filterArtisanCommand($command)
    {
        $parts = explode(" ", $command);

        if (isset($parts[2])) {
            if (Str::contains(strtolower($parts[0]), 'php') && Str::contains(strtolower($parts[1]), 'artisan')) {
                unset($parts[0]);
                unset($parts[1]);
                return implode(" ", $parts);
            }
        }

        return $command;
    }
}
