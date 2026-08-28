<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-2">
        <!-- Hero Welcome Banner -->
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-zinc-900 via-neutral-900 to-indigo-950/80 border border-zinc-800 p-6 sm:p-8 shadow-xl">
            <div class="relative z-10 space-y-3">
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    Todo List
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                    Selamat Datang, {{ auth()->user()->name }}
                </h1>
            </div>
        </div>
    </div>
</x-layouts::app>