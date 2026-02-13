<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return response()->json(['message' => 'ok!!!']);
});

Route::get('/admin', function (Request $request) {
    return response()->json(['message' => 'admin']);
});

Route::middleware(['auth'])->get('/user', function (Request $request) {
    return $request->user();
});
