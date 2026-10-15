<?php
namespace App\Jobs;
use App\Models\ImportRun;
use App\Services\ProjectIngestionService;
use App\Services\ProjectSources\MockProjectSourceAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
class ImportProjectsJob implements ShouldQueue, ShouldBeUnique {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $uniqueFor = 3600;
    public function __construct(public string $source='mock', public ?int $importRunId=null) {}
    public function uniqueId(): string { return $this->source; }
    public function handle(): void {
        $run = $this->importRunId ? ImportRun::findOrFail($this->importRunId) : ImportRun::create(['source'=>$this->source,'status'=>'running','started_at'=>now()]);
        $adapter = match ($this->source) { 'mock' => new MockProjectSourceAdapter(), default => throw new \InvalidArgumentException('Unknown source.') };
        app(ProjectIngestionService::class, ['adapter'=>$adapter])->import($run);
    }
    public function failed(\Throwable $e): void { if ($this->importRunId && ($run=ImportRun::find($this->importRunId))) $run->update(['status'=>'failed','finished_at'=>now(),'errors'=>[substr($e->getMessage(),0,200)]]); }
}
