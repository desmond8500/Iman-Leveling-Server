<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Documentation interactive de l'API (Swagger UI) ; la description est dans public/openapi.json.
Route::view('/docs', 'docs');
