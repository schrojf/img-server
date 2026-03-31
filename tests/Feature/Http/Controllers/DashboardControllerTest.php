<?php

test('dashboard requires authentication', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('authenticated user can access dashboard', function () {
    $user = user();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee($user->name)
        ->assertSee($user->email);
});

test('dashboard displays image statuses section', function () {
    $this->actingAs(user())
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Image Statuses')
        ->assertSee('queued')
        ->assertSee('total');
});

test('dashboard displays supported formats section', function () {
    $this->actingAs(user())
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Supported Image Formats')
        ->assertSee('image/jpeg')
        ->assertSee('image/webp');
});

test('dashboard displays registered variants section', function () {
    $this->actingAs(user())
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Registered Image Variants')
        ->assertSee('80x80wh')
        ->assertSee('600x600wh')
        ->assertSee('2000x2000wh');
});

test('dashboard displays configuration section', function () {
    $this->actingAs(user())
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Configuration')
        ->assertSee('images.driver')
        ->assertSee('images.avif');
});

test('dashboard displays tokens section', function () {
    $this->actingAs(user())
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('API Tokens');
});

test('dashboard displays users section', function () {
    $u = user();

    $this->actingAs($u)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Users')
        ->assertSee($u->name)
        ->assertSee($u->email);
});
