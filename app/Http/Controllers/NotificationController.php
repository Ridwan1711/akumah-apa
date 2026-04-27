<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'in:all,unread_only'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $mode = $validated['mode'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = $request->user()
            ->notifications()
            ->latest();

        if ($mode === 'unread_only') {
            $query->whereNull('read_at');
        }

        $page = $query->paginate($perPage);
        $items = $page->getCollection()->map(fn ($n) => [
            'id' => (string) $n->id,
            'type' => (string) ($n->data['type'] ?? 'info'),
            'title' => (string) ($n->data['title'] ?? 'Notifikasi'),
            'body' => (string) ($n->data['body'] ?? $n->data['message'] ?? ''),
            'message' => (string) ($n->data['message'] ?? $n->data['body'] ?? ''),
            'url' => $n->data['url'] ?? null,
            'entity_type' => $n->data['entity_type'] ?? null,
            'entity_id' => isset($n->data['entity_id']) ? (string) $n->data['entity_id'] : null,
            'role_target' => $n->data['role_target'] ?? null,
            'priority' => $n->data['priority'] ?? null,
            'action' => $n->data['action'] ?? null,
            'collapse_key' => $n->data['collapse_key'] ?? null,
            'context' => $n->data['context'] ?? null,
            'sent_at' => (string) ($n->data['sent_at'] ?? $n->created_at?->toIso8601String()),
            'notification_id' => (string) ($n->data['notification_id'] ?? $n->id),
            'read_at' => $n->read_at?->toIso8601String(),
            'is_read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->unreadNotifications()->where('id', $notification)->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}
