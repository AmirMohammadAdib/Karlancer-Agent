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
    Route::get('/dashboard', fn () => view('dashboard', ['counts' => ['total' => Project::where('platform', 'mock')->count(), 'new' => Project::where('platform', 'mock')->where('decision_status', 'new')->count(), 'approved' => Project::where('platform', 'mock')->where('decision_status', 'approved')->count(), 'rejected' => Project::where('platform', 'mock')->where('decision_status', 'rejected')->count()]]))->name('dashboard');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/status/{status}', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects/{project}/{decision}', [ProjectController::class, 'updateDecision'])->name('projects.decision');
    Route::get('/activity', [ProjectController::class, 'activity'])->name('activity');
    Route::view('/profile', 'placeholder', ['title' => 'پروفایل حرفه‌ای'])->name('profile');
    Route::view('/settings', 'placeholder', ['title' => 'تنظیمات'])->name('settings');
});
