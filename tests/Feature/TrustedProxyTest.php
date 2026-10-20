<?php

use Illuminate\Support\Facades\Route;

it('treats a request forwarded as https by the hosting proxy as secure', function () {
    Route::get('/_proxy-check', fn () => request()->secure() ? 'secure '.url('/asset') : 'plain');

    $this->get('/_proxy-check', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'heatalert.example.com'])
        ->assertOk()
        ->assertSee('secure https://', false);

    $this->get('/_proxy-check')->assertSee('plain');
});
