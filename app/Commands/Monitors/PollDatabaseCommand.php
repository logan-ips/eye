<?php

namespace Eyewitness\Eye\Commands\Monitors;

use Carbon\Carbon;
use Eyewitness\Eye\Eye;
use Illuminate\Console\Command;

class PollDatabaseCommand extends Command
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
    protected $signature = 'eyewitness:poll-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Eyewitness.io - command to poll the database. Will be called automatically by the package.';

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
        if (config('eyewitness.monitor_database')) {
            $this->eye->database()->poll();
        }

        $this->info('Eyewitness database poll complete.');
    }
}
