<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-audit');

        $logs = AuditLog::query()->with('user')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->query('event')))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->query('type')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'events' => config('audit.events'),
            'types' => config('audit.labels'),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
