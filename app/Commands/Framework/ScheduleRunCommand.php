<?php

namespace Eyewitness\Eye\Commands\Framework;

use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Scheduling\CustomEvents;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\ScheduleRunCommand as OriginalScheduleRunCommand;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler as OriginalExceptionHandler;

class ScheduleRunCommand extends OriginalScheduleRunCommand
{
    use CustomEvents;

    /**
     * Determine if any events ran.
     *
     * @var bool
     */
    protected $eventsRan = false;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire(Schedule $schedule, Dispatcher $dispatcher)
    {
        $this->runScheduledEvents($schedule, $dispatcher);

        if (! $this->eventsRan) {
            $this->info('No scheduled commands are ready to run.');
        }
    }

    /**
     * Run the scheduled events. This is an extension of the original run command
     * but allows us to insert our custom event and tracking.
     *
     * @return void
     */
    protected function runScheduledEvents(Schedule $schedule, Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        foreach ($schedule->dueEvents($this->laravel) as $event) {
            $event = $this->convertEvent($event);

            if ($this->canAccessFiltersPass($event) && (! $event->filtersPass($this->laravel))) {
                continue;
            }

            if ($event->onOneServer) {
                $this->runSingleServerEvent($schedule, $event);
            } else {
                $this->runEvent($event);
            }
        }
    }

    /**
     * Allow for simulatenous support of Laravel 5.5 and <=5.4 due to changes in
     * PR https://github.com/laravel/framework/pull/19827.
     *
     * @return mixed
     */
    public function handle(Schedule $schedule, Dispatcher $dispatcher, OriginalExceptionHandler $handler)
    {
        return $this->fire($schedule, $dispatcher);
    }

    /**
     * Due to PR https://github.com/laravel/framework/pull/11646/ we need to check
     * if the filtersPass() method can be called or not.
     *
     * @param  $event
     * @return bool
     */
    protected function canAccessFiltersPass($event)
    {
        return is_callable([$event, 'filtersPass']);
    }

    /**
     * Run the given event.
     *
     * @param  \Illuminate\Support\Collection  $event
     * @return void
     */
    public function runEvent($event)
    {
        $this->line('<info>Running scheduled command:</info> '.$event->getSummaryForDisplay());

        $this->dispatcher->dispatch(new ScheduledTaskStarting($event));

        $start = microtime(true);

        $event->run($this->laravel);

        $this->dispatcher->dispatch(new ScheduledTaskFinished(
            $event,
            round(microtime(true) - $start, 2)
        ));

        $this->eventsRan = true;
    }
}
