<?php

use App\Http\Controllers\ImageApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/images', [ImageApiController::class, 'index']);
    Route::post('/images', [ImageApiController::class, 'store']);
    Route::get('/images/{id}', [ImageApiController::class, 'show']);

});
