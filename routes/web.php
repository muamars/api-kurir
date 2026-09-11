<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Debug route
Route::get('/debug-dashboard', function () {
    $user = auth()->user();
    $stats = [
        'blogs' => \App\Models\Blog::count(),
        'projects' => \App\Models\Project::count(),
        'users' => \App\Models\User::count(),
    ];

    return view('dashboard-debug', compact('stats'));
})->middleware('auth')->name('debug-dashboard');

// Blog Routes
Route::resource('blogs', BlogController::class);

// Project Routes
Route::resource('projects', ProjectController::class);

// Admin Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
});

// Visualisasi alur aplikasi — dev only
Route::get('/arsitektur', function () {
    return view('arsitektur');
})->name('arsitektur');

// /_brain-logic — alias publik untuk laravel-brain viewer
if (app()->isLocal()) {
    Route::prefix('_brain-logic')->group(function () {
        Route::get('/api/source', [\LaraMint\LaravelBrain\Http\Controllers\BrainController::class, 'source']);
        Route::post('/api/scan', [\LaraMint\LaravelBrain\Http\Controllers\BrainController::class, 'scan']);
        Route::post('/api/stress-test', [\LaraMint\LaravelBrain\Http\Controllers\BrainController::class, 'stressTest']);
        Route::get('/api/stress-test/{jobId}', [\LaraMint\LaravelBrain\Http\Controllers\BrainController::class, 'stressTestPoll']);
        Route::get('/api/context', [\LaraMint\LaravelBrain\Http\Controllers\BrainController::class, 'context']);
        Route::post('/api/generate-rules', [\LaraMint\LaravelBrain\Http\Controllers\BrainController::class, 'generateRules']);
        Route::get('/{any?}', [\LaraMint\LaravelBrain\Http\Controllers\BrainController::class, 'serve'])->where('any', '.*');
    });
}
