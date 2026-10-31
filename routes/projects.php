<?php

use App\Http\Controllers\BacklogItemController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
    // Sidebar entry, registered before {project} so the literal never binds
    // as a project id.
    Route::get('backlog', [BacklogItemController::class, 'shortcut'])->name('projects.backlog.shortcut');
    Route::get('{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');

    Route::post('{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::patch('{project}/members/{membership}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
    Route::delete('{project}/members/{membership}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');

    // Scoped bindings resolve the item through the project, so an item of
    // another project gives a 404 instead of being reachable by id.
    Route::scopeBindings()->prefix('{project}/backlog')->name('projects.backlog.')->group(function () {
        Route::get('/', [BacklogItemController::class, 'index'])->name('index');
        Route::get('create', [BacklogItemController::class, 'create'])->name('create');
        Route::post('/', [BacklogItemController::class, 'store'])->name('store');
        Route::get('{backlogItem}/edit', [BacklogItemController::class, 'edit'])->name('edit');
        Route::patch('{backlogItem}', [BacklogItemController::class, 'update'])->name('update');
        Route::patch('{backlogItem}/status', [BacklogItemController::class, 'status'])->name('status');
        Route::patch('{backlogItem}/archive', [BacklogItemController::class, 'archive'])->name('archive');
    });
});
