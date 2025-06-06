<?php

use App\Models\Image;
use App\Models\User;

test('unauthorized index is rejected', function () {
    $response = $this->getJson('/api/images');

    $response->assertUnauthorized();
});

test('empty images index', function () {
    $response = $this
        ->actingAs(User::factory()->create())
        ->get('/api/images');

    $response->assertStatus(200)
        ->assertJson([
            'total' => 0,
        ]);
});

test('images index', function () {
    Image::create();

    $response = $this
        ->actingAs(User::factory()->create())
        ->get('/api/images');

    $response->assertStatus(200)
        ->assertJson([
            'total' => 1,
        ]);
});
