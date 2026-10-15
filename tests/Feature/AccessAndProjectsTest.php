<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectActivityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessAndProjectsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@test.local']);
    }

    public function test_guest_cannot_access_project_management(): void
    {
        $this->get('/projects/status/new')->assertRedirect('/login');
        $this->get('/projects/create')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_can_create_valid_mock_project_and_view_details(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->post('/projects', ['title' => 'ساخت API', 'description' => 'توضیحات پروژه', 'budget' => '12500000', 'currency' => 'IRR', 'required_skills' => 'Laravel، MySQL']);
        $project = Project::first();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseHas('projects', ['title' => 'ساخت API', 'platform' => 'mock', 'decision_status' => 'new', 'currency' => 'IRR']);
        $this->assertDatabaseHas('project_activity_events', ['project_id' => $project->id, 'actor_id' => $admin->id, 'action' => 'created']);
        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertSee('ساخت API')->assertSee('تاریخچه وضعیت');
    }

    public function test_invalid_project_input_is_rejected(): void
    {
        $this->actingAs($this->admin())->from('/projects/create')->post('/projects', ['title' => '', 'description' => '', 'budget' => '-4', 'currency' => 'GBP'])->assertRedirect('/projects/create')->assertSessionHasErrors(['title', 'description', 'budget', 'currency']);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_listing_search_status_filter_and_pagination_are_database_backed(): void
    {
        $admin = $this->admin();
        Project::factory()->count(10)->create(['decision_status' => 'new']);
        Project::factory()->create(['title' => 'Search Target', 'description' => 'unique-search-term', 'decision_status' => 'new']);
        Project::factory()->create(['decision_status' => 'approved']);
        $this->actingAs($admin)->get('/projects/status/new?q=unique-search-term')->assertOk()->assertSee('Search Target')->assertDontSee('پروژه‌ای با این مشخصات');
        $this->actingAs($admin)->get('/projects/status/approved')->assertOk()->assertSee('پروژه‌های تأییدشده')->assertSee('1 پروژه');
        $this->actingAs($admin)->get('/projects/status/new')->assertOk()->assertSee('page=2');
    }

    public function test_valid_transitions_create_one_history_event_each(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['decision_status' => 'new']);
        $this->actingAs($admin)->post(route('projects.decision', [$project, 'approve']))->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'decision_status' => 'approved']);
        $this->assertDatabaseHas('project_activity_events', ['project_id' => $project->id, 'actor_id' => $admin->id, 'previous_status' => 'new', 'new_status' => 'approved', 'action' => 'approve']);
        $this->assertSame(1, ProjectActivityEvent::where('project_id', $project->id)->count());
    }

    public function test_reject_and_restore_workflow_is_supported(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['decision_status' => 'new']);
        $this->actingAs($admin)->post(route('projects.decision', [$project, 'reject']))->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'decision_status' => 'rejected']);
        $this->actingAs($admin)->post(route('projects.decision', [$project, 'restore']))->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'decision_status' => 'new']);
        $this->assertSame(2, ProjectActivityEvent::where('project_id', $project->id)->count());
    }

    public function test_invalid_transition_is_blocked_without_history(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['decision_status' => 'approved']);
        $this->actingAs($admin)->post(route('projects.decision', [$project, 'approve']))->assertSessionHasErrors('project');
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'decision_status' => 'approved']);
        $this->assertDatabaseCount('project_activity_events', 0);
    }

    public function test_dashboard_counters_and_real_integration_boundary(): void
    {
        $admin = $this->admin();
        Project::factory()->create(['decision_status' => 'new']);
        Project::factory()->create(['decision_status' => 'approved']);
        Project::factory()->create(['decision_status' => 'rejected']);
        Project::factory()->create(['platform' => 'karlancer', 'decision_status' => 'new']);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('کل پروژه‌های آزمایشی')->assertSee('3');
        $this->assertSame('mock', config('services.karlancer_mode'));
        $this->assertSame('mock', config('services.llm_mode'));
        $this->assertFalse((bool) config('services.real_submissions_enabled'));
        $this->get('/submit')->assertNotFound();
    }
}
