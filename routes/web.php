<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('front.home');
});

Route::get('/admin', function () {
    return view('back.dashboard');
});
