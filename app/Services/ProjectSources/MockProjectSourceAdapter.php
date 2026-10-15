<?php
namespace App\Services\ProjectSources;
use App\Contracts\ProjectSourceAdapter;
use InvalidArgumentException;
use DateTimeInterface;
use Carbon\Carbon;
class MockProjectSourceAdapter implements ProjectSourceAdapter {
    public function sourceIdentifier(): string { return 'mock'; }
    public function fetchProjects(): iterable { $json = file_get_contents(base_path('database/fixtures/projects/mock-projects.json')); $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR); if (! is_array($data)) throw new InvalidArgumentException('Fixture must be an array.'); return $data; }
    public function normalizeProject(array $payload): array {
        $title = trim((string) ($payload['title'] ?? '')); if ($title === '') throw new InvalidArgumentException('Project title is required.');
        $url = isset($payload['canonical_url']) ? trim((string) $payload['canonical_url']) : null; if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Invalid project URL.');
        $skills = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), (array) ($payload['required_skills'] ?? [])))));
        $number = fn ($v) => is_numeric($v) ? (float) $v : null;
        $published = $payload['published_at'] ?? null;
        if ($published instanceof DateTimeInterface) $published = $published->format('Y-m-d H:i:s');
        elseif (is_string($published) && preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:?\d{2})?)?$/', trim($published))) { try { $published = Carbon::parse($published)->format('Y-m-d H:i:s'); } catch (\Throwable) { $published = null; } }
        else $published = null;
        return ['external_project_id'=>isset($payload['external_project_id']) ? trim((string) $payload['external_project_id']) : null, 'canonical_url'=>$url, 'title'=>$title, 'description'=>trim((string) ($payload['description'] ?? '')), 'budget_min'=>$number($payload['budget_min'] ?? null), 'budget_max'=>$number($payload['budget_max'] ?? null), 'currency'=>isset($payload['currency']) ? strtoupper(trim((string) $payload['currency'])) : null, 'required_skills'=>$skills, 'published_at'=>$published];
    }
}
