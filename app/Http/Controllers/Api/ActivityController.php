<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Audit\ActivityFeedScope;
use App\Support\Audit\AuditLogHumanizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing([
            'roles:id,name',
            'student:id,user_id',
            'guardian:id,user_id',
        ]);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $query = AuditLog::query()->with('user:id,name');
        $scopeDescription = ActivityFeedScope::apply($query, $user);

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);
        $collection = $paginator->getCollection();
        $context = AuditLogHumanizer::warmContext($collection);

        $mapped = $collection->map(function (AuditLog $log) use ($context) {
            return [
                'id' => $log->id,
                'module' => $log->module,
                'action' => $log->action,
                'created_at' => $log->created_at?->toIso8601String(),
                ...AuditLogHumanizer::present($log, $context),
            ];
        });

        return response()->json([
            'scope_description' => $scopeDescription,
            'data' => $mapped->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
