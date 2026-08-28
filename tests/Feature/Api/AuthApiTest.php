<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a new user and returns an api token', function () {
    $response = $this->postJson(route('api.register'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
            'token',
        ]);

    expect(User::where('email', 'john@example.com')->exists())->toBeTrue();
});

it('fails registration when email is already taken or passwords do not match', function () {
    User::factory()->create(['email' => 'john@example.com']);

    $response = $this->postJson(route('api.register'), [
        'name' => 'John Duplicate',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'mismatch123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('logs in an existing user with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'password' => Hash::make('secret12345'),
    ]);

    $response = $this->postJson(route('api.login'), [
        'email' => 'alice@example.com',
        'password' => 'secret12345',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email'],
            'token',
        ]);
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'password' => Hash::make('secret12345'),
    ]);

    $response = $this->postJson(route('api.login'), [
        'email' => 'alice@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('logs out and deletes the current personal access token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.logout'));

    $response->assertStatus(200)
        ->assertJson(['message' => 'Successfully logged out.']);

    expect($user->tokens()->count())->toBe(0);
});
