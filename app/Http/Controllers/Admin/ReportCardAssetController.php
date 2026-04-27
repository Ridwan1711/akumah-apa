<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportCardAssetController extends Controller
{
    private const MAX_SIZE_KB = 2048;

    private const VALID_TYPES = [
        'logos' => ['logo'],
        'signatures' => ['signature_wali', 'signature_kepala'],
        'stamps' => ['stamp'],
    ];

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:'.self::MAX_SIZE_KB],
            'type' => ['required', 'string', 'in:logo,signature_wali,signature_kepala,stamp'],
        ]);

        $folder = match ($request->type) {
            'logo' => 'logos',
            'signature_wali', 'signature_kepala' => 'signatures',
            'stamp' => 'stamps',
            default => 'misc',
        };

        $path = 'report-card-assets/'.$folder;
        $file = $request->file('file');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $fullPath = $file->storeAs($path, $filename, 'public');

        return response()->json([
            'path' => $fullPath,
            'url' => Storage::disk('public')->url($fullPath),
        ]);
    }

    public function list(): JsonResponse
    {
        $logos = $this->listFiles('report-card-assets/logos');
        $signatures = $this->listFiles('report-card-assets/signatures');
        $stamps = $this->listFiles('report-card-assets/stamps');

        return response()->json([
            'logos' => $logos,
            'signatures' => $signatures,
            'stamps' => $stamps,
        ]);
    }

    private function listFiles(string $path): array
    {
        if (! Storage::disk('public')->exists($path)) {
            return [];
        }

        $files = Storage::disk('public')->files($path);

        return collect($files)->map(function (string $file) {
            return [
                'path' => $file,
                'url' => Storage::disk('public')->url($file),
                'name' => basename($file),
            ];
        })->values()->all();
    }
}
