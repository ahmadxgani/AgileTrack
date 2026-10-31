<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');

    Route::post('{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::patch('{project}/members/{membership}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
    Route::delete('{project}/members/{membership}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
});
