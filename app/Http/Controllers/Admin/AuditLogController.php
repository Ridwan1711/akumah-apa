<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogHumanizer;
use App\Support\Audit\AuditLogModules;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $allowedModules = AuditLogModules::allowedModuleNames($user);

        $moduleFilter = (string) $request->input('module', '');
        if ($moduleFilter !== '' && $allowedModules !== null && ! in_array($moduleFilter, $allowedModules, true)) {
            $moduleFilter = '';
        }

        $filters = $request->only(['user_id', 'action', 'date_from', 'date_to']);
        if ($moduleFilter !== '') {
            $filters['module'] = $moduleFilter;
        }

        $query = AuditLog::query()
            ->with('user:id,name')
            ->when($allowedModules !== null, fn ($q) => $q->whereIn('module', $allowedModules))
            ->when($moduleFilter !== '', fn ($q) => $q->where('module', $moduleFilter))
            ->when($request->user_id, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->action, fn ($q, $action) => $q->where('action', $action))
            ->when($request->date_from, fn ($q, $date) => $q->where('created_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->where('created_at', '<=', $date.' 23:59:59'))
            ->orderByDesc('created_at');

        $modulesQuery = AuditLog::query()->distinct()->pluck('module');
        if ($allowedModules !== null) {
            $modules = $modulesQuery->filter(fn ($m) => in_array($m, $allowedModules, true))->sort()->values();
        } else {
            $modules = $modulesQuery->sort()->values();
        }

        $paginator = $query->paginate(20)->withQueryString();
        $collection = $paginator->getCollection();
        $context = AuditLogHumanizer::warmContext($collection);
        $paginator->setCollection(
            $collection->map(function (AuditLog $log) use ($context) {
                return array_merge($log->toArray(), AuditLogHumanizer::present($log, $context));
            }),
        );

        return Inertia::render('admin/audit-logs/index', [
            'logs' => $paginator,
            'modules' => $modules,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'scopeDescription' => AuditLogModules::scopeDescription($user),
        ]);
    }
}
