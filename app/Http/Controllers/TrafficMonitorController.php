<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class TrafficMonitorController extends Controller
{
    public function index(): View
    {
        $reportUrl = config('services.looker_studio.traffic_report_url');

        return view('traffic-monitor.index', compact('reportUrl'));
    }
}
