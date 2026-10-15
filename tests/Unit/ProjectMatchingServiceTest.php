<?php

namespace Tests\Unit;

use App\Models\MatchingProfile;
use App\Models\Project;
use App\Models\ProjectMatch;
use App\Models\User;
use App\Services\ProjectMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $values = []): MatchingProfile
    {
        return MatchingProfile::create(array_merge(['user_id' => User::factory()->create()->id, 'preferred_skills' => ['Laravel'], 'positive_keywords' => ['API'], 'negative_keywords' => ['قمار'], 'min_budget' => 100, 'max_budget' => 200, 'preferred_currency' => 'IRR', 'min_score' => 60, 'enabled' => true], $values));
    }

    private function project(array $values = []): Project
    {
        return Project::factory()->create(array_merge(['title' => 'Laravel API', 'description' => 'ساخت API امن', 'required_skills' => ['Laravel'], 'budget' => 150, 'currency' => 'IRR'], $values));
    }

    public function test_perfect_project_reaches_100_and_explains_matches(): void
    {
        $result = app(ProjectMatchingService::class)->score($this->project(), $this->profile());
        $this->assertSame(100, $result['score']);
        $this->assertContains('laravel', $result['breakdown']['matched_skills']);
        $this->assertContains('api', $result['breakdown']['matched_positive_keywords']);
        $this->assertStringContainsString('امتیاز نهایی 100', $result['breakdown']['explanation']);
    }

    public function test_negative_keywords_penalize_and_scores_are_bounded(): void
    {
        $result = app(ProjectMatchingService::class)->score($this->project(['description' => 'API و قمار']), $this->profile());
        $this->assertContains('قمار', $result['breakdown']['matched_negative_keywords']);
        $this->assertLessThan(100, $result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
    }

    public function test_missing_budget_and_currency_mismatch_do_not_score_as_positive(): void
    {
        $service = app(ProjectMatchingService::class);
        $missing = $service->score($this->project(['budget' => null]), $this->profile());
        $mismatch = $service->score($this->project(['currency' => 'USD']), $this->profile());
        $this->assertSame(0, $missing['breakdown']['budget_points']);
        $this->assertSame(0, $mismatch['breakdown']['budget_points']);
        $this->assertFalse($mismatch['breakdown']['budget']['currency_compatible']);
    }

    public function test_normalization_deduplicates_persian_variants_and_short_keywords(): void
    {
        $profile = $this->profile(['preferred_skills' => ['ي', 'ی', 'ك'], 'positive_keywords' => ['x', 'API', 'api'], 'negative_keywords' => []]);
        $result = app(ProjectMatchingService::class)->score($this->project(['required_skills' => ['ی', 'ک'], 'title' => 'API']), $profile);
        $this->assertCount(2, $result['breakdown']['matched_skills']);
        $this->assertSame(['api'], $result['breakdown']['matched_positive_keywords']);
    }

    public function test_persistence_is_deterministic_and_source_hash_recalculates(): void
    {
        $service = app(ProjectMatchingService::class);
        $project = $this->project();
        $profile = $this->profile();
        $first = $service->persist($project, $profile);
        $second = $service->ensureCurrent($project, $profile);
        $this->assertSame($first->score, $second->score);
        $project->update(['title' => 'کاملاً متفاوت']);
        $third = $service->ensureCurrent($project->fresh(), $profile);
        $this->assertNotSame($first->source_hash, $third->source_hash);
        $this->assertSame(1, ProjectMatch::count());
    }
}
