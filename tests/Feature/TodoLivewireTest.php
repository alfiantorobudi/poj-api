<?php

use App\Models\Todo;
use App\Models\User;
use Livewire\Livewire;

it('renders the todo view for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('todos.index'))
        ->assertStatus(200)
        ->assertSee('Task &amp; Todo Manager', false);
});

it('creates a new todo through livewire component', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('todo')
        ->set('title', 'Learn TALL Stack')
        ->set('description', 'Master Tailwind, Alpine, Livewire, and Laravel')
        ->set('priority', 'high')
        ->call('createTodo')
        ->assertHasNoErrors();

    expect(Todo::where('user_id', $user->id)->where('title', 'Learn TALL Stack')->exists())->toBeTrue();
});

it('toggles completion status of a todo through livewire', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->create([
        'user_id' => $user->id,
        'is_completed' => false,
    ]);

    Livewire::actingAs($user)
        ->test('todo')
        ->call('toggleComplete', $todo->id);

    expect($todo->fresh()->is_completed)->toBeTrue();

    Livewire::actingAs($user)
        ->test('todo')
        ->call('toggleComplete', $todo->id);

    expect($todo->fresh()->is_completed)->toBeFalse();
});

it('opens edit modal and updates a todo through livewire', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->create([
        'user_id' => $user->id,
        'title' => 'Initial Title',
        'priority' => 'low',
    ]);

    Livewire::actingAs($user)
        ->test('todo')
        ->call('openEditModal', $todo->id)
        ->assertSet('editingTodoId', $todo->id)
        ->assertSet('editTitle', 'Initial Title')
        ->set('editTitle', 'Updated via Modal')
        ->set('editPriority', 'high')
        ->call('updateTodo')
        ->assertSet('showEditModal', false);

    expect($todo->fresh()->title)->toBe('Updated via Modal');
    expect($todo->fresh()->priority)->toBe('high');
});

it('deletes a todo through livewire', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->create([
        'user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('todo')
        ->call('deleteTodo', $todo->id);

    expect(Todo::find($todo->id))->toBeNull();
});

it('filters and searches todos through livewire', function () {
    $user = User::factory()->create();

    Todo::factory()->create([
        'user_id' => $user->id,
        'title' => 'Grocery Shopping',
        'is_completed' => false,
        'priority' => 'low',
    ]);

    Todo::factory()->create([
        'user_id' => $user->id,
        'title' => 'Deploy Application',
        'is_completed' => true,
        'priority' => 'high',
    ]);

    Livewire::actingAs($user)
        ->test('todo')
        ->set('filterStatus', 'pending')
        ->assertSee('Grocery Shopping')
        ->assertDontSee('Deploy Application')
        ->set('filterStatus', 'completed')
        ->assertSee('Deploy Application')
        ->assertDontSee('Grocery Shopping')
        ->set('filterStatus', 'all')
        ->set('search', 'Deploy')
        ->assertSee('Deploy Application')
        ->assertDontSee('Grocery Shopping');
});
