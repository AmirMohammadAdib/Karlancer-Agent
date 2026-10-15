<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessAndProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_login_works(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $user = User::factory()->create(['email' => 'admin@test.local']);
        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_cards_are_database_backed_and_decision_is_audited(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['title' => 'پروژه آزمایشی', 'decision_status' => 'new', 'is_demo' => true]);
        $this->actingAs($user)->get('/projects/new')->assertOk()->assertSee('پروژه آزمایشی');
        $project = Project::first();
        $this->actingAs($user)->post("/projects/{$project->id}/approve")->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'decision_status' => 'approved']);
        $this->assertDatabaseHas('project_activity_events', ['project_id' => $project->id, 'event_type' => 'project_approved']);
    }

    public function test_no_real_submission_route_is_available(): void
    {
        $this->assertFalse((bool) config('services.real_submissions_enabled', false));
        $this->get('/submit')->assertNotFound();
    }
}
