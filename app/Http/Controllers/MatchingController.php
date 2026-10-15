<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMatchingProfileRequest;
use App\Models\MatchingProfile;
use App\Models\Project;
use App\Services\ProjectMatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatchingController extends Controller
{
    public function settings(Request $request): View
    {
        return view('matching.settings', ['profile' => $request->user()->matchingProfile ?? new MatchingProfile(['min_score' => 0])]);
    }

    public function update(UpdateMatchingProfileRequest $request, ProjectMatchingService $matching): RedirectResponse
    {
        $data = $request->validated();
        foreach (['preferred_skills', 'positive_keywords', 'negative_keywords'] as $field) {
            $data[$field] = $this->list($data[$field] ?? null);
        }
        $data['enabled'] = $request->boolean('enabled');
        $profile = MatchingProfile::updateOrCreate(['user_id' => $request->user()->id], $data);
        $matching->recalculate($profile);

        return back()->with('status', 'تنظیمات matching ذخیره و امتیازها به‌روزرسانی شد.');
    }

    public function recommendations(Request $request, ProjectMatchingService $matching): View
    {
        $profile = $request->user()->matchingProfile;
        if (! $profile || ! $profile->enabled) {
            return view('matching.recommendations', ['profile' => $profile, 'matches' => null, 'threshold' => 0]);
        }
        $projects = Project::where('platform', 'mock')->whereIn('decision_status', ['new', 'approved', 'rejected'])->get();
        foreach ($projects as $project) {
            $matching->ensureCurrent($project, $profile);
        }
        $threshold = $request->boolean('all') ? 0 : (int) $profile->min_score;
        $matches = $profile->matches()->with('project')->whereHas('project', fn ($query) => $query->where('platform', 'mock'))->where('score', '>=', $threshold)->orderByDesc('score')->paginate(9)->withQueryString();

        return view('matching.recommendations', compact('profile', 'matches', 'threshold'));
    }

    public function recalculate(Request $request, ProjectMatchingService $matching): RedirectResponse
    {
        $profile = $request->user()->matchingProfile;
        if ($profile) {
            $count = $matching->recalculate($profile);
        } else {
            $count = 0;
        }

        return back()->with('status', "{$count} پروژه دوباره امتیازدهی شد.");
    }

    private function list(?string $value): array
    {
        return collect(preg_split('/[,،\n]+/u', (string) $value))->map(fn ($item) => trim($item))->filter()->unique()->values()->all();
    }
}
