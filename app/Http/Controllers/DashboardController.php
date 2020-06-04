<?php

namespace Eyewitness\Eye\Http\Controllers;

use Eyewitness\Eye\Eye;
use Eyewitness\Eye\Repo\Queue;
use Eyewitness\Eye\Repo\Statuses;
use Illuminate\Routing\Controller;
use Eyewitness\Eye\Repo\Scheduler;
use Eyewitness\Eye\Tools\ChartTransformer;
use Eyewitness\Eye\Repo\Notifications\History;

class DashboardController extends Controller
{
    /**
     * The main Eyewitness dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('eyewitness::dashboard.index')->withEye(app(Eye::class))
                                                  ->withTransformer(new ChartTransformer)
                                                  ->withNotifications(History::where('created_at', '>', now()->subDays(30))->orderBy('acknowledged')->orderBy('created_at', 'desc')->get())
                                                  ->withStatuses(Statuses::all())
                                                  ->withQueues(Queue::all())
                                                  ->withSchedulers(Scheduler::all());
    }
}
