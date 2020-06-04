<?php

namespace Eyewitness\Eye\Repo;

use Eyewitness\Eye\Repo\Model;
use Eyewitness\Eye\Repo\History\Scheduler as History;

class Scheduler extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'eyewitness_io_schedulers';

	/**
	 * The values to cast.
	 *
	 * @var array
	 */
	protected $casts = ['last_run' => 'datetime',
		'next_run_due' => 'datetime',
		'next_check_due' => 'datetime',
		'created_at' => 'datetime'];

	/**
	 * Get all of the scheduler history that belong to this schedule.
	 */
	public function history()
	{
		return $this->hasMany(History::class)->where('created_at', '<', now()->subDays(60))->latest();
	}
}
