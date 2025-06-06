<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\get;

beforeEach(function () {
    Route::middleware([
        'api',
    ])->get('/test-json-middleware', function (Request $request) {
        return response()->json([
            'accept' => $request->header('accept'),
        ]);
    });
});

it('sets Accept header to application/json via middleware', function () {
    $response = get('/test-json-middleware');

    $response->assertOk();
    $response->assertJson([
        'accept' => 'application/json',
    ]);
});
