<div 
    x-data="{
        init() {
            this.$nextTick(() => this.scrollToBottom());
            this.$wire.on('scroll-to-bottom', () => this.scrollToBottom());
        },
        scrollToBottom() {
            setTimeout(() => {
                const container = this.$refs.chatContainer;
                if (container) {
                    container.scrollTo({ 
                        top: container.scrollHeight, 
                        behavior: 'smooth' 
                    });
                }
            }, 50);
        }
    }" 
    wire:poll.5s
    class="flex flex-col h-full"
>
    {{-- Messages --}}
    <div 
        x-ref="chatContainer"
        class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-4"
    >
        @forelse($replies as $reply)
            @php 
                $isUser = $reply->user_id === auth()->id();
            @endphp
            
            <div class="flex {{ $isUser ? 'justify-end' : 'justify-start' }} animate-in fade-in slide-in-from-bottom-2 duration-300">
                <div class="flex items-end gap-2 max-w-[85%] sm:max-w-[75%] {{ $isUser ? 'flex-row-reverse' : '' }}">
                    <div class="w-8 h-8 rounded-full {{ $isUser ? 'bg-blue-600' : 'bg-gray-600' }} flex items-center justify-center text-white text-sm font-semibold flex-shrink-0 shadow-sm">
                        {{ strtoupper(substr(daacreators\CreatorsTicketing\Support\UserNameResolver::resolve($reply->user), 0, 1)) }}
                    </div>
                    
                    <div class="flex flex-col {{ $isUser ? 'items-end' : 'items-start' }}">
                        <div class="px-4 py-3 rounded-2xl shadow-sm {{ $isUser ? 'bg-blue-600 text-white rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm border border-gray-200' }}">
                            <div class="text-sm leading-relaxed prose prose-sm max-w-none {{ $isUser ? 'prose-invert' : '' }}">
                                {!! $reply->content !!}
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2 mt-1 px-2">
                            <span class="text-xs text-gray-500 font-medium">{{ \daacreators\CreatorsTicketing\Support\UserNameResolver::resolve($reply->user) }}</span>
                            <span class="text-xs text-gray-300">•</span>
                            <span class="text-xs text-gray-400">
                                @php
                                    $now = \Carbon\Carbon::now();
                                    $diffInMinutes = $reply->created_at->diffInMinutes($now);
                                @endphp
                                @if($diffInMinutes < 60)
                                    {{ $reply->created_at->diffForHumans() }}
                                @else
                                    {{ $reply->created_at->format('M d, H:i') }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex items-center justify-center h-full">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 mb-4">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <h3 class="text-sm font-medium text-gray-900">{{ __('creators-ticketing::resources.chat.no_messages_heading') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('creators-ticketing::resources.chat.no_messages_desc') }}</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Input Area --}}
    <div class="flex-none border-t border-gray-200 bg-white p-4 z-10">
        @if($ticket->status->is_closing_status)
            <div class="bg-gray-50 rounded-lg p-4 text-center border border-gray-200">
                <p class="text-sm text-gray-500 font-medium">
                    {{ __('creators-ticketing::resources.chat.ticket_closed') }}
                </p>
            </div>
        @elseif($replies->count() <= 0)
            <div class="bg-blue-50 rounded-lg p-4 text-center border border-blue-100">
                <p class="text-sm text-blue-600 font-medium">
                     {{ __('creators-ticketing::resources.chat.wait_for_staff') }}
                </p>
            </div>
        @else
            <form wire:submit.prevent="sendMessage" class="flex items-end gap-3">
                <div class="flex-1">
                    <textarea 
                        wire:model="message" 
                        placeholder="{{ __('creators-ticketing::resources.chat.placeholder') }}" 
                        rows="3"
                        class="block w-full px-4 py-3 rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 resize-none transition"
                        @keydown.cmd.enter="$wire.sendMessage()"
                        @keydown.ctrl.enter="$wire.sendMessage()"
                    ></textarea>
                    @error('message') 
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                    class="h-[calc(3rem+2px)] px-6 bg-blue-600 text-white rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow-md transition-all duration-150 flex items-center justify-center font-semibold mb-[2px]"
                >
                    <svg wire:loading.remove wire:target="sendMessage" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    <svg wire:loading wire:target="sendMessage" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="sr-only">{{ __('creators-ticketing::resources.chat.send') }}</span>
                </button>
            </form>
            <p class="mt-2 text-xs text-gray-400 text-right pr-2">{{ __('creators-ticketing::resources.chat.shortcut_hint') }}</p>
        @endif
    </div>
</div>