<?php

namespace App\Http\Controllers;

use App\Jobs\ImportProjectsJob;
use App\Models\ImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalCollectorController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $configured = (string) config('services.collector.token');
        if ($configured === '' || ! hash_equals($configured, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! str_contains(strtolower((string) $request->header('Content-Type')), 'application/json')) {
            return response()->json(['message' => 'Content-Type must be application/json'], 415);
        }
        if ((int) $request->header('Content-Length', 0) > 2_000_000) {
            return response()->json(['message' => 'Payload too large'], 413);
        }

        $payload = $request->json()->all();
        if (($payload['schema_version'] ?? null) !== '1.0'
            || ($payload['source'] ?? null) !== 'karlancer'
            || ! is_array($payload['items'] ?? null)) {
            return response()->json(['message' => 'Unsupported or invalid schema'], 422);
        }

        $records = [];
        $errors = [];
        $seen = [];
        $rejected = 0;
        $skipped = 0;
        $detailFailures = 0;

        foreach ($payload['items'] as $index => $item) {
            // Non-project notifications are intentionally ignored, but remain
            // accounted for as skipped payload items.
            if (! is_array($item)) {
                $rejected++;
                $errors[] = 'item '.($index + 1).': invalid record';
                continue;
            }
            if (($item['type'] ?? 'project') !== 'project') {
                $skipped++;
                continue;
            }

            $url = trim((string) ($item['project_url'] ?? ''));
            $parts = parse_url($url);
            if (! $parts
                || ($parts['scheme'] ?? '') !== 'https'
                || ($parts['host'] ?? '') !== 'www.karlancer.com'
                || ! str_starts_with($parts['path'] ?? '', '/projects/')) {
                $rejected++;
                $errors[] = 'item '.($index + 1).': invalid project URL';
                continue;
            }

            $diagnostics = is_array($item['extraction_diagnostics'] ?? null)
                ? $item['extraction_diagnostics']
                : [];
            $detailStatus = strtolower(trim((string) ($diagnostics['status'] ?? '')));
            $title = trim((string) ($item['title'] ?? ''));
            $looksLikeNotification = str_contains($title, 'پروژه ای مطابق با توانایی شما')
                || str_contains($title, 'پروژه‌ای مطابق با توانایی شما');
            if ($title === '' || $detailStatus === 'failed' || $looksLikeNotification) {
                $rejected++;
                $detailFailures += $detailStatus === 'failed' || $looksLikeNotification ? 1 : 0;
                $errors[] = 'item '.($index + 1).': project detail extraction failed';
                continue;
            }
            if (array_key_exists('skills', $item) && ! is_array($item['skills'])) {
                $rejected++;
                $errors[] = 'item '.($index + 1).': skills must be an array';
                continue;
            }

            $key = rtrim(strtolower($url), '/');
            if (isset($seen[$key])) {
                $skipped++;
                continue;
            }
            $seen[$key] = true;
            $records[] = [
                'external_project_id' => $item['external_project_id'] ?? null,
                'canonical_url' => $url,
                'title' => $title,
                'description' => $item['description'] ?? null,
                'budget_min' => $item['budget_min'] ?? null,
                'budget_max' => $item['budget_max'] ?? null,
                'currency' => $item['currency'] ?? null,
                'required_skills' => $item['skills'] ?? [],
                'category' => $item['category'] ?? null,
                'project_status' => $item['project_status'] ?? null,
                'published_at' => $item['published_at'] ?? null,
            ];
        }

        $totalItems = count($payload['items']);
        if ($totalItems === 0) {
            $errors[] = 'no project records supplied';
        }
        $run = ImportRun::create([
            'source' => 'karlancer',
            'collector_type' => 'playwright',
            'status' => 'running',
            'started_at' => now(),
            'notifications_inspected' => $totalItems,
            'unique_projects_discovered' => count($records),
            'details_succeeded' => count($records),
            'details_failed' => $detailFailures,
            'projects_fetched' => $rejected + $skipped,
            'projects_rejected' => $rejected,
            'projects_skipped' => $skipped,
            'errors' => $errors,
        ]);

        try {
            if ($records !== []) {
                dispatch_sync(new ImportProjectsJob('karlancer', $run->id, $records));
            } else {
                $run->update([
                    'status' => ($rejected > 0 || $totalItems === 0) ? 'failed' : 'completed',
                    'finished_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // The job normally contains item failures. This branch is only for
            // a dispatch/runtime failure and deliberately stores no exception text.
            $run->increment('projects_failed', count($records));
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'errors' => array_values(array_slice(array_merge((array) $run->errors, ['import job failed']), -100)),
            ]);
        }

        $run->refresh();
        $statusCode = match ($run->status) {
            'completed' => 200,
            'partial' => 207,
            default => 422,
        };

        return response()->json([
            'status' => $run->status,
            'import_run_id' => $run->id,
            'notifications_inspected' => $totalItems,
            'projects_discovered' => $run->unique_projects_discovered,
            'projects_fetched' => $run->projects_fetched,
            'projects_created' => $run->projects_created,
            'projects_updated' => $run->projects_updated,
            'projects_skipped' => $run->projects_skipped,
            'projects_rejected' => $run->projects_rejected,
            'projects_failed' => $run->projects_failed,
            'errors' => $run->errors ?? [],
        ], $statusCode);
    }
}
