<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['level', 'channel', 'search', 'date_from', 'date_to']);

        $query = SystemLog::query()
            ->with('user:id,name')
            ->when($request->filled('level'), fn ($q) => $q->where('level', (string) $request->input('level')))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', (string) $request->input('channel')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes((string) $request->input('search'), '%_\\').'%';

                $q->where(function ($inner) use ($term) {
                    $inner->where('message', 'like', $term)
                        ->orWhere('url', 'like', $term)
                        ->orWhere('ip_address', 'like', $term);
                });
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('logged_at', '>=', (string) $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('logged_at', '<=', (string) $request->input('date_to')))
            ->orderByDesc('logged_at')
            ->orderByDesc('id');

        return Inertia::render('admin/system-logs/index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'levels' => SystemLog::query()->distinct()->orderBy('level')->pluck('level')->values(),
            'channels' => SystemLog::query()->distinct()->orderBy('channel')->pluck('channel')->values(),
            'filters' => $filters,
        ]);
    }
}
