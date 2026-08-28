<x-layouts::app :title="__('Conversations')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <livewire:conversation :initial-conversation-id="$initialConversationId ?? null" />
    </div>
</x-layouts::app>