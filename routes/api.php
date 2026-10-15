<?php
use App\Http\Controllers\InternalCollectorController; use Illuminate\Support\Facades\Route;
Route::middleware('throttle:10,1')->post('/internal/collector/import',[InternalCollectorController::class,'import'])->name('internal.collector.import');
