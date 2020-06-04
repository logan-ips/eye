<?php

namespace Eyewitness\Eye\Tools;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Eyewitness\Eye\Monitors\Custom;
use Eyewitness\Eye\Repo\History\Queue;
use Eyewitness\Eye\Repo\History\Custom as CustomHistory;
use Eyewitness\Eye\Repo\History\Database;
use Eyewitness\Eye\Repo\History\Scheduler;

class ChartTransformer
{
	/**
	 * The transformed scheduler data.
	 *
	 * @var array
	 */
	protected $scheduler = [];

	/**
	 * The transformed custom witness data.
	 *
	 * @var array
	 */
	protected $custom = [];

	/**
	 * The transformed database data.
	 *
	 * @var array
	 */
	protected $database = [];

	/**
	 * The transformed queue data.
	 *
	 * @var array
	 */
	protected $queue = [];

	/**
	 * Get the scheduler data and transform it into the required format for our charts.
	 *
	 * @param  \Eyewitness\Eye\Repo\Scheduler  $scheduler
	 * @return array
	 */
	public function generateScheduler($scheduler)
	{
		if (count($this->scheduler)) {
			return $this->scheduler;
		}

		$history = Scheduler::where('scheduler_id', $scheduler->id)->get()->groupBy(function($item) {
			return $item->created_at->format('Y-m-d');
		})->map(function ($row) {
			return round($row->avg('time_to_run'), 2);
		});

		// Fill the array gaps - because the chart wont populate correctly without 0 values
		for($i=20; $i>=0; $i--) {
			$this->scheduler['day'][] = Carbon::now($scheduler->timezone)->subDay($i)->format('d');

			if (isset($history[Carbon::now($scheduler->timezone)->subDay($i)->format('Y-m-d')])) {
				$this->scheduler['count'][] = $history[Carbon::now($scheduler->timezone)->subDay($i)->format('Y-m-d')];
			} else {
				$this->scheduler['count'][] = 0;
			}
		}

		return $this->scheduler;
	}

	/**
	 * Get the witness data and transform it into the required format for our charts.
	 *
	 * @param  \Eyewitness\Eye\Monitors\Custom  $witness
	 * @return array
	 */
	public function generateCustom($witness)
	{
		if (isset($this->custom[$witness->getSafeName()])) {
			return $this->custom[$witness->getSafeName()];
		}

		$history = CustomHistory::where('created_at', '>', now()->subDays(90))->where('meta', $witness->getSafeName())->get()->groupBy(function($item) {
			return $item->created_at->format('Y-m-d');
		})->map(function ($row) {
			return round($row->filter(function($item) {
				return (! is_null($item->value));
			})->avg('value'), 2);
		});

		// Fill the array gaps - because the chart wont populate correctly without 0 values
		for($i=20; $i>=0; $i--) {
			$this->custom[$witness->getSafeName()]['day'][] = Carbon::now()->subDay($i)->format('d');

			if (isset($history[Carbon::now()->subDay($i)->format('Y-m-d')])) {
				$this->custom[$witness->getSafeName()]['count'][] = $history[Carbon::now()->subDay($i)->format('Y-m-d')];
			} else {
				$this->custom[$witness->getSafeName()]['count'][] = 0;
			}
		}

		return $this->custom[$witness->getSafeName()];
	}

	/**
	 * Get the database data and transform it into the required format for our charts.
	 *
	 * @param  \Eyewitness\Eye\Repo\Monitors\Database  $connection
	 * @return array
	 */
	public function generateDatabase($connection)
	{
		if (isset($this->database[$connection])) {
			return $this->database[$connection];
		}

		$history = Database::where('created_at', '>', now()->subDays(90))
			->where('meta', $connection)->get()->groupBy(function($item) {
				return $item->created_at->format('Y-m-d');
			})->map(function ($row) {
				return round($row->avg('value'), 2);
			});

		// Fill the array gaps - because the chart wont populate correctly without 0 values
		for($i=20; $i>=0; $i--) {
			$this->database[$connection]['day'][] = Carbon::now()->subDay($i)->format('d');

			if (isset($history[Carbon::now()->subDay($i)->format('Y-m-d')])) {
				$this->database[$connection]['count'][] = $history[Carbon::now()->subDay($i)->format('Y-m-d')];
			} else {
				$this->database[$connection]['count'][] = 0;
			}
		}

		return $this->database[$connection];
	}

	/**
	 * Get the database data and transform it into the required format for our charts.
	 *
	 * @param  \Eyewitness\Eye\Repo\Queue  $queue
	 * @return array
	 */
	public function generateQueue($queue)
	{
		if (isset($this->queue[$queue->id])) {
			return $this->queue[$queue->id];
		}

		$history = Queue::where('date', '>', now()->subDays(90))->where('queue_id', $queue->id)->get()->groupBy(function($item) {
			return $item->date->format('Y-m-d');
		})->map(function ($row) {
			return [
				'avg_wait_time' => $this->calculateAvg($row->sum('sonar_time'), $row->sum('sonar_count')),
				'avg_process_time' => $this->calculateAvg($row->sum('process_time'), $row->sum('process_count')),
				'total_process_count' => $row->sum('process_count'),
				'avg_pending_count' => round($row->avg('pending_count')),
				'idle_time' => $this->calculateTotal($row->sum('idle_time')),
				'exception_count' => $this->calculateTotal($row->sum('exception_count'))
			];
		});

		// Fill the array gaps - because the chart wont populate correctly without 0 values
		for($i=14; $i>=0; $i--) {
			$this->queue[$queue->id]['day'][] = Carbon::now()->subDay($i)->format('d');

			if (isset($history[Carbon::now()->subDay($i)->format('Y-m-d')])) {
				$this->queue[$queue->id]['avg_wait_time'][] = $history[Carbon::now()->subDay($i)->format('Y-m-d')]['avg_wait_time'];
				$this->queue[$queue->id]['avg_process_time'][] = $history[Carbon::now()->subDay($i)->format('Y-m-d')]['avg_process_time'];
				$this->queue[$queue->id]['avg_pending_count'][] = $history[Carbon::now()->subDay($i)->format('Y-m-d')]['avg_pending_count'];
				$this->queue[$queue->id]['total_process_count'][] = $history[Carbon::now()->subDay($i)->format('Y-m-d')]['total_process_count'];
				$this->queue[$queue->id]['idle_time'][] = $history[Carbon::now()->subDay($i)->format('Y-m-d')]['idle_time'];
				$this->queue[$queue->id]['exception_count'][] = $history[Carbon::now()->subDay($i)->format('Y-m-d')]['exception_count'];
			} else {
				$this->queue[$queue->id]['avg_wait_time'][] = 0;
				$this->queue[$queue->id]['avg_process_time'][] = 0;
				$this->queue[$queue->id]['avg_pending_count'][] = 0;
				$this->queue[$queue->id]['total_process_count'][] = 0;
				$this->queue[$queue->id]['idle_time'][] = 0;
				$this->queue[$queue->id]['exception_count'][] = 0;
			}
		}

		return $this->queue[$queue->id];
	}

	/**
	 * Calculate the average of the numbers, protected against divide-by-zero.
	 *
	 * @param  int  $total
	 * @param  int  $count
	 * @return int
	 */
	protected function calculateAvg($total, $count)
	{
		if ($count < 1){
			return 0;
		}

		return round($total/$count, 2);
	}

	/**
	 * Calculate the total of the number.
	 *
	 * @param  int|null  $total
	 * @return int
	 */
	protected function calculateTotal($total)
	{
		if (is_null($total) || $total < 1) {
			return 0;
		}

		return round($total);
	}
}
