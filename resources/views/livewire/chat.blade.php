<div
    x-data="{
        sidebarOpen: false,
        desktopSidebarOpen: localStorage.getItem('desktopSidebarOpen') !== 'false',
        toggleDesktopSidebar() {
            this.desktopSidebarOpen = !this.desktopSidebarOpen;
            localStorage.setItem('desktopSidebarOpen', this.desktopSidebarOpen);
        }
    }"
    class="flex h-screen bg-[#FAF9F5]"
>
    {{-- Mobile sidebar backdrop --}}
    <div
        x-show="sidebarOpen"
        x-transition:enter="transition-opacity duration-200 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="sidebarOpen = false"
        class="fixed inset-0 z-20 bg-black/40 lg:hidden"
    ></div>

    {{-- Sidebar --}}
    <aside
        :class="{
            'translate-x-0': sidebarOpen,
            '-translate-x-full': !sidebarOpen,
            'lg:translate-x-0 lg:relative lg:w-64 lg:shrink-0': desktopSidebarOpen,
            'lg:-translate-x-full lg:hidden': !desktopSidebarOpen
        }"
        class="fixed inset-y-0 left-0 z-30 w-72 bg-[#FAF9F5] flex flex-col transition-all duration-200 ease-in-out border-r border-black/[0.15]"
    >
        {{-- Sidebar header --}}
        <div class="p-3">
            <button
                wire:click="newConversation"
                @click="sidebarOpen = false"
                class="flex items-center gap-2 w-full px-3 py-2.5 rounded-md text-sm font-medium text-[#3D3D3A] hover:bg-[#F0EEE6] transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                New chat
            </button>
        </div>

        {{-- Conversation list --}}
        <div class="flex-1 overflow-y-auto px-3 pb-3">
            @php $grouped = $this->getGroupedConversations(); @endphp

            @if (empty($grouped))
                <div class="px-2 py-8 text-center">
                    <p class="text-xs text-[#73726C]">No conversations yet</p>
                </div>
            @else
                @foreach ($grouped as $label => $convos)
                    <p class="px-2 pb-1.5 pt-3 first:pt-0 text-xs text-[#73726C]">{{ $label }}</p>
                    <div class="space-y-0.5">
                        @foreach ($convos as $conversation)
                            <button
                                wire:key="conv-{{ $conversation->id }}"
                                wire:click="selectConversation('{{ $conversation->id }}')"
                                @click="sidebarOpen = false"
                                class="w-full text-left px-4 py-1.5 rounded-md text-xs truncate transition-colors leading-4 {{ $conversationId === $conversation->id ? 'bg-[#F0EEE6] text-[#141413] font-medium' : 'text-[#3D3D3A] hover:bg-[#F0EEE6]/60' }}"
                            >
                                {{ $conversation->title ?? 'Untitled' }}
                            </button>
                        @endforeach
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Mobile close button --}}
        <div class="p-3 border-t border-black/[0.15] lg:hidden">
            <button
                @click="sidebarOpen = false"
                class="flex items-center gap-2 w-full px-3 py-2 rounded-md text-sm text-[#3D3D3A] hover:bg-[#F0EEE6] transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Close
            </button>
        </div>
    </aside>

    {{-- Main chat area --}}
    <div class="flex-1 flex flex-col min-w-0">
        {{-- Desktop sidebar toggle --}}
        <div class="hidden lg:flex items-center h-12 px-4 border-b border-black/[0.08]">
            <button
                @click="toggleDesktopSidebar()"
                class="p-1.5 -ml-1.5 rounded-md text-[#3D3D3A] hover:bg-[#F0EEE6] transition-colors"
                title="Toggle sidebar"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        {{-- Mobile header --}}
        <div class="flex items-center gap-3 px-4 h-12 border-b border-black/[0.08] lg:hidden">
            <button
                @click="sidebarOpen = true"
                class="p-1.5 -ml-1.5 rounded-md text-[#3D3D3A] hover:bg-[#F0EEE6]"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="text-sm font-medium text-[#141413] truncate">
                {{ $this->getCurrentConversationTitle() }}
            </span>
        </div>

        {{-- Messages --}}
        <div id="chat-messages" class="flex-1 overflow-y-auto scroll-smooth min-h-0">
            @if (empty($messages))
                <div class="flex flex-col items-center justify-center h-full px-4 text-center">
                    <h2 class="text-2xl font-normal text-[#141413] mb-6">How can I help you today?</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-lg w-full">
                        @foreach ($suggestions as $suggestion)
                            <button
                                wire:click="selectSuggestion('{{ str_replace("'", "\\'", $suggestion) }}')"
                                class="px-3 py-2.5 rounded-lg border border-black/[0.15] bg-[#FAF9F5] text-sm text-[#3D3D3A] text-left hover:bg-[#F0EEE6] transition-colors"
                            >
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="max-w-3xl mx-auto w-full px-4 sm:px-6 py-8 space-y-8">
                    @foreach ($messages as $index => $msg)
                        <div wire:key="msg-{{ $index }}">
                            @if ($msg['role'] === 'user')
                                <div class="flex justify-end">
                                    <div class="bg-[#F0EEE6] text-[#141413] rounded-xl px-4 py-2.5 max-w-[85%] sm:max-w-xl">
                                        <p class="text-base leading-normal whitespace-pre-wrap break-words">{{ $msg['content'] }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="pl-1 group" x-data="{ copied: false }">
                                    <div class="prose prose-stone max-w-none prose-p:text-[#141413] prose-p:leading-[1.65] prose-p:my-4 prose-headings:text-[#141413] prose-headings:font-semibold prose-h1:text-2xl prose-h2:text-xl prose-h3:text-lg prose-strong:text-[#141413] prose-strong:font-semibold prose-pre:bg-[#1e1e1e] prose-pre:text-[#e0e0e0] prose-pre:rounded-xl prose-code:text-[#C6613F] prose-code:before:content-none prose-code:after:content-none prose-li:text-[#141413] prose-li:my-1 prose-ul:my-3 prose-ol:my-3 text-[#141413] text-base leading-[1.65]">
                                        {!! \Illuminate\Support\Str::markdown($msg['content']) !!}
                                    </div>
                                    <div class="flex items-center gap-1 mt-2">
                                        <button
                                            @click="
                                                const text = {{ \Illuminate\Support\Js::from($msg['content']) }};
                                                if (navigator.clipboard && window.isSecureContext) {
                                                    navigator.clipboard.writeText(text);
                                                } else {
                                                    const ta = document.createElement('textarea');
                                                    ta.value = text;
                                                    ta.style.position = 'fixed';
                                                    ta.style.left = '-9999px';
                                                    document.body.appendChild(ta);
                                                    ta.select();
                                                    document.execCommand('copy');
                                                    document.body.removeChild(ta);
                                                }
                                                copied = true;
                                                setTimeout(() => copied = false, 2000);
                                            "
                                            class="p-1.5 rounded-md text-[#73726C] hover:text-[#3D3D3A] hover:bg-[#F0EEE6] transition-colors"
                                            title="Copy to clipboard"
                                        >
                                            <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/>
                                            </svg>
                                            <svg x-show="copied" x-cloak class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Streaming response --}}
                    <div wire:loading wire:target="ask" class="pl-1">
                        <div class="prose prose-stone max-w-none prose-p:text-[#141413] prose-p:leading-[1.65] prose-p:my-4 prose-headings:text-[#141413] prose-headings:font-semibold prose-h1:text-2xl prose-h2:text-xl prose-h3:text-lg prose-strong:text-[#141413] prose-strong:font-semibold prose-pre:bg-[#1e1e1e] prose-pre:text-[#e0e0e0] prose-pre:rounded-xl prose-code:text-[#C6613F] prose-code:before:content-none prose-code:after:content-none prose-li:text-[#141413] prose-li:my-1 prose-ul:my-3 prose-ol:my-3 text-[#141413] text-base leading-[1.65]">
                            <p id="stream-target" class="whitespace-pre-wrap break-words"></p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input area --}}
        <div class="pb-4 pt-2 px-4">
            <div class="max-w-3xl mx-auto w-full">
                <form
                    wire:submit="sendMessage"
                    x-data="{
                        resize() {
                            $refs.input.style.height = 'auto';
                            $refs.input.style.height = Math.min($refs.input.scrollHeight, 200) + 'px';
                        }
                    }"
                    x-init="$nextTick(() => $refs.input.focus())"
                    class="flex items-end gap-2 rounded-[20px] bg-white px-4 py-3 shadow-[0_4px_20px_rgba(0,0,0,0.035),0_0_0_0.5px_rgba(31,30,29,0.15)] transition-all focus-within:shadow-[0_4px_20px_rgba(0,0,0,0.06),0_0_0_0.5px_rgba(31,30,29,0.25)]"
                >
                    <textarea
                        x-ref="input"
                        wire:model="message"
                        @input="resize()"
                        @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); if ($refs.input.value.trim()) $wire.sendMessage() }"
                        placeholder="How can I help you today?"
                        rows="1"
                        class="flex-1 resize-none border-0 bg-transparent p-0 text-base text-[#141413] placeholder-[#73726C] focus:ring-0 max-h-[200px] leading-normal"
                    ></textarea>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="ask,sendMessage"
                        class="shrink-0 p-2 rounded-xl bg-[#C6613F] text-white hover:bg-[#B5532F] disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg wire:loading.remove wire:target="ask,sendMessage" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                        </svg>
                        <svg wire:loading wire:target="ask,sendMessage" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>

                <p class="mt-2 text-xs text-[#73726C] text-center hidden sm:block">
                    AI can make mistakes. Please double-check responses.
                </p>
            </div>
        </div>
    </div>
</div>

@script
<script>
    const chatMessages = document.getElementById('chat-messages');

    if (chatMessages) {
        const observer = new MutationObserver(() => {
            chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
        });

        observer.observe(chatMessages, { childList: true, subtree: true });
        chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
    }
</script>
@endscript
