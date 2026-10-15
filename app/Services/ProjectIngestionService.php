<?php
namespace App\Services;
use App\Contracts\ProjectSourceAdapter;
use App\Models\ImportRun;
use App\Models\Project;
use App\Models\ProjectMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
class ProjectIngestionService {
    public function __construct(private ProjectSourceAdapter $adapter) {}
    public function import(ImportRun $run): ImportRun {
        try {
            foreach ($this->adapter->fetchProjects() as $payload) {
                $run->increment('projects_fetched');
                try { $this->upsert($this->adapter->normalizeProject((array) $payload), $run); }
                catch (\Throwable $e) { $run->increment('projects_skipped'); $errors = $run->errors ?? []; $errors[] = substr($e->getMessage(), 0, 200); $run->update(['errors'=>$errors]); }
            }
            $run->refresh(); $run->update(['status'=>count($run->errors ?? []) ? ($run->projects_created || $run->projects_updated ? 'partial' : 'failed') : 'completed', 'finished_at'=>now()]);
        } catch (\Throwable $e) { $run->update(['status'=>'failed','finished_at'=>now(),'errors'=>array_merge($run->errors ?? [], [substr($e->getMessage(),0,200)])]); }
        return $run->refresh();
    }
    private function upsert(array $data, ImportRun $run): void {
        $hash = $data['canonical_url'] ? hash('sha256', rtrim(strtolower($data['canonical_url']), '/')) : null;
        $query = Project::query()->where('platform', $this->adapter->sourceIdentifier());
        if ($data['external_project_id']) $query->where('external_project_id', $data['external_project_id']); elseif ($hash) $query->where('canonical_url_hash', $hash); else throw new \InvalidArgumentException('External ID or URL is required.');
        $existing = $query->first(); $now = now(); $attrs = array_merge($data, ['platform'=>$this->adapter->sourceIdentifier(),'canonical_url_hash'=>$hash,'last_seen_at'=>$now,'is_demo'=>$this->adapter->sourceIdentifier() === 'mock','discovered_at'=>$now]);
        $attrs['budget'] = $data['budget_min'] !== null || $data['budget_max'] !== null ? trim(($data['budget_min'] !== null ? $data['budget_min'] : '').' - '.($data['budget_max'] !== null ? $data['budget_max'] : '')) : null;
        if (! $existing) { $attrs['first_seen_at']=$now; $attrs['decision_status']='new'; $attrs['availability_status']='available'; Project::create($attrs); $run->increment('projects_created'); return; }
        $changed = collect(['title','description','budget_min','budget_max','currency','required_skills'])->some(fn ($f) => $existing->{$f} != $attrs[$f]);
        unset($attrs['decision_status'], $attrs['availability_status'], $attrs['approved_at'], $attrs['rejected_at'], $attrs['first_seen_at']); $existing->update($attrs); if ($changed) ProjectMatch::where('project_id',$existing->id)->delete();
        $run->increment('projects_updated');
    }
}
