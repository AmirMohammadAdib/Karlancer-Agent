<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', fn () => view('dashboard', ['counts' => ['new' => Project::where('decision_status', 'new')->count(), 'approved' => Project::where('decision_status', 'approved')->count(), 'rejected' => Project::where('decision_status', 'rejected')->count()]]))->name('dashboard');
    Route::get('/projects/{status}', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects/{project}/{decision}', [ProjectController::class, 'updateDecision'])->name('projects.decision');
    Route::get('/activity', [ProjectController::class, 'activity'])->name('activity');
    Route::view('/profile', 'placeholder', ['title' => 'پروفایل حرفه‌ای'])->name('profile');
    Route::view('/settings', 'placeholder', ['title' => 'تنظیمات'])->name('settings');
});
