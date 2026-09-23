<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::contractor-finder')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
