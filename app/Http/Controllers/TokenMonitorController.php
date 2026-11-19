<?php

namespace App\Http\Controllers;

use App\Models\AiTokenLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TokenMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'until' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:from'],
        ]);

        $query = AiTokenLog::query()
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['until'] ?? null, fn ($query, $until) => $query->where('created_at', '<=', $until.':59'));

        $totalTokens = (int) (clone $query)->sum('tokens');
        $totalRequests = (clone $query)->count();
        $bySource = (clone $query)->selectRaw('source, COUNT(*) as requests, SUM(tokens) as tokens')
            ->groupBy('source')->orderByDesc('tokens')->get();
        $byProvider = (clone $query)->selectRaw('provider, model, COUNT(*) as requests, SUM(tokens) as tokens')
            ->groupBy('provider', 'model')->orderByDesc('tokens')->get();
        $usages = $query->with('user:id,name,email')->latest()->paginate(20)->withQueryString();

        return view('token-monitor.index', compact('filters', 'totalTokens', 'totalRequests', 'bySource', 'byProvider', 'usages'));
    }
}
