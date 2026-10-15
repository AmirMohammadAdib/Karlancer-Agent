<?php
namespace App\Http\Controllers;
use App\Jobs\ImportProjectsJob;
use App\Models\ImportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\View\View;
use App\Services\ProjectSources\ManualProjectSourceAdapter;
class ImportController extends Controller {
    public function sources(): View { return view('imports.sources', ['lastRun'=>ImportRun::where('source','mock')->latest('id')->first()]); }
    public function importMock(): RedirectResponse { $run=ImportRun::create(['source'=>'mock','status'=>'running','started_at'=>now()]); dispatch_sync(new ImportProjectsJob('mock',$run->id)); return back()->with('status','ورود داده‌های آزمایشی انجام شد.'); }
    public function index(): View { return view('imports.index', ['runs'=>ImportRun::latest('id')->paginate(15)]); }
    public function collectorProjects(): View { return view('imports.collector-projects', ['projects'=>\App\Models\Project::where('platform','karlancer')->latest('last_seen_at')->paginate(12)]); }
    public function manualForm(): View { return view('imports.manual'); }
    public function manualPreview(Request $request): View|RedirectResponse {
        $request->validate(['file'=>['required','file','mimes:json,csv,txt','max:2048']]);
        $file=$request->file('file'); $records=[]; $errors=[];
        try { $records=$this->parseFile($file->getRealPath(), $file->getClientOriginalExtension()); } catch (\Throwable $e) { $errors[]='ساختار فایل قابل خواندن نیست.'; }
        $adapter=new ManualProjectSourceAdapter([]); $valid=[];
        foreach ($records as $i=>$record) { try { $valid[]=$adapter->normalizeProject((array)$record); } catch (\Throwable $e) { $errors[]='ردیف '.($i+1).': داده نامعتبر است.'; } }
        if (!count($valid)) return back()->withErrors($errors ?: ['هیچ رکورد معتبری یافت نشد.']);
        $request->session()->put('manual_import_records',$valid);
        return view('imports.preview',['records'=>$valid,'errors'=>$errors]);
    }
    public function manualConfirm(Request $request): RedirectResponse {
        $records=$request->session()->pull('manual_import_records',[]); if (!count($records)) return back()->withErrors(['پیش‌نمایش منقضی شده است.']);
        $run=ImportRun::create(['source'=>'karlancer','status'=>'running','started_at'=>now()]); dispatch_sync(new ImportProjectsJob('karlancer',$run->id,$records));
        return redirect()->route('imports.index')->with('status','ورود دستی اطلاعات کارلنسر انجام شد.');
    }
    private function parseFile(string $path, string $extension): array {
        $raw=file_get_contents($path); if ($extension==='json') { $decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR); if (!is_array($decoded)) throw new \InvalidArgumentException(); return array_is_list($decoded)?$decoded:[$decoded]; }
        $handle=fopen($path,'rb'); $headers=fgetcsv($handle); if (!$headers) throw new \InvalidArgumentException(); $out=[]; while (($row=fgetcsv($handle))!==false) { if (count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0) continue; $out[]=array_combine($headers,array_pad($row,count($headers),'')); } fclose($handle); return $out;
    }
}
