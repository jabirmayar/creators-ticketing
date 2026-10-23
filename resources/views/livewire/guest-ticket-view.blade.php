<div class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-3xl mx-auto">

        @if(!$found)
            {{-- ── Token not found ──────────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mb-6">
                    <svg class="h-8 w-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('creators-ticketing::resources.frontend.guest_track_not_found') }}</h2>
                <p class="mt-2 text-sm text-gray-500">{{ __('creators-ticketing::resources.frontend.guest_track_not_found_desc') }}</p>
            </div>
        @else
            {{-- ── Ticket found ─────────────────────────────────────────── --}}
            <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('creators-ticketing::resources.frontend.guest_track_title') }}</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ __('creators-ticketing::resources.frontend.guest_track_subtitle', ['uid' => $ticket->ticket_uid]) }}
                    </p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold border"
                      style="background-color: {{ $ticket->status?->color ?? '#6b7280' }}15; color: {{ $ticket->status?->color ?? '#6b7280' }}; border-color: {{ $ticket->status?->color ?? '#6b7280' }}40;">
                    {{ $ticket->status?->name }}
                </span>
            </div>

            {{-- Details card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('creators-ticketing::resources.frontend.guest_name') }}</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $ticket->guest_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('creators-ticketing::resources.frontend.guest_email') }}</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $ticket->guest_email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('creators-ticketing::resources.frontend.department_label') }}</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $ticket->department->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('creators-ticketing::resources.frontend.created_at_label') }}</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $ticket->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Save-link reminder --}}
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-start gap-3">
                <svg class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-amber-800">
                    {{ __('creators-ticketing::resources.frontend.guest_save_link_reminder') }}
                    <span class="font-mono text-xs break-all ml-1">{{ url()->current() }}</span>
                </p>
            </div>

            {{-- Chat --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" style="height: 520px;">
                @livewire('creators-ticketing.public-ticket-chat',
                    ['ticketId' => $ticket->id, 'guestToken' => $token],
                    key('guest-chat-' . $ticket->id)
                )
            </div>
        @endif

    </div>
</div>
