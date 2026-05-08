<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class LaravelLogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/laravel-logs/index', [
            'defaultLogPath' => storage_path('logs/laravel.log'),
        ]);
    }

    public function fetch(Request $request): JsonResponse
    {
        $filters = [
            'level' => strtolower((string) $request->input('level', 'all')),
            'search' => trim((string) $request->input('search', '')),
            'limit' => max(20, min(500, (int) $request->input('limit', 120))),
        ];

        $path = storage_path('logs/laravel.log');
        if (! File::exists($path)) {
            return response()->json([
                'entries' => [],
                'levels' => [],
                'meta' => [
                    'exists' => false,
                    'path' => $path,
                    'size' => 0,
                    'modified_at' => null,
                ],
            ]);
        }

        $raw = $this->readTail($path, 2_000_000);
        $entries = $this->parseLaravelEntries($raw);

        if ($filters['level'] !== 'all') {
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry): bool => strtolower($entry['level']) === $filters['level']
            ));
        }

        if ($filters['search'] !== '') {
            $needle = mb_strtolower($filters['search']);
            $entries = array_values(array_filter($entries, function (array $entry) use ($needle): bool {
                $haystack = mb_strtolower($entry['content']);

                return str_contains($haystack, $needle);
            }));
        }

        $entries = array_slice(array_reverse($entries), 0, $filters['limit']);

        $levels = array_values(array_unique(array_map(
            fn (array $entry): string => strtolower((string) $entry['level']),
            $this->parseLaravelEntries($raw)
        )));
        sort($levels);

        return response()->json([
            'entries' => $entries,
            'levels' => $levels,
            'meta' => [
                'exists' => true,
                'path' => $path,
                'size' => File::size($path),
                'modified_at' => Carbon::createFromTimestamp(File::lastModified($path))->toIso8601String(),
            ],
        ]);
    }

    private function readTail(string $path, int $maxBytes): string
    {
        $size = File::size($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $bytesToRead = min($size, $maxBytes);
        if ($bytesToRead <= 0) {
            fclose($handle);

            return '';
        }

        fseek($handle, -$bytesToRead, SEEK_END);
        $chunk = fread($handle, $bytesToRead);
        fclose($handle);

        if (! is_string($chunk)) {
            return '';
        }

        // Prevent partial first entry when reading from middle of file.
        $firstHeaderPos = strpos($chunk, '[');
        if ($size > $bytesToRead && $firstHeaderPos !== false) {
            $chunk = substr($chunk, $firstHeaderPos);
        }

        return $chunk;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseLaravelEntries(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        if (trim($raw) === '') {
            return [];
        }

        $pattern = '/^\[(?<timestamp>[^\]]+)\]\s+(?<channel>[a-zA-Z0-9_\-\.]+)\.(?<level>[a-zA-Z0-9_\-]+):\s*(?<message>.*)$/m';
        preg_match_all($pattern, $raw, $matches, PREG_OFFSET_CAPTURE);
        $headers = $matches[0] ?? [];
        if ($headers === []) {
            return [];
        }

        $entries = [];
        foreach ($headers as $index => $header) {
            $start = $header[1];
            $nextStart = $headers[$index + 1][1] ?? strlen($raw);
            $block = trim(substr($raw, $start, $nextStart - $start));

            $timestamp = $matches['timestamp'][$index][0] ?? '';
            $channel = $matches['channel'][$index][0] ?? 'app';
            $level = strtolower($matches['level'][$index][0] ?? 'info');
            $message = $matches['message'][$index][0] ?? '';

            $entries[] = [
                'id' => sha1($timestamp.'|'.$channel.'|'.$level.'|'.$message.'|'.$start),
                'timestamp' => $timestamp,
                'channel' => $channel,
                'level' => $level,
                'message' => trim($message),
                'content' => $block,
            ];
        }

        return $entries;
    }
}
