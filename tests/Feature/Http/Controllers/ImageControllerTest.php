<?php

use App\Models\Image;
use App\Models\User;

test('unauthorized index is rejected', function () {
    $response = $this->getJson('/api/images');

    $response->assertUnauthorized();
});

test('unauthorized index is rejected (ForceJsonResponse)', function () {
    $response = $this->get('/api/images');

    $response->assertUnauthorized();
});

test('empty images index', function () {
    $response = $this
        ->actingAs(user())
        ->get('/api/images');

    $response->assertStatus(200)
        ->assertJson([
            'total' => 0,
        ]);
});

test('images index', function () {
    image();

    $response = $this
        ->actingAs(user())
        ->get('/api/images');

    $response->assertStatus(200)
        ->assertJson([
            'total' => 1,
        ]);
});

test('only authorized user can create image', function () {
    $response = $this->postJson('/api/images');

    $response->assertUnauthorized();
});

test('only one image was created', function () {
    $user = user();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/images', ['url' => 'https://example.com/image.jpg']);

    $response->assertStatus(201)
        ->assertJson([
            'image' => [
                'original_url' => 'https://example.com/image.jpg',
            ],
            'is_new' => true,
        ]);

    $response = $this
        ->actingAs($user)
        ->postJson('/api/images', ['url' => 'https://example.com/image.jpg']);

    $response->assertStatus(200)
        ->assertJson([
            'image' => [
                'original_url' => 'https://example.com/image.jpg',
            ],
            'is_new' => false,
        ]);

    expect(Image::all()->count())->toBe(1);
});
