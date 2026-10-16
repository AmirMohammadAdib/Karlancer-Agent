<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Models\ProjectActivityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request, string $status = 'new'): View
    {
        abort_unless(in_array($status, ['new', 'approved', 'rejected'], true), 404);
        $query = Project::query()->where('decision_status', $status);
        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        return view('projects.index', ['projects' => $query->latest()->paginate(9)->withQueryString(), 'status' => $status, 'search' => $search]);
    }

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['platform'] = 'mock';
            $data['external_project_id'] = 'manual-'.str()->uuid();
            $data['required_skills'] = $this->skills($data['required_skills'] ?? null);
            $data['discovered_at'] = now();
            $data['availability_status'] = 'available';
            $data['decision_status'] = 'new';
            $data['is_demo'] = true;
            $project = Project::create($data);
            $project->activityEvents()->create(['actor_id' => $request->user()->id, 'event_type' => 'project_discovered', 'action' => 'created', 'new_status' => 'new', 'actor' => 'admin']);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('status', 'پروژه آزمایشی با موفقیت ایجاد شد.');
    }

    public function show(Project $project): View
    {
        return view('projects.show', ['project' => $project->load(['activityEvents' => fn ($query) => $query->with('actorUser')->oldest('created_at')])]);
    }

    public function updateDecision(Request $request, Project $project, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'reject', 'restore'], true), 404);
        $transitions = ['approve' => ['new', 'approved'], 'reject' => ['new', 'rejected'], 'restore' => ['rejected', 'new']];
        [$from, $to] = $transitions[$decision];
        if ($project->decision_status !== $from) {
            throw ValidationException::withMessages(['project' => 'این انتقال وضعیت برای پروژه فعلی مجاز نیست.']);
        }
        DB::transaction(function () use ($request, $project, $decision, $from, $to) {
            $project->update(['decision_status' => $to, 'approved_at' => $to === 'approved' ? now() : null, 'rejected_at' => $to === 'rejected' ? now() : null]);
            $project->activityEvents()->create(['actor_id' => $request->user()->id, 'event_type' => 'project_'.($decision === 'approve' ? 'approved' : $decision.'d'), 'action' => $decision, 'previous_status' => $from, 'new_status' => $to, 'actor' => 'admin']);
        });

        return back()->with('status', 'وضعیت پروژه با موفقیت ذخیره شد.');
    }

    public function activity(): View
    {
        return view('activity.index', ['events' => ProjectActivityEvent::with(['project', 'actorUser'])->latest('created_at')->paginate(20)]);
    }

    private function skills(?string $value): array
    {
        return collect(preg_split('/[,،\n]+/', (string) $value))->map(fn ($skill) => trim($skill))->filter()->unique()->values()->all();
    }
}
