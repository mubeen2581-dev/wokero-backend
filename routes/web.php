<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return response()->json([
        'message' => 'Workero API',
        'version' => '1.0.0',
    ]);
});

// Serve storage files (for avatars, etc.)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        abort(404);
    }
    
    $file = file_get_contents($filePath);
    $type = mime_content_type($filePath);
    
    return response($file, 200)->header('Content-Type', $type);
})->where('path', '.*');

