<?php

namespace App\Services;

use App\Contracts\ProjectSourceAdapter;
use App\Models\ImportRun;
use App\Models\Project;
use App\Models\ProjectMatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Imports normalized source records one at a time.
 *
 * projects_fetched is incremented for records that reach this service. The
 * HTTP receiver adds rejected/duplicate records to the same total before
 * dispatching the job, so every payload item has exactly one outcome:
 * created, updated, skipped, rejected, or failed.
 */
class ProjectIngestionService
{
    public function __construct(private ProjectSourceAdapter $adapter) {}

    public function import(ImportRun $run): ImportRun
    {
        try {
            foreach ($this->adapter->fetchProjects() as $payload) {
                $run->increment('projects_fetched');

                try {
                    DB::transaction(function () use ($payload, $run): void {
                        $this->upsert($this->adapter->normalizeProject((array) $payload), $run);
                    });
                } catch (\Throwable $exception) {
                    // A bad item must not abort the remaining batch.
                    $run->increment('projects_failed');
                    $this->recordError($run, $exception);
                }
            }

            $run->refresh();
            $successful = (int) $run->projects_created
                + (int) $run->projects_updated
                + (int) $run->projects_skipped;
            $hasFailures = (int) ($run->projects_rejected ?? 0) > 0
                || (int) ($run->projects_failed ?? 0) > 0
                || count((array) ($run->errors ?? [])) > 0;

            $status = $hasFailures
                ? ($successful > 0 ? 'partial' : 'failed')
                : 'completed';

            $run->update(['status' => $status, 'finished_at' => now()]);
        } catch (\Throwable $exception) {
            $this->recordError($run, $exception);
            $run->increment('projects_failed');
            $run->update(['status' => 'failed', 'finished_at' => now()]);
        }

        return $run->refresh();
    }

    private function recordError(ImportRun $run, \Throwable $exception): void
    {
        // Never persist SQL, URLs, cookies, or exception traces. These labels
        // are enough for operators while remaining safe in the admin panel.
        $message = match (true) {
            $exception instanceof QueryException => 'database persistence error',
            $exception instanceof \InvalidArgumentException => 'invalid project data',
            default => 'item processing error',
        };

        try {
            $errors = is_array($run->errors) ? $run->errors : [];
            $errors[] = $message;
            $run->update(['errors' => array_values(array_slice($errors, -100))]);
        } catch (\Throwable) {
            // Diagnostics must never stop the remaining import.
        }
    }

    private function upsert(array $data, ImportRun $run): void
    {
        $url = isset($data['canonical_url']) ? trim((string) $data['canonical_url']) : '';
        $hash = $url !== '' ? hash('sha256', rtrim(strtolower($url), '/')) : null;
        $externalId = isset($data['external_project_id'])
            ? trim((string) $data['external_project_id'])
            : '';

        $query = Project::query()->where('platform', $this->adapter->sourceIdentifier());
        if ($externalId !== '') {
            $query->where('external_project_id', $externalId);
        } elseif ($hash !== null) {
            $query->where('canonical_url_hash', $hash);
        } else {
            throw new \InvalidArgumentException('External ID or URL is required.');
        }

        $existing = $query->lockForUpdate()->first();
        $now = now();
        $attrs = array_merge($data, [
            'platform' => $this->adapter->sourceIdentifier(),
            'external_project_id' => $externalId !== '' ? $externalId : null,
            'canonical_url' => $url !== '' ? $url : null,
            'canonical_url_hash' => $hash,
            'last_seen_at' => $now,
            'is_demo' => $this->adapter->sourceIdentifier() === 'mock',
            'discovered_at' => $now,
        ]);

        $attrs['budget'] = ($data['budget_min'] ?? null) !== null || ($data['budget_max'] ?? null) !== null
            ? trim((($data['budget_min'] ?? null) !== null ? $data['budget_min'] : '').' - '.(($data['budget_max'] ?? null) !== null ? $data['budget_max'] : ''))
            : null;

        if (! $existing) {
            $attrs['first_seen_at'] = $now;
            $attrs['decision_status'] = 'new';
            $attrs['availability_status'] = 'available';
            Project::create($attrs);
            $run->increment('projects_created');
            return;
        }

        // Only non-empty source values may refresh optional fields. This is
        // what keeps a failed detail extraction from erasing good data.
        $optional = ['external_project_id', 'canonical_url', 'canonical_url_hash', 'description', 'budget', 'budget_min', 'budget_max', 'currency', 'required_skills', 'category', 'source_status', 'published_at'];
        $changed = false;
        foreach (['title', 'description', 'budget', 'budget_min', 'budget_max', 'currency', 'required_skills', 'category', 'source_status', 'published_at'] as $field) {
            $value = $attrs[$field] ?? null;
            if (in_array($field, $optional, true) && ($value === null || $value === '' || $value === [])) {
                continue;
            }
            if (array_key_exists($field, $attrs) && $existing->{$field} != $value) {
                $changed = true;
                break;
            }
        }

        $updateAttrs = collect($attrs)
            ->except(['decision_status', 'availability_status', 'approved_at', 'rejected_at', 'first_seen_at', 'is_demo'])
            ->filter(function ($value, $key) use ($optional): bool {
                return ! in_array($key, $optional, true) || ($value !== null && $value !== '' && $value !== []);
            })
            ->all();

        $existing->update($updateAttrs);
        if ($changed) {
            ProjectMatch::where('project_id', $existing->id)->delete();
            $run->increment('projects_updated');
        } else {
            $run->increment('projects_skipped');
        }
    }
}
