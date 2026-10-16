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
        $this->assertSame(0, $second->projects_updated);
        $this->assertSame(2, $second->projects_skipped);
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
        $this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',['schema_version'=>'1.0','source'=>'karlancer','items'=>[['title'=>'bad','project_url'=>'https://evil.example/projects/x']]])->assertStatus(422)->assertJsonPath('projects_discovered',0)->assertJsonPath('projects_rejected',1);
    }
    public function test_relative_persian_dates_are_unknown_and_do_not_abort_batch(): void {
        $records=[]; for($i=1;$i<=20;$i++) $records[]=['external_project_id'=>'batch-'.$i,'canonical_url'=>'https://www.karlancer.com/projects/batch-'.$i,'title'=>'پروژه '.$i,'description'=>'شرح','published_at'=>'۳ ساعت پیش','required_skills'=>[]];
        dispatch_sync(new ImportProjectsJob('karlancer', null, $records));
        $run=ImportRun::latest('id')->first();
        $this->assertSame('completed',$run->status); $this->assertSame(20,$run->projects_fetched); $this->assertSame(20,$run->projects_created); $this->assertSame(0,$run->projects_skipped); $this->assertSame(0,Project::where('platform','karlancer')->whereNotNull('published_at')->count());
        dispatch_sync(new ImportProjectsJob('karlancer', null, $records));
        $rerun=ImportRun::latest('id')->first(); $this->assertSame(0,$rerun->projects_created); $this->assertSame(0,$rerun->projects_updated); $this->assertSame(20,$rerun->projects_skipped);
    }

    public function test_collector_accounts_twenty_items_and_is_idempotent(): void {
        config(['services.collector.token'=>'test-token']);
        $items=[]; for ($i=1; $i<=20; $i++) $items[]=['type'=>'project','title'=>'پروژه '.$i,'project_url'=>'https://www.karlancer.com/projects/account-'.$i,'description'=>'شرح '.$i,'skills'=>['PHP']];
        $payload=['schema_version'=>'1.0','source'=>'karlancer','items'=>$items];
        $first=$this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',$payload);
        $first->assertOk()->assertJsonPath('status','completed')->assertJsonPath('projects_fetched',20)->assertJsonPath('projects_created',20)->assertJsonPath('projects_skipped',0);
        $second=$this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',$payload);
        $second->assertOk()->assertJsonPath('projects_fetched',20)->assertJsonPath('projects_created',0)->assertJsonPath('projects_skipped',20);
        $this->assertSame(20,Project::where('platform','karlancer')->count());
    }

    public function test_invalid_item_is_rejected_without_stopping_valid_items(): void {
        config(['services.collector.token'=>'test-token']);
        $items=[['type'=>'project','title'=>'پروژه معتبر','project_url'=>'https://www.karlancer.com/projects/valid-1','description'=>'شرح']];
        for ($i=2; $i<=20; $i++) $items[]=['type'=>'project','title'=>'پروژه '.$i,'project_url'=>'https://www.karlancer.com/projects/valid-'.$i];
        $items[7]=['type'=>'project','title'=>'پروژه خراب','project_url'=>'https://evil.example/projects/nope'];
        $response=$this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',['schema_version'=>'1.0','source'=>'karlancer','items'=>$items]);
        $response->assertStatus(207)->assertJsonPath('status','partial')->assertJsonPath('projects_fetched',20)->assertJsonPath('projects_created',19)->assertJsonPath('projects_rejected',1)->assertJsonCount(1,'errors');
        $this->assertSame(19,Project::where('platform','karlancer')->count());
    }

    public function test_failed_detail_does_not_store_notification_title_or_erase_existing_data(): void {
        config(['services.collector.token'=>'test-token']);
        $valid=['schema_version'=>'1.0','source'=>'karlancer','items'=>[['type'=>'project','title'=>'عنوان واقعی','project_url'=>'https://www.karlancer.com/projects/preserve-1','description'=>'شرح معتبر','budget_min'=>100,'skills'=>['Laravel']]]];
        $this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',$valid)->assertOk();
        $failed=['schema_version'=>'1.0','source'=>'karlancer','items'=>[['type'=>'project','title'=>'پروژه ای مطابق با توانایی شما با عنوان «عنوان واقعی» ثبت گردید.','project_url'=>'https://www.karlancer.com/projects/preserve-1','description'=>null,'skills'=>[],'extraction_diagnostics'=>['status'=>'failed']]]];
        $this->withHeader('Authorization','Bearer test-token')->postJson('/api/internal/collector/import',$failed)->assertStatus(422)->assertJsonPath('projects_rejected',1);
        $this->assertDatabaseHas('projects',['canonical_url'=>'https://www.karlancer.com/projects/preserve-1','title'=>'عنوان واقعی','description'=>'شرح معتبر']);
    }

    public function test_imported_projects_are_visible_in_existing_management_pages(): void {
        $project=Project::factory()->create(['platform'=>'karlancer','decision_status'=>'new','title'=>'پروژه واردشده','description'=>'شرح واردشده','canonical_url'=>'https://www.karlancer.com/projects/ui-1']);
        $admin=User::factory()->create();
        $this->actingAs($admin)->get('/projects/status/new')->assertOk()->assertSee('پروژه واردشده');
        $this->actingAs($admin)->get('/projects/'.$project->id)->assertOk()->assertSee('شرح واردشده')->assertSee('www.karlancer.com/projects/ui-1');
    }
}
