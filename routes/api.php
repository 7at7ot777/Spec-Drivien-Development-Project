<?php

use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('ping', fn () => ApiResponse::success(['status' => 'ok']))->name('api.ping');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', fn (Request $request) => ApiResponse::success(new UserResource($request->user())))->name('api.me');
});
