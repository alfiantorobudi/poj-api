<?php

use App\Ai\Agents\ContentStrategist;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    #[Url(as: 'c')]
    public ?string $activeConversationId = null;

    public string $search = '';
    public string $promptInput = '';
    public bool $isLoading = false;
    public string $errorMessage = '';

    // Editing title state
    public ?string $editingConversationId = null;
    public string $editTitleInput = '';

    // Delete confirmation state
    public ?string $confirmingDeleteId = null;

    // Quick Tone selector
    public string $selectedTone = 'Professional';

    public function mount(?string $initialConversationId = null): void
    {
        if (empty($this->activeConversationId) && ! empty($initialConversationId)) {
            $this->activeConversationId = $initialConversationId;
        }

        // Verify active conversation belongs to current user
        if ($this->activeConversationId) {
            $exists = Auth::user()?->conversations()
                ->where('id', $this->activeConversationId)
                ->exists();

            if (! $exists) {
                $this->activeConversationId = null;
            }
        }
    }

    /**
     * Get all conversations for the authenticated user.
     *
     * @return \Illuminate\Support\Collection<int, Conversation>
     */
    #[Computed]
    public function conversations(): \Illuminate\Support\Collection
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        return $user->conversations()
            ->withCount('messages')
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                        ->orWhereHas('messages', function ($mq) {
                            $mq->where('content', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->latest('updated_at')
            ->get();
    }

    /**
     * Get the active conversation with its messages.
     */
    #[Computed]
    public function activeConversation(): ?Conversation
    {
        if (! $this->activeConversationId) {
            return null;
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return $user->conversations()
            ->with(['messages' => fn ($q) => $q->orderBy('created_at', 'asc')])
            ->find($this->activeConversationId);
    }

    /**
     * Get the messages for the active conversation.
     *
     * @return \Illuminate\Support\Collection<int, ConversationMessage>
     */
    #[Computed]
    public function messages(): \Illuminate\Support\Collection
    {
        return $this->activeConversation?->messages ?? collect();
    }

    /**
     * Select and restore a conversation.
     */
    public function selectConversation(string $conversationId): void
    {
        $this->errorMessage = '';
        $this->editingConversationId = null;
        $this->confirmingDeleteId = null;

        /** @var User|null $user */
        $user = Auth::user();

        $conversation = $user?->conversations()->find($conversationId);

        if ($conversation) {
            $this->activeConversationId = $conversation->id;
        }
    }

    /**
     * Start a fresh new conversation.
     */
    public function startNewConversation(): void
    {
        $this->activeConversationId = null;
        $this->promptInput = '';
        $this->errorMessage = '';
        $this->editingConversationId = null;
        $this->confirmingDeleteId = null;
    }

    /**
     * Send prompt to the AI agent and store messages in database.
     */
    public function sendMessage(): void
    {
        $this->validate([
            'promptInput' => 'required|string|min:2',
        ], [
            'promptInput.required' => 'Pesan tidak boleh kosong.',
            'promptInput.min' => 'Pesan minimal 2 karakter.',
        ]);

        $prompt = trim($this->promptInput);
        $this->promptInput = '';
        $this->isLoading = true;
        $this->errorMessage = '';

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->errorMessage = 'Silakan login terlebih dahulu.';
            $this->isLoading = false;
            return;
        }

        try {
            $agent = new ContentStrategist;

            if ($this->activeConversationId) {
                $agent = $agent->continue($this->activeConversationId, as: $user);
            } else {
                $agent = $agent->forUser($user);
            }

            $response = $agent->prompt(
                "Tone of Voice: {$this->selectedTone}\n\nPrompt:\n{$prompt}"
            );

            // Update active conversation ID from response if it was newly created
            if ($response->conversationId) {
                $this->activeConversationId = $response->conversationId;
            }

            unset($this->conversations);
            unset($this->activeConversation);
            unset($this->messages);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal memproses pesan: ' . $e->getMessage();
            // Restore prompt input so user doesn't lose text
            $this->promptInput = $prompt;
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Set a quick starter prompt.
     */
    public function useQuickPrompt(string $starterText): void
    {
        $this->promptInput = $starterText;
    }

    /**
     * Begin editing the conversation title.
     */
    public function startEditingTitle(string $conversationId, string $currentTitle): void
    {
        $this->editingConversationId = $conversationId;
        $this->editTitleInput = $currentTitle;
    }

    /**
     * Cancel editing title.
     */
    public function cancelEditingTitle(): void
    {
        $this->editingConversationId = null;
        $this->editTitleInput = '';
    }

    /**
     * Save the edited conversation title.
     */
    public function saveTitle(): void
    {
        $this->validate([
            'editTitleInput' => 'required|string|min:1|max:150',
        ], [
            'editTitleInput.required' => 'Judul percakapan tidak boleh kosong.',
            'editTitleInput.max' => 'Judul maksimal 150 karakter.',
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $this->editingConversationId) {
            return;
        }

        $conversation = $user->conversations()->find($this->editingConversationId);

        if ($conversation) {
            $conversation->update([
                'title' => trim($this->editTitleInput),
            ]);
        }

        $this->editingConversationId = null;
        $this->editTitleInput = '';

        unset($this->conversations);
        unset($this->activeConversation);
    }

    /**
     * Prompt delete confirmation.
     */
    public function confirmDelete(string $conversationId): void
    {
        $this->confirmingDeleteId = $conversationId;
    }

    /**
     * Cancel delete confirmation.
     */
    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Delete the conversation and cascade its messages.
     */
    public function deleteConversation(string $conversationId): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $conversation = $user->conversations()->find($conversationId);

        if ($conversation) {
            // Delete associated messages
            $conversation->messages()->delete();
            $conversation->delete();
        }

        if ($this->activeConversationId === $conversationId) {
            $this->activeConversationId = null;
        }

        $this->confirmingDeleteId = null;

        unset($this->conversations);
        unset($this->activeConversation);
        unset($this->messages);
    }
};
?>

<div class="h-[calc(100vh-8rem)] w-full flex flex-col lg:flex-row gap-4 max-w-7xl mx-auto"
    x-data="{
        mobileSidebarOpen: false,
        scrollToBottom() {
            $nextTick(() => {
                const el = this.$refs.messageContainer;
                if (el) { el.scrollTop = el.scrollHeight; }
            });
        }
    }"
    x-init="scrollToBottom()"
    @message-received.window="scrollToBottom()">

    <!-- LEFT SIDEBAR: Conversation History -->
    <div class="w-full lg:w-80 shrink-0 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-sm flex flex-col overflow-hidden h-full max-h-[380px] lg:max-h-full">
        
        <!-- Sidebar Header & Action -->
        <div class="p-4 border-b border-neutral-200 dark:border-neutral-800 space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="size-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <svg class="size-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-neutral-900 dark:text-neutral-100">Riwayat Chat</h2>
                        <p class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ $this->conversations->count() }} Percakapan Tersimpan</p>
                    </div>
                </div>

                <button type="button" wire:click="startNewConversation"
                    class="p-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5"
                    title="Buat Percakapan Baru">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="hidden sm:inline">Baru</span>
                </button>
            </div>

            <!-- Search Bar -->
            <div class="relative">
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari percakapan..."
                    class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/80 text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" />
                <svg class="size-3.5 text-neutral-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                @if(filled($search))
                    <button type="button" wire:click="$set('search', '')" class="absolute right-2.5 top-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <!-- Conversation Item List -->
        <div class="flex-1 overflow-y-auto p-2 space-y-1.5 divide-y divide-neutral-100 dark:divide-neutral-800/50">
            @forelse($this->conversations as $conversation)
                <div wire:key="conv-{{ $conversation->id }}"
                    class="group relative rounded-xl p-2.5 transition flex flex-col gap-1 cursor-pointer
                    {{ $activeConversationId === $conversation->id
                        ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/80 text-indigo-900 dark:text-indigo-200'
                        : 'hover:bg-neutral-100 dark:hover:bg-neutral-800/70 border border-transparent text-neutral-700 dark:text-neutral-300' }}">

                    <!-- Title & Inline Edit or Display -->
                    @if($editingConversationId === $conversation->id)
                        <form wire:submit="saveTitle" class="flex items-center gap-1.5" @click.stop>
                            <input type="text" wire:model="editTitleInput" autofocus
                                class="flex-1 px-2 py-1 text-xs rounded-lg border border-indigo-400 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                            <button type="submit" class="p-1 rounded-md text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950" title="Simpan">
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                            <button type="button" wire:click="cancelEditingTitle" class="p-1 rounded-md text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700" title="Batal">
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </form>
                    @else
                        <div class="flex items-start justify-between gap-2" wire:click="selectConversation('{{ $conversation->id }}')">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold truncate leading-tight">
                                    {{ $conversation->title }}
                                </p>
                                <div class="flex items-center gap-2 mt-1 text-[10px] text-neutral-400 dark:text-neutral-500">
                                    <span>{{ $conversation->updated_at->diffForHumans() }}</span>
                                    <span>•</span>
                                    <span>{{ $conversation->messages_count }} pesan</span>
                                </div>
                            </div>

                            <!-- Actions Menu (Edit Title & Delete) -->
                            <div class="opacity-0 group-hover:opacity-100 transition flex items-center gap-0.5 shrink-0" @click.stop>
                                <button type="button" wire:click="startEditingTitle('{{ $conversation->id }}', @js($conversation->title))"
                                    class="p-1 rounded-lg text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-neutral-200/60 dark:hover:bg-neutral-800 transition"
                                    title="Edit Judul">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button type="button" wire:click="confirmDelete('{{ $conversation->id }}')"
                                    class="p-1 rounded-lg text-neutral-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-neutral-200/60 dark:hover:bg-neutral-800 transition"
                                    title="Hapus Percakapan">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Inline Delete Confirmation -->
                        @if($confirmingDeleteId === $conversation->id)
                            <div class="mt-2 p-2 rounded-lg bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-800 flex items-center justify-between text-[11px]" @click.stop>
                                <span class="text-red-700 dark:text-red-300 font-medium">Hapus percakapan ini?</span>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" wire:click="deleteConversation('{{ $conversation->id }}')"
                                        class="px-2 py-0.5 rounded bg-red-600 hover:bg-red-700 text-white font-semibold text-[10px] transition">
                                        Hapus
                                    </button>
                                    <button type="button" wire:click="cancelDelete"
                                        class="px-2 py-0.5 rounded bg-neutral-200 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-[10px]">
                                        Batal
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            @empty
                <div class="py-12 px-4 text-center">
                    <div class="size-10 rounded-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mx-auto text-neutral-400 mb-2">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <p class="text-xs font-medium text-neutral-600 dark:text-neutral-400">Belum ada riwayat chat</p>
                    <p class="text-[10px] text-neutral-400 dark:text-neutral-500 mt-0.5">Kirim prompt untuk memulai percakapan baru.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- MAIN CHAT AREA -->
    <div class="flex-1 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-sm flex flex-col overflow-hidden h-full">

        <!-- Chat Header -->
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-900/50">
            <div class="flex items-center gap-3 min-w-0">
                <div class="size-9 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm shadow-sm shrink-0">
                    AI
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-sm sm:text-base font-bold text-neutral-900 dark:text-neutral-100 truncate">
                            {{ $this->activeConversation?->title ?? 'Percakapan Baru' }}
                        </h1>
                        @if($this->activeConversation)
                            <button type="button" wire:click="startEditingTitle('{{ $this->activeConversation->id }}', @js($this->activeConversation->title))"
                                class="text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Ganti Judul">
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-neutral-400 dark:text-neutral-500">
                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            ContentStrategist
                        </span>
                        <span>•</span>
                        <span>Ollama / minimax-m3</span>
                        @if($this->activeConversation)
                            <span>•</span>
                            <span>{{ $this->messages->count() }} Pesan</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Header Controls -->
            <div class="flex items-center gap-2">
                @if($this->activeConversation)
                    <button type="button" wire:click="startNewConversation"
                        class="px-3 py-1.5 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 transition flex items-center gap-1">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Baru</span>
                    </button>
                    <button type="button" wire:click="confirmDelete('{{ $this->activeConversation->id }}')"
                        class="p-2 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/50 rounded-xl border border-red-200 dark:border-red-800 transition"
                        title="Hapus Percakapan Ini">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <!-- Error Notification -->
        @if($errorMessage)
            <div class="m-4 p-3 rounded-xl bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-xs flex items-start justify-between gap-2 shadow-sm">
                <div class="flex items-start gap-2">
                    <svg class="size-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errorMessage }}</span>
                </div>
                <button type="button" wire:click="$set('errorMessage', '')" class="text-red-500 hover:text-red-700">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        <!-- Message List Container -->
        <div x-ref="messageContainer" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6">
            @if($this->messages->isNotEmpty())
                @foreach($this->messages as $msg)
                    <div wire:key="msg-{{ $msg->id }}"
                        class="flex gap-3 {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}"
                        x-data="{ copied: false }">
                        
                        <!-- AI Avatar -->
                        @if($msg->role !== 'user')
                            <div class="size-8 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white text-xs font-bold flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                                AI
                            </div>
                        @endif

                        <!-- Message Content Bubble -->
                        <div class="max-w-[85%] sm:max-w-[75%] rounded-2xl p-4 space-y-2
                            {{ $msg->role === 'user'
                                ? 'bg-indigo-600 text-white rounded-tr-none shadow-sm'
                                : 'bg-neutral-100 dark:bg-neutral-800/90 text-neutral-900 dark:text-neutral-100 border border-neutral-200/80 dark:border-neutral-700/70 rounded-tl-none shadow-sm' }}">
                            
                            <!-- Bubble Header -->
                            <div class="flex items-center justify-between gap-4 text-[10px] {{ $msg->role === 'user' ? 'text-indigo-200' : 'text-neutral-400 dark:text-neutral-500' }}">
                                <span class="font-semibold uppercase tracking-wider">
                                    {{ $msg->role === 'user' ? 'Anda' : 'ContentStrategist (AI)' }}
                                </span>
                                <span>{{ $msg->created_at->format('H:i') }}</span>
                            </div>

                            <!-- Text Body -->
                            <div class="text-xs sm:text-sm leading-relaxed whitespace-pre-wrap font-sans break-words select-text"
                                x-ref="msgBody{{ $msg->id }}">
                                {{ $msg->content }}
                            </div>

                            <!-- Bubble Footer Tools (Copy, Re-use) -->
                            <div class="flex items-center justify-end gap-2 pt-1 border-t {{ $msg->role === 'user' ? 'border-indigo-500/50' : 'border-neutral-200 dark:border-neutral-700/50' }}">
                                <button type="button"
                                    @click="navigator.clipboard.writeText($refs.msgBody{{ $msg->id }}.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="text-[10px] font-medium px-2 py-0.5 rounded transition flex items-center gap-1
                                        {{ $msg->role === 'user'
                                            ? 'text-indigo-200 hover:text-white hover:bg-indigo-700/60'
                                            : 'text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 hover:bg-neutral-200/60 dark:hover:bg-neutral-700' }}">
                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                                </button>
                                @if($msg->role === 'user')
                                    <button type="button" wire:click="useQuickPrompt(@js($msg->content))"
                                        class="text-[10px] font-medium text-indigo-200 hover:text-white hover:bg-indigo-700/60 px-2 py-0.5 rounded transition"
                                        title="Edit dan Kirim Ulang">
                                        Gunakan Lagi
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- User Avatar -->
                        @if($msg->role === 'user')
                            <div class="size-8 rounded-xl bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-200 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                                {{ auth()->user()?->initials() ?? 'U' }}
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <!-- Empty State / Starter Suggestions -->
                <div class="h-full flex flex-col items-center justify-center text-center p-6 space-y-6 max-w-md mx-auto my-auto">
                    <div class="size-16 rounded-3xl bg-gradient-to-tr from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Mulai Percakapan Baru</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                            Kirimkan prompt ide konten, transkrip berita, atau topik untuk diproses menjadi konten Twitter, LinkedIn, Instagram, dan Video Script.
                        </p>
                    </div>

                    <!-- Starter Cards -->
                    <div class="w-full grid grid-cols-1 gap-2 text-left">
                        <button type="button" wire:click="useQuickPrompt('Buatkan ide konten edukasi tentang perkembangan kecerdasan buatan (AI) untuk LinkedIn dan Twitter')"
                            class="p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/50 hover:border-indigo-300 dark:hover:border-indigo-700 transition group">
                            <p class="text-xs font-semibold text-neutral-800 dark:text-neutral-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">🚀 Tren AI & Teknologi</p>
                            <p class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-1">Buatkan ide konten edukasi tentang AI untuk LinkedIn & Twitter</p>
                        </button>

                        <button type="button" wire:click="useQuickPrompt('Tuliskan script video reels 30 detik untuk mempromosikan fitur automasi konten digital')"
                            class="p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/50 hover:border-indigo-300 dark:hover:border-indigo-700 transition group">
                            <p class="text-xs font-semibold text-neutral-800 dark:text-neutral-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">🎬 Script Video Pendek</p>
                            <p class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-1">Script video reels 30 detik format visual & audio</p>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Loading Bubble -->
            @if($isLoading)
                <div class="flex gap-3 justify-start items-center">
                    <div class="size-8 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white text-xs font-bold flex items-center justify-center shrink-0 shadow-sm animate-pulse">
                        AI
                    </div>
                    <div class="bg-neutral-100 dark:bg-neutral-800/90 rounded-2xl p-4 border border-neutral-200/80 dark:border-neutral-700/70 flex items-center gap-3">
                        <div class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-indigo-600 dark:bg-indigo-400 animate-bounce"></span>
                            <span class="size-2 rounded-full bg-indigo-600 dark:bg-indigo-400 animate-bounce [animation-delay:0.2s]"></span>
                            <span class="size-2 rounded-full bg-indigo-600 dark:bg-indigo-400 animate-bounce [animation-delay:0.4s]"></span>
                        </div>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400 font-medium">ContentStrategist sedang berpikir & memproses via Ollama...</span>
                    </div>
                </div>
            @endif
        </div>

        <!-- Chat Input Form -->
        <div class="p-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50/40 dark:bg-neutral-900/40 space-y-3">
            
            <!-- Quick Tone Controls -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-neutral-500 dark:text-neutral-400 text-[11px] font-medium">Tone:</span>
                    <select wire:model="selectedTone"
                        class="px-2.5 py-1 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-1 focus:ring-indigo-500 transition font-medium">
                        <option value="Professional">💼 Professional</option>
                        <option value="Casual">☕ Casual</option>
                        <option value="Witty/Bold">🔥 Witty &amp; Bold</option>
                    </select>
                </div>

                <span class="text-[10px] text-neutral-400 dark:text-neutral-500">Tekan Enter untuk kirim, Shift+Enter untuk baris baru</span>
            </div>

            <!-- Input Box -->
            <form wire:submit="sendMessage" class="relative">
                <textarea wire:model="promptInput"
                    placeholder="Tulis prompt atau instruksi konten Anda di sini..."
                    rows="3"
                    @keydown.enter.exact.prevent="$wire.sendMessage()"
                    class="w-full p-3.5 pr-24 text-xs sm:text-sm rounded-2xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm resize-none transition"
                    @disabled($isLoading)></textarea>

                <div class="absolute right-2.5 bottom-3 flex items-center gap-1.5">
                    @if(filled($promptInput))
                        <button type="button" wire:click="$set('promptInput', '')"
                            class="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 rounded-xl"
                            title="Bersihkan input">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endif

                    <button type="submit"
                        wire:loading.attr="disabled"
                        @disabled($isLoading || blank($promptInput))
                        class="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-semibold shadow-sm transition flex items-center justify-center"
                        title="Kirim Pesan">
                        <span wire:loading.remove wire:target="sendMessage">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="sendMessage">
                            <svg class="animate-spin size-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
            @error('promptInput')
                <span class="text-xs text-red-500 block font-medium">{{ $message }}</span>
            @enderror
        </div>

    </div>
</div>