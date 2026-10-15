<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectActivityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(string $status = 'new'): View
    {
        abort_unless(in_array($status, ['new', 'approved', 'rejected'], true), 404);

        return view('projects.index', ['projects' => Project::where('decision_status', $status)->latest('discovered_at')->get(), 'status' => $status]);
    }

    public function updateDecision(Project $project, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'reject', 'restore'], true), 404);
        $new = ['approve' => 'approved', 'reject' => 'rejected', 'restore' => 'new'][$decision];
        DB::transaction(function () use ($project, $new, $decision) {
            $previous = $project->decision_status;
            $project->update(['decision_status' => $new, 'approved_at' => $new === 'approved' ? now() : null, 'rejected_at' => $new === 'rejected' ? now() : null]);
            $project->activityEvents()->create(['event_type' => 'project_'.($decision === 'approve' ? 'approved' : $decision.'d'), 'previous_status' => $previous, 'new_status' => $new, 'actor' => 'admin']);
        });

        return back()->with('status', 'وضعیت پروژه ذخیره شد.');
    }

    public function activity(): View
    {
        return view('activity.index', ['events' => ProjectActivityEvent::with('project')->latest('created_at')->get()]);
    }
}
