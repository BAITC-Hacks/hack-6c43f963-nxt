<?php

use App\Livewire\ContractorFinder;
use Illuminate\Support\Facades\Route;

Route::get('/', ContractorFinder::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
