<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-2">
        <!-- Hero Welcome Banner -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-zinc-900 via-neutral-900 to-indigo-950/80 border border-zinc-800 p-6 sm:p-8 shadow-xl">
            <div class="relative z-10 space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    ✨ AI Creative Suite
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                    Selamat Datang, {{ auth()->user()->name }}
                </h1>
                <p class="text-sm text-zinc-300 max-w-xl">
                    Pilih alat AI kreasi konten Anda hari ini: buat visual storyboard & shot list untuk produksi video, atau ubah artikel menjadi thread media sosial.
                </p>
            </div>
        </div>

        <!-- Featured Tools Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- SceneCraft Card (Featured) -->
            <a href="{{ route('scenecraft') }}" wire:navigate
               class="group relative overflow-hidden rounded-3xl bg-gradient-to-b from-indigo-950/40 via-zinc-900 to-zinc-900 border border-indigo-500/30 hover:border-indigo-500 p-6 transition duration-200 shadow-lg flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="size-12 rounded-2xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center border border-indigo-500/30 group-hover:scale-110 transition duration-200">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>
                    </div>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        BARU • Visual Storyboard
                    </span>
                    <h3 class="text-xl font-bold text-white group-hover:text-indigo-300 transition">
                        SceneCraft Studio
                    </h3>
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        Ubah ide cerita menjadi scene-by-scene script, shot list kamera (lensa, angle, lighting), narasi VO, dan prompt Midjourney siap pakai.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-indigo-400 group-hover:translate-x-1 transition">
                    <span>Mulai Sutradarai Video</span>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </div>
            </a>

            <!-- Content Repurposer Card -->
            <a href="{{ route('content') }}" wire:navigate
               class="group relative overflow-hidden rounded-3xl bg-zinc-900 border border-zinc-800 hover:border-emerald-500/50 p-6 transition duration-200 shadow-lg flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="size-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center border border-emerald-500/30 group-hover:scale-110 transition duration-200">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>
                        </svg>
                    </div>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        Social Content
                    </span>
                    <h3 class="text-xl font-bold text-white group-hover:text-emerald-300 transition">
                        Content Strategist
                    </h3>
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        Bedah teks berita / artikel panjang menjadi format Twitter Thread, Instagram Carousel, LinkedIn post, dan video script.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-emerald-400 group-hover:translate-x-1 transition">
                    <span>Analisis Konten</span>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </div>
            </a>

            <!-- Conversations Card -->
            <a href="{{ route('conversation') }}" wire:navigate
               class="group relative overflow-hidden rounded-3xl bg-zinc-900 border border-zinc-800 hover:border-violet-500/50 p-6 transition duration-200 shadow-lg flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="size-12 rounded-2xl bg-violet-500/20 text-violet-400 flex items-center justify-center border border-violet-500/30 group-hover:scale-110 transition duration-200">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-violet-500/20 text-violet-300 border border-violet-500/30">
                        Chat Memory
                    </span>
                    <h3 class="text-xl font-bold text-white group-hover:text-violet-300 transition">
                        AI Conversations
                    </h3>
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        Lihat riwayat percakapan interaktif, lanjutkan diskusi pembuatan konten, dan simpan log kreasi Anda secara persisten.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-violet-400 group-hover:translate-x-1 transition">
                    <span>Buka Riwayat Percakapan</span>
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </div>
            </a>
        </div>
    </div>
</x-layouts::app>