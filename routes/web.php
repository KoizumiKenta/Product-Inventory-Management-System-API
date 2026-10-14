<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/docs');

Route::view('/docs', 'docs')->name('docs');

Route::get('/docs/openapi.yaml', function () {
    return response()->file(base_path('docs/openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
})->name('docs.openapi');
