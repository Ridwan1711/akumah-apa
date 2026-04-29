<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PpdbStudentSyncService;
use App\Services\SystemLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PpdbSyncController extends Controller
{
    public function __construct(
        private readonly PpdbStudentSyncService $syncService
    ) {}

    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'PPDB sync endpoint ready.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ppdb_application_id' => ['required', 'integer'],
            'reg_no' => ['required', 'string', 'max:100'],
            'registration_sequence' => ['nullable', 'integer'],
            'applicant' => ['required', 'array'],
            'applicant.full_name' => ['required', 'string', 'max:255'],
            'applicant.sex' => ['nullable', 'in:L,P'],
            'guardians' => ['nullable', 'array'],
            'guardians.*.role' => ['nullable', 'string'],
            'guardians.*.name' => ['nullable', 'string', 'max:255'],
            'intake' => ['nullable', 'array'],
            'intake.year' => ['nullable', 'integer'],
            'documents' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->syncService->sync($validated);

            return response()->json([
                'success' => true,
                'message' => 'Data PPDB berhasil disinkronkan ke SIAKAD.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $log = new SystemLogService();

            $log->error('PPDB sync failed in SIAKAD', [
                'payload_application_id' => $validated['ppdb_application_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sinkronisasi gagal: '.$e->getMessage(),
            ], 422);
        }
    }
}
