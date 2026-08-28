<?php

use App\Http\Controllers\TodoController;
use Illuminate\Http\Request;
use Livewire\Component;

new class extends Component {
    public string $transcript = '';
    public string $selectedTone = 'Professional';
    /** @var array<int, string> */
    public array $selectedPlatforms = [];
    public string $contentResult = '';
    public ?string $conversationId = null;
    public bool $isAnalyzing = false;
    public string $errorMessage = '';

    public function loadSampleTranscript(): void
    {
        $this->transcript = implode("\n", [
            'Viral Pagar Tinggi Mall Jakarta dan Spekulasi Isu Demo.
Di tengah dinamika sosial-politik menyambut peringatan Hari Kemerdekaan Indonesia, jagat media sosial dihebohkan oleh fenomena munculnya pagar pembatas tinggi di sejumlah pusat perbelanjaan (mall) besar di wilayah Jakarta. 
Berbagai unggahan video dan foto yang memperlihatkan konstruksi pagar besi membentang di area mall—seperti di Jakarta Selatan—viral dan memicu gelombang spekulasi di kalangan masyarakat.
Sebagian warganet mengaitkan fenomena tersebut dengan rumor ancaman aksi demonstrasi besar-besaran di ibu kota. Narasi tersebut kian meluas meny meny menyikapi sorotan isu kepuasan publik dan dinamika politik terkini.',
        ]);
    }

    public function index(TodoController $todoController): void
    {
        $this->validate([
            'selectedTone' => 'required|string',
            'selectedPlatforms' => 'required|array|min:1',
            'transcript' => 'required|string|min:20',
        ], [
            'selectedTone.required' => 'Tone wajib diisi.',
            'selectedPlatforms.required' => 'Pilih minimal satu platform.',
            'selectedPlatforms.min' => 'Pilih minimal satu platform.',
            'transcript.required' => 'Transkrip percakapan wajib diisi.',
            'transcript.min' => 'Transkrip percakapan minimal 20 karakter.',
        ]);

        $this->isAnalyzing = true;
        $this->errorMessage = '';
        $this->contentResult = '';
        $this->conversationId = null;

        try {
            $request = new Request([
                'transcript' => $this->transcript,
                'selectedTone' => $this->selectedTone,
                'selectedPlatform' => implode(', ', $this->selectedPlatforms),
                'conversation_id' => $this->conversationId,
            ]);

            if ($user = auth()->user()) {
                $request->setUserResolver(fn() => $user);
            }

            $response = $todoController->index($request);

            /** @var \Illuminate\Http\JsonResponse $response */
            $data = $response->getData(true);
            $this->contentResult = $data['content'] ?? '';
            $this->conversationId = $data['conversation_id'] ?? null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal menganalisis percakapan: ' . $e->getMessage();
        } finally {
            $this->isAnalyzing = false;
        }
    }
};

?>

<div class="w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
    <!-- Header -->
    <div
        class="bg-white dark:bg-neutral-800 rounded-2xl p-6 border border-neutral-200 dark:border-neutral-700 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    ContentStrategist Agent
                </span>
                <span class="text-xs text-neutral-400 dark:text-neutral-500">via contentController::analyze</span>
            </div>
            <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100 mt-2">
                Analisis Content
            </h2>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                Masukkan text input draft berita / ide content, untuk mendapatkan rekomendasi platform social media copy
                content.
            </p>
        </div>

        <button type="button" wire:click="loadSampleTranscript"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800 rounded-xl transition duration-150 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Muat Contoh Content
        </button>
    </div>

    <!-- Error Alert -->
    @if($errorMessage)
        <div
            class="p-4 rounded-2xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm flex items-start gap-3 shadow-sm">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="font-semibold text-red-800 dark:text-red-200">Terjadi Kesalahan</p>
                <p class="mt-1 text-red-600 dark:text-red-300">{{ $errorMessage }}</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Input Form -->
        <div
            class="bg-white dark:bg-neutral-800 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-700 shadow-sm flex flex-col justify-between space-y-4">
            <form wire:submit="analyzeContent" class="space-y-4 flex-1 flex flex-col">
                <div>
                    <label for="transcript-input"
                        class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        Transkrip Content <span class="text-red-300">*</span>
                    </label>
                    <textarea id="transcript-input" wire:model="transcript" rows="12"
                        placeholder="Tempelkan content Anda di sini..."
                        class="w-full p-4 text-sm rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm resize-y font-mono"></textarea>
                    @error('transcript')
                        <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span>
                    @enderror
                    <div class="space-y-4 pt-1">
                        {{-- Tone of Voice --}}
                        <div>
                            <label class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                                Tone of Voice <span class="text-red-300">*</span>
                            </label>
                            <select wire:model="selectedTone"
                                class="w-full px-3 py-2.5 text-sm rounded-xl border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="Professional">💼 Professional &amp; Direct</option>
                                <option value="Casual">☕ Casual &amp; Conversational</option>
                                <option value="Witty/Bold">🔥 Witty, Bold &amp; Engaging</option>
                            </select>
                        </div>

                        {{-- Platform Social Media --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                                    Platform Social Media <span class="text-red-300">*</span>
                                </label>
                                @if(count($selectedPlatforms) > 0)
                                    <span
                                        class="text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded-full">
                                        {{ count($selectedPlatforms) }} dipilih
                                    </span>
                                @endif
                            </div>

                            @error('selectedPlatforms')
                                <p class="text-xs text-red-500 mb-2 font-medium">{{ $message }}</p>
                            @enderror

                            <div class="grid grid-cols-2 gap-2">
                                {{-- Instagram --}}
                                <label for="platform-instagram"
                                    class="relative flex items-center gap-2.5 px-3 py-2.5 rounded-xl border-2 cursor-pointer transition-all duration-150
                                        {{ in_array('Instagram', $selectedPlatforms)
    ? 'border-pink-400 bg-gradient-to-br from-pink-50 to-purple-50 dark:from-pink-950/40 dark:to-purple-950/40 shadow-sm'
    : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:border-pink-300 dark:hover:border-pink-700' }}">
                                    <input id="platform-instagram" type="checkbox" value="Instagram"
                                        wire:model.live="selectedPlatforms" class="sr-only" />
                                    <span class="text-lg leading-none">📸</span>
                                    <div class="flex-1 min-w-0">
                                        <span
                                            class="block text-xs font-semibold
                                            {{ in_array('Instagram', $selectedPlatforms) ? 'text-pink-700 dark:text-pink-300' : 'text-neutral-700 dark:text-neutral-300' }}">
                                            Instagram
                                        </span>
                                        <span
                                            class="block text-[10px] text-neutral-400 dark:text-neutral-500 truncate">Post
                                            &amp; Carousel</span>
                                    </div>
                                    @if(in_array('Instagram', $selectedPlatforms))
                                        <span
                                            class="absolute top-1.5 right-1.5 size-3.5 rounded-full bg-pink-500 flex items-center justify-center">
                                            <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </label>

                                {{-- Twitter / X --}}
                                <label for="platform-twitter"
                                    class="relative flex items-center gap-2.5 px-3 py-2.5 rounded-xl border-2 cursor-pointer transition-all duration-150
                                        {{ in_array('Twitter', $selectedPlatforms)
    ? 'border-sky-400 bg-gradient-to-br from-sky-50 to-blue-50 dark:from-sky-950/40 dark:to-blue-950/40 shadow-sm'
    : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:border-sky-300 dark:hover:border-sky-700' }}">
                                    <input id="platform-twitter" type="checkbox" value="Twitter"
                                        wire:model.live="selectedPlatforms" class="sr-only" />
                                    <span class="text-lg leading-none">🐦</span>
                                    <div class="flex-1 min-w-0">
                                        <span
                                            class="block text-xs font-semibold
                                            {{ in_array('Twitter', $selectedPlatforms) ? 'text-sky-700 dark:text-sky-300' : 'text-neutral-700 dark:text-neutral-300' }}">
                                            Twitter / X
                                        </span>
                                        <span
                                            class="block text-[10px] text-neutral-400 dark:text-neutral-500 truncate">Thread
                                            5-7 tweet</span>
                                    </div>
                                    @if(in_array('Twitter', $selectedPlatforms))
                                        <span
                                            class="absolute top-1.5 right-1.5 size-3.5 rounded-full bg-sky-500 flex items-center justify-center">
                                            <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </label>

                                {{-- LinkedIn --}}
                                <label for="platform-linkedin"
                                    class="relative flex items-center gap-2.5 px-3 py-2.5 rounded-xl border-2 cursor-pointer transition-all duration-150
                                        {{ in_array('LinkedIn', $selectedPlatforms)
    ? 'border-blue-500 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950/40 dark:to-indigo-950/40 shadow-sm'
    : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:border-blue-300 dark:hover:border-blue-700' }}">
                                    <input id="platform-linkedin" type="checkbox" value="LinkedIn"
                                        wire:model.live="selectedPlatforms" class="sr-only" />
                                    <span class="text-lg leading-none">💼</span>
                                    <div class="flex-1 min-w-0">
                                        <span
                                            class="block text-xs font-semibold
                                            {{ in_array('LinkedIn', $selectedPlatforms) ? 'text-blue-700 dark:text-blue-300' : 'text-neutral-700 dark:text-neutral-300' }}">
                                            LinkedIn
                                        </span>
                                        <span
                                            class="block text-[10px] text-neutral-400 dark:text-neutral-500 truncate">Post
                                            profesional</span>
                                    </div>
                                    @if(in_array('LinkedIn', $selectedPlatforms))
                                        <span
                                            class="absolute top-1.5 right-1.5 size-3.5 rounded-full bg-blue-600 flex items-center justify-center">
                                            <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </label>

                                {{-- Script Video --}}
                                <label for="platform-script"
                                    class="relative flex items-center gap-2.5 px-3 py-2.5 rounded-xl border-2 cursor-pointer transition-all duration-150
                                        {{ in_array('Script Video', $selectedPlatforms)
    ? 'border-violet-400 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-violet-950/40 dark:to-purple-950/40 shadow-sm'
    : 'border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:border-violet-300 dark:hover:border-violet-700' }}">
                                    <input id="platform-script" type="checkbox" value="Script Video"
                                        wire:model.live="selectedPlatforms" class="sr-only" />
                                    <span class="text-lg leading-none">🎬</span>
                                    <div class="flex-1 min-w-0">
                                        <span
                                            class="block text-xs font-semibold
                                            {{ in_array('Script Video', $selectedPlatforms) ? 'text-violet-700 dark:text-violet-300' : 'text-neutral-700 dark:text-neutral-300' }}">
                                            Script Video
                                        </span>
                                        <span
                                            class="block text-[10px] text-neutral-400 dark:text-neutral-500 truncate">30-60
                                            detik [VISUAL]/[AUDIO]</span>
                                    </div>
                                    @if(in_array('Script Video', $selectedPlatforms))
                                        <span
                                            class="absolute top-1.5 right-1.5 size-3.5 rounded-full bg-violet-500 flex items-center justify-center">
                                            <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="analyzeContent"
                    class="w-full py-3 px-5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-semibold text-sm rounded-xl shadow-sm transition duration-150 flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="analyzeContent" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Generate Sekarang
                    </span>
                    <span wire:loading wire:target="analyzeContent" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Men-Generate Content...
                    </span>
                </button>
            </form>
        </div>

        <!-- Output Result Panel -->
        <table class="w-full max-w-5xl mx-auto">
            <thead>
                <tr>
                    <th>Song</th>
                    <th>Artist</th>
                    <th>Year</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>The Sliding Mr. Bones (Next Stop, Pottersville)</td>
                    <td>Malcolm Lockyer</td>
                    <td>1961</td>
                </tr>
                <tr>
                    <td>Witchy Woman</td>
                    <td>The Eagles</td>
                    <td>1972</td>
                </tr>
                <tr>
                    <td>Shining Star</td>
                    <td>Earth, Wind, and Fire</td>
                    <td>1975</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>