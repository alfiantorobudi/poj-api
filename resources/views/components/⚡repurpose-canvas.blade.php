<?php

use Livewire\Component;
use function Laravel\Ai\agent;
use Laravel\Ai\Streaming\Events\TextDelta;

new class extends Component {
    // Form Properties
    public string $sourceText = '';
    public string $selectedTone = 'Professional';

    // Output Properties
    public string $twitterOutput = '';
    public string $linkedinOutput = '';
    public string $scriptOutput = '';

    public bool $isGenerating = false;
    public string $errorMessage = '';

    public function generate(): void
    {
        $this->validate([
            'sourceText' => 'required|min:150|max:10000',
            'selectedTone' => 'required|in:Professional,Casual,Witty/Bold',
        ]);

        $this->isGenerating = true;

        // Reset output state
        $this->twitterOutput = '';
        $this->linkedinOutput = '';
        $this->scriptOutput = '';
        $this->errorMessage = '';

        try {
            // Laravel AI SDK Stream Call
            $stream = agent(instructions: $this->getSystemPrompt())
                ->stream("Teks Sumber:\n\n" . $this->sourceText);

            $currentSection = null;

            foreach ($stream as $event) {
                if (!$event instanceof TextDelta) {
                    continue;
                }

                $text = $event->delta;
                if (empty($text)) {
                    continue;
                }

                // Deteksi penanda seksi output
                if (str_contains($text, '---TWITTER---')) {
                    $currentSection = 'twitter';
                    continue;
                } elseif (str_contains($text, '---LINKEDIN---')) {
                    $currentSection = 'linkedin';
                    continue;
                } elseif (str_contains($text, '---SCRIPT---')) {
                    $currentSection = 'script';
                    continue;
                }

                // Livewire 4 wire:stream ke target Blade
                if ($currentSection === 'twitter') {
                    $this->twitterOutput .= $text;
                    $this->stream(to: 'twitterStream', content: $text);
                } elseif ($currentSection === 'linkedin') {
                    $this->linkedinOutput .= $text;
                    $this->stream(to: 'linkedinStream', content: $text);
                } elseif ($currentSection === 'script') {
                    $this->scriptOutput .= $text;
                    $this->stream(to: 'scriptStream', content: $text);
                }
            }
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '401')) {
                $this->errorMessage = 'AI Provider request unauthorized (401). Please check your API Key (OPENAI_API_KEY, GEMINI_API_KEY, etc.) or set AI_PROVIDER=local in your .env file.';
            } else {
                $this->errorMessage = 'Failed to generate content: ' . $e->getMessage();
            }
        } finally {
            $this->isGenerating = false;
        }
    }

    private function getSystemPrompt(): string
    {
        return "Anda adalah Content Strategist & Copywriter kelas dunia. Tugas Anda adalah membedah teks input menjadi 3 format media sosial.
Gunakan tone penulisan: {$this->selectedTone}.

Format Output WAJIB dipisahkan dengan tag penanda khusus berikut:

---TWITTER---
(Buat Twitter Thread 5-7 tweet dengan nomor [1/x]. Hook kuat di awal.)

---LINKEDIN---
(Buat Post LinkedIn terstruktur, spasi longgar, bullet point, dan CTA.)

---SCRIPT---
(Buat Script Video 30-60 detik dengan format [VISUAL] dan [AUDIO].)
";
    }
};
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 border-b border-slate-200 pb-5">
        <h1 class="text-3xl font-bold text-slate-900">OmniFormat Canvas</h1>
        <p class="text-slate-500 mt-1">Single-File Component di Livewire 4.1 dengan AI Real-time Streaming.</p>
    </div>

    @if($errorMessage)
        <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-3 shadow-sm">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="font-semibold text-red-800">AI Request Error</p>
                <p class="mt-1 text-red-700">{{ $errorMessage }}</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- LEFT PANEL: Input Form -->
        <div class="lg:col-span-4 bg-gray p-6 rounded-2xl border border-slate-200 shadow-sm h-fit sticky top-6">
            <form wire:submit="generate" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Teks Sumber / Artikel</label>
                    <textarea wire:model="sourceText" rows="12"
                        placeholder="Tempelkan artikel atau transkrip di sini (min. 150 kata)..."
                        class="w-full p-3 text-sm rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm border"></textarea>
                    @error('sourceText') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Tone of Voice</label>
                    <select wire:model="selectedTone"
                        class="w-full p-2.5 text-sm rounded-xl border-slate-300 focus:border-indigo-500 border bg-gray">
                        <option value="Professional">💼 Professional & Direct</option>
                        <option value="Casual">☕ Casual & Conversational</option>
                        <option value="Witty/Bold">🔥 Witty, Bold & Engaging</option>
                    </select>
                </div>

                <!-- Livewire 4 data-loading attribute integration -->
                <button type="submit"
                    class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl shadow transition duration-200 flex items-center justify-center gap-2 group">
                    <span class="group-data-[loading]:hidden">⚡ Repurpose Content</span>
                    <span class="hidden group-data-[loading]:flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Generating Output...
                    </span>
                </button>
            </form>
        </div>

        <!-- RIGHT PANEL: Output Cards -->
        <div class="lg:col-span-8 space-y-6">

            <!-- Twitter Card -->
            <div x-data="{ copied: false }"
                class="bg-gray rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="p-2 bg-sky-100 text-sky-600 rounded-lg text-xs font-bold">X / TWITTER</span>
                        <h3 class="font-semibold text-slate-800 text-sm">Thread Format</h3>
                    </div>
                    <button
                        @click="navigator.clipboard.writeText($refs.twitterText.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                        class="text-xs font-medium text-slate-600 hover:text-indigo-600 bg-gray border border-slate-200 px-3 py-1.5 rounded-lg transition">
                        <span x-text="copied ? '✓ Copied!' : 'Copy Thread'"></span>
                    </button>
                </div>
                <div class="p-6 text-sm text-slate-700 whitespace-pre-line leading-relaxed font-mono min-h-[100px]"
                    x-ref="twitterText">
                    <span wire:stream="twitterStream">{{ $twitterOutput }}</span>
                    @if(empty($twitterOutput) && !$isGenerating)
                        <p class="text-slate-400 italic font-sans text-center py-4">Hasil Twitter thread akan muncul di
                            sini...</p>
                    @endif
                </div>
            </div>

            <!-- LinkedIn Card -->
            <div x-data="{ copied: false }"
                class="bg-gray rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="p-2 bg-blue-100 text-blue-600 rounded-lg text-xs font-bold">LINKEDIN</span>
                        <h3 class="font-semibold text-slate-800 text-sm">Post & Outline</h3>
                    </div>
                    <button
                        @click="navigator.clipboard.writeText($refs.linkedinText.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                        class="text-xs font-medium text-slate-600 hover:text-indigo-600 bg-gray border border-slate-200 px-3 py-1.5 rounded-lg transition">
                        <span x-text="copied ? '✓ Copied!' : 'Copy Post'"></span>
                    </button>
                </div>
                <div class="p-6 text-sm text-slate-700 whitespace-pre-line leading-relaxed min-h-[100px]"
                    x-ref="linkedinText">
                    <span wire:stream="linkedinStream">{{ $linkedinOutput }}</span>
                    @if(empty($linkedinOutput) && !$isGenerating)
                        <p class="text-slate-400 italic text-center py-4">Hasil LinkedIn post akan muncul di sini...</p>
                    @endif
                </div>
            </div>

            <!-- Video Script Card -->
            <div x-data="{ copied: false }"
                class="bg-gray rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="p-2 bg-pink-100 text-pink-600 rounded-lg text-xs font-bold">SHORT VIDEO</span>
                        <h3 class="font-semibold text-slate-800 text-sm">Reels / TikTok Script</h3>
                    </div>
                    <button
                        @click="navigator.clipboard.writeText($refs.scriptText.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                        class="text-xs font-medium text-slate-600 hover:text-indigo-600 bg-gray border border-slate-200 px-3 py-1.5 rounded-lg transition">
                        <span x-text="copied ? '✓ Copied!' : 'Copy Script'"></span>
                    </button>
                </div>
                <div class="p-6 text-sm text-slate-700 whitespace-pre-line leading-relaxed min-h-[100px]"
                    x-ref="scriptText">
                    <span wire:stream="scriptStream">{{ $scriptOutput }}</span>
                    @if(empty($scriptOutput) && !$isGenerating)
                        <p class="text-slate-400 italic text-center py-4">Hasil Video Script akan muncul di sini...</p>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>