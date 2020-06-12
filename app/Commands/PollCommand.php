<?php

namespace Eyewitness\Eye\Commands;

use Carbon\Carbon;
use Eyewitness\Eye\Eye;
use Illuminate\Console\Command;

class PollCommand extends Command
{
    /**
     * The eye instance.
     *
     * @var \Eyewitness\Eye\Eye;
     */
    protected $eye;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'eyewitness:poll {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Eyewitness.io - command to poll the server. Will be called automatically by the package.';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Create the Poll command.
     *
     * @param  \Eyewitness\Eye\Eye  $eye
     * @return void
     */
    public function __construct(Eye $eye)
    {
        parent::__construct();

        $this->eye = $eye;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (config('eyewitness.monitor_scheduler')) {
            $this->eye->scheduler()->poll();
        }

        if (config('eyewitness.monitor_queue')) {
            $this->eye->queue()->poll();
        }
		if (config('eyewitness.monitor_database')) {
			$this->eye->database()->poll(true);
		}

        if ($this->option('force')) {
            $this->eye->composer()->poll();
            $this->eye->dns()->poll();
        }

        $this->eye->debug()->poll();

        $this->info('Eyewitness poll complete.');
    }
}
