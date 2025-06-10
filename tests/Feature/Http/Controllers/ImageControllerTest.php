<?php

use App\Jobs\DownloadImageJob;
use App\Models\Image;
use Illuminate\Support\Facades\Queue;

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
    Queue::fake([
        DownloadImageJob::class,
    ]);

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

    Queue::assertPushed(DownloadImageJob::class, 1);
});

test('image download job was dispatched', function () {
    Queue::fake([
        DownloadImageJob::class,
    ]);

    $response = $this
        ->actingAs(user())
        ->postJson('/api/images', ['url' => 'https://example.com/image_job.webp']);

    $response->assertStatus(201);

    Queue::assertPushed(DownloadImageJob::class, function (DownloadImageJob $job) {
        return Image::findOrFail($job->imageId)->original_url === 'https://example.com/image_job.webp';
    });
});
