<?php

use App\Models\Todo;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to access todos api endpoints', function () {
    $this->getJson(route('api.todos.index'))->assertStatus(401);
    $this->postJson(route('api.todos.store'), ['title' => 'Test'])->assertStatus(401);
    $this->putJson(route('api.todos.update', 1), ['title' => 'Test'])->assertStatus(401);
    $this->deleteJson(route('api.todos.destroy', 1))->assertStatus(401);
});

it('returns list of todos belonging only to the authenticated user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Todo::factory()->count(3)->create(['user_id' => $user1->id]);
    Todo::factory()->count(2)->create(['user_id' => $user2->id]);

    Sanctum::actingAs($user1);

    $response = $this->getJson(route('api.todos.index'));

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('creates a new todo via POST /todos', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $payload = [
        'title' => 'Complete Laravel REST API project',
        'description' => 'Write clean endpoints and TALL stack interface',
        'priority' => 'high',
        'due_date' => now()->addDays(3)->toISOString(),
    ];

    $response = $this->postJson(route('api.todos.store'), $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Complete Laravel REST API project')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.is_completed', false);

    expect(Todo::where('user_id', $user->id)->where('title', 'Complete Laravel REST API project')->exists())->toBeTrue();
});

it('validates required fields when creating a todo', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson(route('api.todos.store'), [
        'priority' => 'invalid-priority',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'priority']);
});

it('fetches a single todo via GET /todos/{id}', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->create([
        'user_id' => $user->id,
        'title' => 'My specific task',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('api.todos.show', $todo->id));

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $todo->id)
        ->assertJsonPath('data.title', 'My specific task');
});

it('updates an existing todo via PUT /todos/{id}', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old Title',
        'is_completed' => false,
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson(route('api.todos.update', $todo->id), [
        'title' => 'Updated Title',
        'is_completed' => true,
        'priority' => 'high',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('data.priority', 'high');

    expect($todo->fresh()->is_completed)->toBeTrue();
    expect($todo->fresh()->completed_at)->not->toBeNull();
});

it('deletes a todo via DELETE /todos/{id}', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    $response = $this->deleteJson(route('api.todos.destroy', $todo->id));

    $response->assertStatus(200)
        ->assertJson(['message' => 'Todo deleted successfully.']);

    expect(Todo::find($todo->id))->toBeNull();
});

it('prevents a user from accessing or modifying another user todo', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $todoOfUser2 = Todo::factory()->create(['user_id' => $user2->id]);

    Sanctum::actingAs($user1);

    // Try GET
    $this->getJson(route('api.todos.show', $todoOfUser2->id))->assertStatus(404);

    // Try PUT
    $this->putJson(route('api.todos.update', $todoOfUser2->id), ['title' => 'Hacked'])->assertStatus(404);

    // Try DELETE
    $this->deleteJson(route('api.todos.destroy', $todoOfUser2->id))->assertStatus(404);

    expect($todoOfUser2->fresh()->title)->not->toBe('Hacked');
});
