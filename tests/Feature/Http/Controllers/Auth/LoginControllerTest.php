<?php

test('login page is accessible', function () {
    $this->get('/login')
        ->assertStatus(200)
        ->assertSee('Login');
});

test('authenticated user is redirected from login page', function () {
    $this->actingAs(user())
        ->get('/login')
        ->assertRedirect('/dashboard');
});

test('user can login with valid credentials', function () {
    $user = user(['password' => bcrypt('password')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('user can login with remember me', function () {
    $user = user(['password' => bcrypt('password')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('user cannot login with invalid credentials', function () {
    $user = user(['password' => bcrypt('password')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertRedirect()
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login requires email', function () {
    $this->post('/login', [
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

test('login requires password', function () {
    $this->post('/login', [
        'email' => 'test@example.com',
    ])->assertSessionHasErrors('password');
});

test('login requires valid email format', function () {
    $this->post('/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

test('user can logout', function () {
    $this->actingAs(user())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

test('guest cannot logout', function () {
    $this->post('/logout')
        ->assertRedirect('/login');
});
