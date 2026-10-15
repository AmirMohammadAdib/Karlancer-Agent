<?php
namespace Tests\Feature;
use App\Jobs\ImportProjectsJob;
use App\Models\ImportRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class IngestionFeatureTest extends TestCase {
    use RefreshDatabase;
    public function test_mock_fixture_import_is_idempotent_and_records_history(): void {
        dispatch_sync(new ImportProjectsJob('mock'));
        $this->assertSame(2, Project::where('platform','mock')->count());
        $first = ImportRun::latest('id')->first();
        $this->assertSame('completed', $first->status);
        $this->assertSame(2, $first->projects_created);
        dispatch_sync(new ImportProjectsJob('mock'));
        $second = ImportRun::latest('id')->first();
        $this->assertSame(0, $second->projects_created);
        $this->assertSame(2, $second->projects_updated);
        $this->assertCount(2, ImportRun::all());
    }
    public function test_import_administration_requires_authentication(): void {
        $this->get('/sources')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/sources')->assertOk();
    }
}
