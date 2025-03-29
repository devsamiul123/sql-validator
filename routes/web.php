<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// This will catch all routes and direct them to the SPA
Route::get('/{any}', fn() => view('welcome'))->where('any', '.*');
