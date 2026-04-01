<?php

use App\Livewire\CloneRepo;
use App\Livewire\GitHubLogin;
use App\Livewire\Landing;
use App\Livewire\Workspace;
use Illuminate\Support\Facades\Route;

Route::get('/', Landing::class);
Route::get('/github/login', GitHubLogin::class);
Route::get('/clone', CloneRepo::class);
Route::get('/repo', Workspace::class)->name('repo.show');
