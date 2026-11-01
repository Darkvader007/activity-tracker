<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::livewire('reports', 'pages::reports')
    ->middleware(['auth', 'verified'])
    ->name('reports');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::board')->name('dashboard');
    Route::livewire('reports', 'pages::reports')->name('reports');
});

require __DIR__.'/settings.php';
