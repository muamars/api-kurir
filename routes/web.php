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
