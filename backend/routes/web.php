<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $index = public_path('index.html');
    abort_unless(is_file($index), 503, 'Compila la interfaz con npm run build.');

    return response()->file($index, ['Cache-Control' => 'no-cache']);
});
