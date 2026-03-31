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
