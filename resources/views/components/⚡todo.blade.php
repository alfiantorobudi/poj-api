<?php

use App\Models\Todo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $title = '';
    public string $description = '';
    public string $priority = 'medium';
    public ?string $due_date = null;

    public string $filterStatus = 'all';
    public string $filterPriority = 'all';
    public string $search = '';
    public string $sortBy = 'newest';

    public bool $showDescriptionInput = false;

    // Edit modal state
    public bool $showEditModal = false;
    public ?int $editingTodoId = null;
    public string $editTitle = '';
    public string $editDescription = '';
    public string $editPriority = 'medium';
    public ?string $editDueDate = null;

    // Toast notification
    public ?string $toastMessage = null;
    public string $toastType = 'success';

    /**
     * Create a new Todo item.
     */
    public function createTodo(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
        ], [
            'title.required' => 'Judul todo wajib diisi.',
            'title.max' => 'Judul todo maksimal 255 karakter.',
        ]);

        $user = auth()->user();

        Todo::create([
            'user_id' => $user->id,
            'title' => trim($this->title),
            'description' => $this->description ? trim($this->description) : null,
            'priority' => $this->priority,
            'due_date' => $this->due_date ? Carbon::parse($this->due_date) : null,
            'is_completed' => false,
        ]);

        $this->reset(['title', 'description', 'due_date', 'showDescriptionInput']);
        $this->priority = 'medium';

        $this->notify('Tugas baru berhasil ditambahkan!');
    }

    /**
     * Toggle completion status of a Todo.
     */
    public function toggleComplete(int $todoId): void
    {
        $todo = Todo::where('user_id', auth()->id())->find($todoId);

        if (! $todo) {
            return;
        }

        if ($todo->is_completed) {
            $todo->markAsPending();
            $this->notify('Tugas ditandai belum selesai.', 'info');
        } else {
            $todo->markAsCompleted();
            $this->notify('Tugas selesai! Kerja bagus 🎉', 'success');
        }
    }

    /**
     * Open the edit modal with current Todo data.
     */
    public function openEditModal(int $todoId): void
    {
        $todo = Todo::where('user_id', auth()->id())->find($todoId);

        if (! $todo) {
            return;
        }

        $this->editingTodoId = $todo->id;
        $this->editTitle = $todo->title;
        $this->editDescription = $todo->description ?? '';
        $this->editPriority = $todo->priority;
        $this->editDueDate = $todo->due_date ? $todo->due_date->format('Y-m-d\TH:i') : null;
        $this->showEditModal = true;
    }

    /**
     * Update the Todo item from the edit modal.
     */
    public function updateTodo(): void
    {
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editDescription' => 'nullable|string|max:5000',
            'editPriority' => 'required|in:low,medium,high',
            'editDueDate' => 'nullable|date',
        ], [
            'editTitle.required' => 'Judul todo wajib diisi.',
        ]);

        $todo = Todo::where('user_id', auth()->id())->find($this->editingTodoId);

        if ($todo) {
            $todo->update([
                'title' => trim($this->editTitle),
                'description' => $this->editDescription ? trim($this->editDescription) : null,
                'priority' => $this->editPriority,
                'due_date' => $this->editDueDate ? Carbon::parse($this->editDueDate) : null,
            ]);

            $this->notify('Tugas berhasil diperbarui!');
        }

        $this->closeEditModal();
    }

    /**
     * Close the edit modal.
     */
    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->reset(['editingTodoId', 'editTitle', 'editDescription', 'editPriority', 'editDueDate']);
    }

    /**
     * Delete a Todo item.
     */
    public function deleteTodo(int $todoId): void
    {
        $todo = Todo::where('user_id', auth()->id())->find($todoId);

        if ($todo) {
            $todo->delete();
            $this->notify('Tugas berhasil dihapus.', 'info');
        }
    }

    /**
     * Mark all pending todos as completed.
     */
    public function markAllCompleted(): void
    {
        $affected = Todo::where('user_id', auth()->id())
            ->where('is_completed', false)
            ->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);

        if ($affected > 0) {
            $this->notify("Semua tugas ($affected) berhasil diselesaikan! 🚀");
        }
    }

    /**
     * Delete all completed todos.
     */
    public function clearCompleted(): void
    {
        $deleted = Todo::where('user_id', auth()->id())
            ->where('is_completed', true)
            ->delete();

        if ($deleted > 0) {
            $this->notify("$deleted tugas selesai telah dibersihkan.", 'info');
        }
    }

    /**
     * Helper to show a temporary toast message.
     */
    public function notify(string $message, string $type = 'success'): void
    {
        $this->toastMessage = $message;
        $this->toastType = $type;
        $this->dispatch('toast-notify');
    }

    /**
     * Get filtered Todos.
     *
     * @return Collection<int, Todo>
     */
    #[Computed]
    public function todos(): Collection
    {
        $query = Todo::where('user_id', auth()->id());

        if ($this->filterStatus === 'pending') {
            $query->pending();
        } elseif ($this->filterStatus === 'completed') {
            $query->completed();
        }

        if ($this->filterPriority !== 'all') {
            $query->priority($this->filterPriority);
        }

        if (trim($this->search) !== '') {
            $query->search($this->search);
        }

        match ($this->sortBy) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'due_date' => $query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END"),
            default => $query->orderBy('created_at', 'desc'),
        };

        return $query->get();
    }

    /**
     * Get todo metrics.
     *
     * @return array{total: int, pending: int, completed: int, overdue: int, rate: int}
     */
    #[Computed]
    public function stats(): array
    {
        $userId = auth()->id();
        $total = Todo::where('user_id', $userId)->count();
        $completed = Todo::where('user_id', $userId)->where('is_completed', true)->count();
        $pending = $total - $completed;
        $overdue = Todo::where('user_id', $userId)
            ->where('is_completed', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();
        $rate = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return compact('total', 'pending', 'completed', 'overdue', 'rate');
    }
};

?>

<div class="w-full max-w-6xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6" x-data="{
    init() {
        window.addEventListener('toast-notify', () => {
            setTimeout(() => {
                $wire.toastMessage = null;
            }, 3500);
        });
    }
}">
    <!-- Toast Notification -->
    <div x-show="$wire.toastMessage"
        x-transition:enter="transform ease-out duration-300 transition"
        x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-sm font-medium backdrop-blur-md transition"
        :class="{
            'bg-emerald-950/90 text-emerald-200 border-emerald-800': $wire.toastType === 'success',
            'bg-zinc-900/90 text-zinc-100 border-zinc-700': $wire.toastType === 'info',
            'bg-red-950/90 text-red-200 border-red-800': $wire.toastType === 'error'
        }"
        style="display: none;">
        <template x-if="$wire.toastType === 'success'">
            <svg class="size-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </template>
        <template x-if="$wire.toastType === 'info'">
            <svg class="size-5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </template>
        <span x-text="$wire.toastMessage"></span>
    </div>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    <span class="size-2 rounded-full bg-indigo-500 animate-pulse"></span>
                    TALL Stack + REST API
                </span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50 mt-2">
                Task &amp; Todo Manager
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Kelola target, prioritas kerja, dan sinkronisasi tugas Anda secara real-time.
            </p>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex items-center gap-2.5">
            @if($this->stats['pending'] > 0)
                <button type="button"
                    wire:click="markAllCompleted"
                    wire:confirm="Tandai semua tugas sebagai selesai?"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-xl bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 transition">
                    <svg class="size-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Selesaikan Semua
                </button>
            @endif

            @if($this->stats['completed'] > 0)
                <button type="button"
                    wire:click="clearCompleted"
                    wire:confirm="Hapus semua tugas yang sudah selesai?"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-xl bg-zinc-100 hover:bg-red-50 dark:bg-zinc-800 dark:hover:bg-red-950/40 text-zinc-600 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400 border border-zinc-200 dark:border-zinc-700 hover:border-red-200 dark:hover:border-red-800 transition">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Bersihkan Selesai
                </button>
            @endif
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Tasks -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Total Tugas</span>
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->stats['total'] }}</span>
                <span class="text-xs text-zinc-400">item</span>
            </div>
        </div>

        <!-- In Progress -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Sedang Dikerjakan</span>
                <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $this->stats['pending'] }}</span>
                @if($this->stats['overdue'] > 0)
                    <span class="text-xs font-semibold text-rose-500 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded-md">
                        {{ $this->stats['overdue'] }} terlambat
                    </span>
                @endif
            </div>
        </div>

        <!-- Completed -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Selesai</span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $this->stats['completed'] }}</span>
                <span class="text-xs text-zinc-400">tuntas</span>
            </div>
        </div>

        <!-- Completion Rate Progress -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Progress</span>
                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $this->stats['rate'] }}%</span>
            </div>
            <div class="mt-4">
                <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-emerald-500 h-2.5 rounded-full transition-all duration-500"
                        style="width: {{ $this->stats['rate'] }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Add Task Card -->
    <div class="bg-white dark:bg-zinc-900 p-5 sm:p-6 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
        <form wire:submit="createTodo" class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="size-6 rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-700 flex items-center justify-center shrink-0">
                    <svg class="size-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <input type="text"
                    wire:model="title"
                    placeholder="Tambah tugas baru... (tekan Enter untuk menyimpan)"
                    class="w-full bg-transparent text-base sm:text-lg font-medium text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 border-none focus:outline-none focus:ring-0 p-0" />
            </div>

            @error('title')
                <p class="text-xs text-red-500 font-medium ml-9">{{ $message }}</p>
            @enderror

            <!-- Optional Details (Description, Priority, Due Date) -->
            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800/80 flex flex-wrap items-center justify-between gap-3">
                <!-- Priority & Date Inputs -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <!-- Priority Selector Pills -->
                    <div class="flex items-center gap-1 p-1 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl text-xs font-semibold">
                        <button type="button"
                            wire:click="$set('priority', 'low')"
                            class="px-2.5 py-1 rounded-lg transition {{ $priority === 'low' ? 'bg-emerald-500 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                            Low
                        </button>
                        <button type="button"
                            wire:click="$set('priority', 'medium')"
                            class="px-2.5 py-1 rounded-lg transition {{ $priority === 'medium' ? 'bg-amber-500 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                            Medium
                        </button>
                        <button type="button"
                            wire:click="$set('priority', 'high')"
                            class="px-2.5 py-1 rounded-lg transition {{ $priority === 'high' ? 'bg-rose-500 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                            High
                        </button>
                    </div>

                    <!-- Due Date Input -->
                    <div class="relative">
                        <input type="datetime-local"
                            wire:model="due_date"
                            class="px-3 py-1.5 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 text-zinc-700 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition" />
                    </div>

                    <!-- Toggle Description field -->
                    <button type="button"
                        wire:click="$toggle('showDescriptionInput')"
                        class="text-xs font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 flex items-center gap-1 py-1 px-2 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                        {{ $showDescriptionInput ? 'Tutup Deskripsi' : '+ Deskripsi' }}
                    </button>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-sm transition inline-flex items-center gap-2 disabled:opacity-50">
                    <span wire:loading.remove wire:target="createTodo">Tambah Tugas</span>
                    <span wire:loading wire:target="createTodo" class="inline-flex items-center gap-1">
                        <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Menyimpan...
                    </span>
                </button>
            </div>

            <!-- Expandable Description Textarea -->
            @if($showDescriptionInput)
                <div class="pt-2">
                    <textarea wire:model="description"
                        rows="2"
                        placeholder="Tambahkan detail catatan atau deskripsi tugas di sini..."
                        class="w-full p-3 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition resize-y"></textarea>
                </div>
            @endif
        </form>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Status Tabs -->
        <div class="flex items-center p-1 bg-zinc-100 dark:bg-zinc-800/80 rounded-2xl text-xs font-semibold self-start sm:self-auto">
            <button type="button"
                wire:click="$set('filterStatus', 'all')"
                class="px-3 py-1.5 rounded-xl transition {{ $filterStatus === 'all' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-50 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                Semua ({{ $this->stats['total'] }})
            </button>
            <button type="button"
                wire:click="$set('filterStatus', 'pending')"
                class="px-3 py-1.5 rounded-xl transition {{ $filterStatus === 'pending' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-50 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                Aktif ({{ $this->stats['pending'] }})
            </button>
            <button type="button"
                wire:click="$set('filterStatus', 'completed')"
                class="px-3 py-1.5 rounded-xl transition {{ $filterStatus === 'completed' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-50 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                Selesai ({{ $this->stats['completed'] }})
            </button>
        </div>

        <!-- Search & Dropdowns -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Search Input -->
            <div class="relative flex-1 sm:w-52">
                <svg class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari tugas..."
                    class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" />
            </div>

            <!-- Priority Filter -->
            <select wire:model.live="filterPriority"
                class="py-1.5 px-3 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <option value="all">Semua Prioritas</option>
                <option value="high">🔥 High Priority</option>
                <option value="medium">⚡ Medium Priority</option>
                <option value="low">🌱 Low Priority</option>
            </select>

            <!-- Sort By -->
            <select wire:model.live="sortBy"
                class="py-1.5 px-3 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <option value="newest">Terbaru</option>
                <option value="oldest">Terlama</option>
                <option value="due_date">Tenggat Waktu</option>
                <option value="priority">Prioritas Tertinggi</option>
            </select>
        </div>
    </div>

    <!-- Task List -->
    <div class="space-y-2.5">
        @forelse($this->todos as $todo)
            <div wire:key="todo-{{ $todo->id }}"
                class="group p-4 rounded-2xl bg-white dark:bg-zinc-900 border transition-all duration-200 flex items-start justify-between gap-3 shadow-sm hover:shadow-md
                    {{ $todo->is_completed
                        ? 'border-zinc-200/60 dark:border-zinc-800/60 bg-zinc-50/50 dark:bg-zinc-900/40 opacity-75'
                        : 'border-zinc-200 dark:border-zinc-800 hover:border-indigo-300 dark:hover:border-indigo-800' }}">

                <!-- Left: Checkbox + Content -->
                <div class="flex items-start gap-3.5 flex-1 min-w-0">
                    <!-- Toggle Checkbox Button -->
                    <button type="button"
                        wire:click="toggleComplete({{ $todo->id }})"
                        class="mt-0.5 size-5 rounded-lg border-2 flex items-center justify-center transition shrink-0
                            {{ $todo->is_completed
                                ? 'bg-emerald-500 border-emerald-500 text-white shadow-sm'
                                : 'border-zinc-300 dark:border-zinc-600 hover:border-emerald-500 dark:hover:border-emerald-400 bg-white dark:bg-zinc-800' }}">
                        @if($todo->is_completed)
                            <svg class="size-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </button>

                    <!-- Text & Details -->
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold transition
                                {{ $todo->is_completed
                                    ? 'line-through text-zinc-400 dark:text-zinc-500'
                                    : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $todo->title }}
                            </span>

                            <!-- Priority Badge -->
                            @if($todo->priority === 'high')
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md uppercase tracking-wider bg-rose-500/10 text-rose-500 dark:text-rose-400 border border-rose-500/20">
                                    High
                                </span>
                            @elseif($todo->priority === 'medium')
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    Medium
                                </span>
                            @else
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    Low
                                </span>
                            @endif

                            <!-- Due Date Badge -->
                            @if($todo->due_date)
                                @php
                                    $isOverdue = ! $todo->is_completed && $todo->due_date->isPast();
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-md
                                    {{ $isOverdue
                                        ? 'bg-rose-500/10 text-rose-500 dark:text-rose-400 border border-rose-500/20'
                                        : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">
                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ $todo->due_date->isoFormat('D MMM Y, HH:mm') }}
                                    @if($isOverdue)
                                        (Terlambat)
                                    @endif
                                </span>
                            @endif
                        </div>

                        <!-- Description -->
                        @if($todo->description)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-2 leading-relaxed">
                                {{ $todo->description }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Right: Action Buttons -->
                <div class="flex items-center gap-1 opacity-80 group-hover:opacity-100 transition shrink-0">
                    <!-- Edit Button -->
                    <button type="button"
                        wire:click="openEditModal({{ $todo->id }})"
                        title="Edit tugas"
                        class="p-1.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>

                    <!-- Delete Button -->
                    <button type="button"
                        wire:click="deleteTodo({{ $todo->id }})"
                        wire:confirm="Yakin ingin menghapus tugas ini?"
                        title="Hapus tugas"
                        class="p-1.5 text-zinc-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/40 transition">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="p-10 rounded-2xl bg-white dark:bg-zinc-900 border border-dashed border-zinc-200 dark:border-zinc-800 text-center space-y-3">
                <div class="size-12 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-500 flex items-center justify-center">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Belum ada tugas ditemukan</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm mx-auto">
                    @if($search)
                        Tidak ada tugas yang cocok dengan kata kunci "{{ $search }}". Coba ubah pencarian Anda.
                    @else
                        Mulai hari Anda dengan menambahkan tugas pertama di atas.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    <!-- Edit Todo Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/70 backdrop-blur-sm"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">

            <div class="w-full max-w-lg bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 shadow-2xl space-y-4"
                @click.outside="$wire.closeEditModal()">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Edit Tugas</h3>
                    <button type="button"
                        wire:click="closeEditModal"
                        class="p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Form -->
                <form wire:submit="updateTodo" class="space-y-4">
                    <!-- Title -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Judul Tugas <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                            wire:model="editTitle"
                            class="w-full px-3.5 py-2 text-sm rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" />
                        @error('editTitle')
                            <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Priority -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Prioritas
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                wire:click="$set('editPriority', 'low')"
                                class="flex-1 py-2 rounded-xl text-xs font-semibold border transition
                                    {{ $editPriority === 'low'
                                        ? 'bg-emerald-500 text-white border-emerald-500 shadow-sm'
                                        : 'border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                Low
                            </button>
                            <button type="button"
                                wire:click="$set('editPriority', 'medium')"
                                class="flex-1 py-2 rounded-xl text-xs font-semibold border transition
                                    {{ $editPriority === 'medium'
                                        ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
                                        : 'border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                Medium
                            </button>
                            <button type="button"
                                wire:click="$set('editPriority', 'high')"
                                class="flex-1 py-2 rounded-xl text-xs font-semibold border transition
                                    {{ $editPriority === 'high'
                                        ? 'bg-rose-500 text-white border-rose-500 shadow-sm'
                                        : 'border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                High
                            </button>
                        </div>
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Tenggat Waktu
                        </label>
                        <input type="datetime-local"
                            wire:model="editDueDate"
                            class="w-full px-3.5 py-2 text-sm rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" />
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Deskripsi / Catatan
                        </label>
                        <textarea wire:model="editDescription"
                            rows="3"
                            class="w-full p-3 text-sm rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition resize-y"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        <button type="button"
                            wire:click="closeEditModal"
                            class="px-4 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl transition">
                            Batal
                        </button>
                        <button type="submit"
                            wire:loading.attr="disabled"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-sm transition inline-flex items-center gap-2">
                            <span wire:loading.remove wire:target="updateTodo">Simpan Perubahan</span>
                            <span wire:loading wire:target="updateTodo">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>