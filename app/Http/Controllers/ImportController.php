<?php
namespace App\Http\Controllers;
use App\Jobs\ImportProjectsJob;
use App\Models\ImportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\View\View;
class ImportController extends Controller {
    public function sources(): View { return view('imports.sources', ['lastRun'=>ImportRun::where('source','mock')->latest('id')->first()]); }
    public function importMock(): RedirectResponse { $run=ImportRun::create(['source'=>'mock','status'=>'running','started_at'=>now()]); dispatch_sync(new ImportProjectsJob('mock',$run->id)); return back()->with('status','ورود داده‌های آزمایشی انجام شد.'); }
    public function index(): View { return view('imports.index', ['runs'=>ImportRun::latest('id')->paginate(15)]); }
}
