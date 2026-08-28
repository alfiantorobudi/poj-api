<?php

use App\Ai\Agents\ContentStrategist;
use App\Models\User;
use Laravel\Ai\Models\Conversation;
use Livewire\Livewire;

test('guests are redirected from conversation page', function () {
    $response = $this->get(route('conversation'));

    $response->assertRedirect(route('login'));
});

test('authenticated user can visit conversation page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('conversation'));

    $response->assertOk();
    $response->assertSeeLivewire('conversation');
});

test('sending a prompt creates records in agent_conversations and agent_conversation_messages', function () {
    ContentStrategist::fake([
        'Ini adalah rekomendasi konten Twitter dan LinkedIn dari AI.',
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('conversation')
        ->set('promptInput', 'Buatkan thread Twitter tentang inovasi teknologi.')
        ->call('sendMessage')
        ->assertHasNoErrors();

    // Verify conversation created
    $this->assertDatabaseHas('agent_conversations', [
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    // Verify user and assistant messages created
    $this->assertDatabaseHas('agent_conversation_messages', [
        'role' => 'user',
        'participant_id' => $user->id,
    ]);

    $this->assertDatabaseHas('agent_conversation_messages', [
        'role' => 'assistant',
        'participant_id' => $user->id,
        'content' => 'Ini adalah rekomendasi konten Twitter dan LinkedIn dari AI.',
    ]);
});

test('user can restore and continue an existing conversation', function () {
    ContentStrategist::fake([
        'Tentu, ini tambahan slide Instagram.',
    ]);

    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => (string) str()->uuid(),
        'title' => 'Diskusi Konten AI',
    ]);

    $conversation->messages()->create([
        'id' => (string) str()->uuid(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'agent' => ContentStrategist::class,
        'role' => 'user',
        'content' => 'Pesan pertama',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    Livewire::actingAs($user)
        ->test('conversation', ['initialConversationId' => $conversation->id])
        ->assertSet('activeConversationId', $conversation->id)
        ->assertSee('Pesan pertama')
        ->set('promptInput', 'Tambahkan format Instagram')
        ->call('sendMessage')
        ->assertHasNoErrors()
        ->assertSet('errorMessage', '');

    $this->assertDatabaseHas('agent_conversation_messages', [
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Tentu, ini tambahan slide Instagram.',
    ]);
});

test('user can edit conversation title', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => (string) str()->uuid(),
        'title' => 'Judul Lama',
    ]);

    Livewire::actingAs($user)
        ->test('conversation')
        ->call('startEditingTitle', $conversation->id, 'Judul Lama')
        ->set('editTitleInput', 'Judul Baru yang Diedit')
        ->call('saveTitle')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $conversation->id,
        'title' => 'Judul Baru yang Diedit',
    ]);
});

test('user can delete a conversation and its messages', function () {
    $user = User::factory()->create();

    $conversation = $user->conversations()->create([
        'id' => (string) str()->uuid(),
        'title' => 'Percakapan untuk dihapus',
    ]);

    $conversation->messages()->create([
        'id' => (string) str()->uuid(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'agent' => ContentStrategist::class,
        'role' => 'user',
        'content' => 'Pesan sementara',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    Livewire::actingAs($user)
        ->test('conversation', ['initialConversationId' => $conversation->id])
        ->call('deleteConversation', $conversation->id)
        ->assertSet('activeConversationId', null);

    $this->assertDatabaseMissing('agent_conversations', [
        'id' => $conversation->id,
    ]);

    $this->assertDatabaseMissing('agent_conversation_messages', [
        'conversation_id' => $conversation->id,
    ]);
});

test('user cannot edit or delete another users conversation', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $conversation = $user1->conversations()->create([
        'id' => (string) str()->uuid(),
        'title' => 'Private Conversation User 1',
    ]);

    Livewire::actingAs($user2)
        ->test('conversation')
        ->call('startEditingTitle', $conversation->id, 'Hacked Title')
        ->set('editTitleInput', 'Hacked Title')
        ->call('saveTitle');

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $conversation->id,
        'title' => 'Private Conversation User 1',
    ]);

    Livewire::actingAs($user2)
        ->test('conversation')
        ->call('deleteConversation', $conversation->id);

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $conversation->id,
    ]);
});

test('content analyze endpoint stores conversation for authenticated user', function () {
    ContentStrategist::fake([
        'Hasil generate content dari endpoint analyze.',
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('content.analyze'), [
        'transcript' => 'Ini transkrip berita penting tentang ekonomi digital dan peluncuran produk baru.',
        'selectedTone' => 'Professional',
        'selectedPlatform' => 'Twitter, LinkedIn',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'content',
        'conversation_id',
    ]);

    $conversationId = $response->json('conversation_id');
    expect($conversationId)->not->toBeNull();

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $conversationId,
        'participant_id' => $user->id,
    ]);

    $this->assertDatabaseHas('agent_conversation_messages', [
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'content' => 'Hasil generate content dari endpoint analyze.',
    ]);
});
