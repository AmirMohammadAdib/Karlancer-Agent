<?php

namespace Tests\Feature;

use App\Models\MatchingProfile;
use App\Models\Project;
use App\Models\ProjectMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_settings_require_authentication(): void
    {
        $this->get('/matching/settings')->assertRedirect('/login');
        $this->get('/recommendations')->assertRedirect('/login');
    }

    public function test_admin_can_create_and_update_profile_with_validation(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post('/matching/settings', ['preferred_skills' => 'Laravel، PHP', 'positive_keywords' => 'API, remote', 'negative_keywords' => 'قمار', 'min_budget' => 100, 'max_budget' => 500, 'preferred_currency' => 'IRR', 'min_score' => 70, 'enabled' => '1'])->assertRedirect();
        $this->assertDatabaseHas('matching_profiles', ['user_id' => $admin->id, 'min_score' => 70, 'preferred_currency' => 'IRR', 'enabled' => 1]);
        $this->actingAs($admin)->from('/matching/settings')->post('/matching/settings', ['min_budget' => 600, 'max_budget' => 100, 'min_score' => 101])->assertRedirect('/matching/settings')->assertSessionHasErrors(['max_budget', 'min_score']);
    }

    public function test_recommendations_rank_and_filter_by_threshold(): void
    {
        $admin = User::factory()->create();
        MatchingProfile::create(['user_id' => $admin->id, 'preferred_skills' => ['Laravel'], 'positive_keywords' => ['API'], 'negative_keywords' => [], 'min_budget' => 100, 'max_budget' => 200, 'preferred_currency' => 'IRR', 'min_score' => 60, 'enabled' => true]);
        Project::factory()->create(['title' => 'ضعیف', 'description' => 'متن', 'required_skills' => [], 'budget' => null]);
        Project::factory()->create(['title' => 'قوی', 'description' => 'API', 'required_skills' => ['Laravel'], 'budget' => 150, 'currency' => 'IRR']);
        $response = $this->actingAs($admin)->get('/recommendations');
        $response->assertOk()->assertSee('قوی')->assertDontSee('ضعیف');
        $this->assertGreaterThanOrEqual(1, ProjectMatch::count());
        $this->actingAs($admin)->get('/recommendations?all=1')->assertOk()->assertSee('ضعیف');
    }

    public function test_disabled_matching_does_not_present_recommendations(): void
    {
        $admin = User::factory()->create();
        MatchingProfile::create(['user_id' => $admin->id, 'min_score' => 0, 'enabled' => false]);
        $this->actingAs($admin)->get('/recommendations')->assertOk()->assertSee('matching فعال نیست');
    }

    public function test_profile_update_recalculates_existing_projects(): void
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create(['title' => 'API Laravel', 'description' => 'API', 'required_skills' => ['Laravel'], 'budget' => 150, 'currency' => 'IRR']);
        $this->actingAs($admin)->post('/matching/settings', ['preferred_skills' => 'Laravel', 'positive_keywords' => 'API', 'negative_keywords' => '', 'min_budget' => 100, 'max_budget' => 200, 'preferred_currency' => 'IRR', 'min_score' => 0, 'enabled' => '1'])->assertRedirect();
        $this->assertDatabaseHas('project_matches', ['project_id' => $project->id, 'matching_profile_id' => MatchingProfile::first()->id]);
    }
}
