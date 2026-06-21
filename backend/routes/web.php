<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Central Kitchen Food Recall System API',
        'version' => '1.0.0',
        'status' => 'running',
    ]);
});
