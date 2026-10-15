<?php
namespace Tests\Feature;
use App\Jobs\ImportProjectsJob;
use App\Models\ImportRun;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
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
    public function test_manual_upload_requires_preview_then_confirmation(): void {
        $admin=User::factory()->create();
        $payload=json_encode([['external_project_id'=>'real-1','canonical_url'=>'https://www.karlancer.com/project/1','title'=>'پروژه واقعی نمونه','description'=>'شرح','currency'=>'IRR','required_skills'=>['PHP']]], JSON_UNESCAPED_UNICODE);
        $this->actingAs($admin)->post(route('sources.karlancer.manual.preview'), ['file'=>UploadedFile::fake()->createWithContent('projects.json',$payload)])->assertOk()->assertSee('پروژه واقعی نمونه');
        $this->actingAs($admin)->post(route('sources.karlancer.manual.confirm'))->assertRedirect(route('imports.index'));
        $this->assertDatabaseHas('projects',['platform'=>'karlancer','external_project_id'=>'real-1']);
    }
    public function test_collector_endpoint_requires_token_and_imports_valid_payload(): void {
        config(['services.collector.token'=>'test-token']);
        $payload=['schema_version'=>'1.0','source'=>'karlancer','items'=>[['type'=>'project','title'=>'پروژه جمع‌آوری‌شده','project_url'=>'https://www.karlancer.com/projects/collector-1','description'=>'شرح کامل','skills'=>['PHP']]]];
        $this->postJson('/api/internal/collector/import',$payload)->assertUnauthorized();
        $this->withHeader('Authorization','Bearer incorrect-token')->postJson('/api/internal/collector/import',$payload)->assertUnauthorized();
        $this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',$payload)->assertOk()->assertJsonPath('projects_created',1);
        $this->assertDatabaseHas('projects',['platform'=>'karlancer','external_project_id'=>null,'title'=>'پروژه جمع‌آوری‌شده']);
    }
    public function test_collector_rejects_external_urls_and_unsupported_schema(): void {
        config(['services.collector.token'=>'test-token']);
        $this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',['schema_version'=>'9.0','source'=>'karlancer','items'=>[]])->assertStatus(422);
        $this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',['schema_version'=>'1.0','source'=>'karlancer','items'=>[['title'=>'bad','project_url'=>'https://evil.example/projects/x']]])->assertStatus(207)->assertJsonPath('projects_discovered',0);
    }
}
