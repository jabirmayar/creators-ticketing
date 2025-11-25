<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    @if (session()->has('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-green-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-red-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif
 
    @if($selectedTicket)
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 px-6 py-4">
                <button wire:click="backToList" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700 mb-3 transition">
                    <svg class="w-4 h-4 mr-1 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ __('creators-ticketing::resources.frontend.back_to_list') }}
                </button>
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ $selectedTicket->title }}</h2>
                        <div class="mt-2 flex items-center gap-3 text-sm text-gray-600">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                #{{ $selectedTicket->ticket_uid }}
                            </span>
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                {{ $selectedTicket->department->name }}
                            </span>
                        </div>
                    </div>
                    <span class="px-3 py-1.5 text-xs font-semibold rounded-lg shadow-sm" style="background-color: {{ $selectedTicket->status->color }}20; color: {{ $selectedTicket->status->color }}; border: 1px solid {{ $selectedTicket->status->color }}40;">
                        {{ $selectedTicket->status->name }}
                    </span>
                </div>
            </div>

            @livewire('creators-ticketing::public-ticket-chat', ['ticketId' => $selectedTicket->id], key('chat-'.$selectedTicket->id))
        </div>
    @else
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button 
                        wire:click="$set('showForm', true)"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition {{ $showForm ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                    >
                        <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ __('creators-ticketing::resources.frontend.new_ticket') }}
                    </button>
                    @if(auth()->check())
                        <button 
                            wire:click="$set('showForm', false)"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition {{ !$showForm ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                        >
                            <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            {{ __('creators-ticketing::resources.frontend.my_tickets') }}
                            @if(count($userTickets) > 0)
                                <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-bold rounded-full">{{ count($userTickets) }}</span>
                            @endif
                        </button>
                    @endif
                </nav>
            </div>
        </div>

        @if($showForm)
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 px-6 py-5">
                    <h2 class="text-xl font-bold text-gray-900">{{ __('creators-ticketing::resources.frontend.submit_ticket_title') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('creators-ticketing::resources.frontend.submit_ticket_desc') }}</p>
                </div>

                <form wire:submit.prevent="submit" class="p-6 space-y-6">
                    <div>
                        <label for="department_id" class="block text-sm font-semibold text-gray-900 mb-2">
                            {{ __('creators-ticketing::resources.frontend.select_department') }} <span class="text-red-500">*</span>
                        </label>
                        <select 
                            wire:model.live="department_id" 
                            id="department_id" 
                            class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition"
                        >
                            <option value="">{{ __('creators-ticketing::resources.frontend.choose_department') }}</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id') 
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div wire:loading wire:target="department_id" class="flex items-center justify-center py-12">
                        <div class="flex items-center space-x-3 text-blue-600">
                            <svg class="animate-spin h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-medium">{{ __('creators-ticketing::resources.frontend.loading_fields') }}</span>
                        </div>
                    </div>

                    <div wire:loading.remove wire:target="department_id">
                        @if(!empty($form_fields))
                            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 space-y-5">
                                <h3 class="text-base font-bold text-gray-900 flex items-center pb-3 border-b border-gray-200">
                                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    {{ __('creators-ticketing::resources.frontend.ticket_details') }}
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    @foreach($form_fields as $field)
                                        <div class="{{ in_array($field['type'], ['textarea', 'rich_editor', 'file']) ? 'md:col-span-2' : '' }}">
                                            <label for="field_{{ $field['name'] }}" class="block text-sm font-semibold text-gray-900 mb-2">
                                                {{ $field['label'] }}
                                                @if($field['is_required']) <span class="text-red-500">*</span> @endif
                                            </label>

                                            @switch($field['type'])
                                                @case('text')
                                                @case('email')
                                                @case('tel')
                                                @case('url')
                                                @case('number')
                                                    <input 
                                                        type="{{ $field['type'] }}" 
                                                        wire:model.blur="custom_fields.{{ $field['name'] }}"
                                                        id="field_{{ $field['name'] }}"
                                                        class="block w-full px-4 py-3 rounded-lg border-gray-300 bg-white shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition @error("custom_fields.{$field['name']}") border-red-300 @enderror"
                                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                                    >
                                                    @if($field['help_text'])
                                                        <p class="text-xs text-gray-600 mt-2">{{ $field['help_text'] }}</p>
                                                    @endif
                                                    @break

                                                @case('textarea')
                                                    <textarea 
                                                        wire:model.blur="custom_fields.{{ $field['name'] }}"
                                                        id="field_{{ $field['name'] }}"
                                                        rows="4"
                                                        class="block w-full px-4 py-3 rounded-lg border-gray-300 bg-white shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition @error("custom_fields.{$field['name']}") border-red-300 @enderror"
                                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                                    ></textarea>
                                                    @if($field['help_text'])
                                                        <p class="text-xs text-gray-600 mt-2">{{ $field['help_text'] }}</p>
                                                    @endif
                                                    @break

                                                @case('select')
                                                    <select 
                                                        wire:model="custom_fields.{{ $field['name'] }}"
                                                        id="field_{{ $field['name'] }}"
                                                        class="block w-full px-4 py-3 rounded-lg border-gray-300 bg-white shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition @error("custom_fields.{$field['name']}") border-red-300 @enderror"
                                                    >
                                                        <option value="">{{ __('creators-ticketing::resources.frontend.select_option') }}</option>
                                                        @foreach($field['options'] ?? [] as $key => $value)
                                                            <option value="{{ $key }}">{{ $value }}</option>
                                                        @endforeach
                                                    </select>
                                                    @if($field['help_text'])
                                                        <p class="text-xs text-gray-600 mt-2">{{ $field['help_text'] }}</p>
                                                    @endif
                                                    @break

                                                @case('radio')
                                                    @if($field['help_text'])
                                                        <p class="text-xs text-gray-600 mb-2">{{ $field['help_text'] }}</p>
                                                    @endif
                                                    <div class="space-y-3">
                                                        @foreach($field['options'] ?? [] as $key => $value)
                                                            <label class="flex items-center p-3 bg-white rounded-lg hover:bg-blue-50 transition cursor-pointer border border-gray-200 hover:border-blue-300">
                                                                <input 
                                                                    type="radio" 
                                                                    wire:model="custom_fields.{{ $field['name'] }}"
                                                                    value="{{ $key }}"
                                                                    class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                                                                >
                                                                <span class="ml-3 text-sm font-medium text-gray-900">{{ $value }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    @break

                                                @case('checkbox')
                                                    @if($field['help_text'])
                                                        <p class="text-xs text-gray-600 mb-2">{{ $field['help_text'] }}</p>
                                                    @endif
                                                    <div class="flex items-start p-3 bg-white rounded-lg border border-gray-200">
                                                        <input 
                                                            type="checkbox" 
                                                            wire:model="custom_fields.{{ $field['name'] }}"
                                                            id="field_{{ $field['name'] }}"
                                                            class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                                                        >
                                                        <label for="field_{{ $field['name'] }}" class="ml-3 text-sm text-gray-700 cursor-pointer">
                                                            {{ $field['label'] }}
                                                        </label>
                                                    </div>
                                                    @break

                                                @case('toggle')
                                                    @if($field['help_text'])
                                                        <p class="text-xs text-gray-600 mb-2">{{ $field['help_text'] }}</p>
                                                    @endif
                                                    <label class="relative inline-flex items-center cursor-pointer p-3 bg-white rounded-lg border border-gray-200">
                                                        <input 
                                                            type="checkbox" 
                                                            wire:model="custom_fields.{{ $field['name'] }}"
                                                            class="sr-only peer"
                                                        >
                                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                                        <span class="ml-3 text-sm font-medium text-gray-900">{{ $field['label'] }}</span>
                                                    </label>
                                                    @break

                                                @case('date')
                                                @case('datetime')
                                                    <input 
                                                        type="{{ $field['type'] === 'datetime' ? 'datetime-local' : 'date' }}" 
                                                        wire:model="custom_fields.{{ $field['name'] }}"
                                                        id="field_{{ $field['name'] }}"
                                                        class="block w-full px-4 py-3 rounded-lg border-gray-300 bg-white shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition @error("custom_fields.{$field['name']}") border-red-300 @enderror"
                                                    >
                                                    @if($field['help_text'])
                                                        <p class="text-xs text-gray-600 mt-2">{{ $field['help_text'] }}</p>
                                                    @endif
                                                    @break

                                                @case('file')
                                                    @if($field['help_text'])
                                                        <p class="text-xs text-gray-600 mb-2">{{ $field['help_text'] }}</p>
                                                    @endif
                                                    <div class="flex items-center justify-center w-full">
                                                        <label for="field_{{ $field['name'] }}" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-white hover:bg-gray-50 transition">
                                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                                <svg class="w-10 h-10 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                                </svg>
                                                                <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">{{ __('creators-ticketing::resources.frontend.upload_click') }}</span> {{ __('creators-ticketing::resources.frontend.upload_drag') }}</p>
                                                                <p class="text-xs text-gray-500">{{ __('creators-ticketing::resources.frontend.upload_multiple') }}</p>
                                                            </div>
                                                            <input 
                                                                type="file" 
                                                                wire:model="custom_fields.{{ $field['name'] }}"
                                                                id="field_{{ $field['name'] }}"
                                                                multiple
                                                                class="hidden"
                                                            >
                                                        </label>
                                                    </div>
                                                    <div wire:loading wire:target="custom_fields.{{ $field['name'] }}" class="mt-2 text-sm text-blue-600 flex items-center">
                                                        <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        {{ __('creators-ticketing::resources.frontend.uploading') }}
                                                    </div>
                                                    @break
                                            @endswitch

                                            @error("custom_fields.{$field['name']}") 
                                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                                <p class="text-sm text-gray-500">
                                    <span class="text-red-500">*</span> {{ __('creators-ticketing::resources.frontend.required_fields') }}
                                </p>
                                <button 
                                    type="submit" 
                                    wire:loading.attr="disabled"
                                    wire:target="submit"
                                    class="inline-flex items-center px-6 py-3 bg-blue-600 border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow-md transition-all duration-150"
                                >
                                    <svg wire:loading wire:target="submit" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="submit">{{ __('creators-ticketing::resources.frontend.submit_btn') }}</span>
                                    <span wire:loading wire:target="submit">{{ __('creators-ticketing::resources.frontend.submitting_btn') }}</span>
                                </button>
                            </div>
                        @elseif($department_id)
                            <div class="text-center py-16 bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="mt-4 text-sm font-semibold text-gray-900">{{ __('creators-ticketing::resources.frontend.no_form_title') }}</h3>
                                <p class="mt-2 text-sm text-gray-500">{{ __('creators-ticketing::resources.frontend.no_form_desc') }}</p>
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        @else
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                @if(count($userTickets) > 0)
                    <div class="divide-y divide-gray-200">
                        @foreach($userTickets as $ticket)
                            <div class="p-5 hover:bg-gray-50 cursor-pointer transition group" wire:click="viewTicket({{ $ticket->id }})">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h3 class="text-base font-semibold text-gray-900 truncate group-hover:text-blue-600 transition">
                                                {{ $ticket->title }}
                                            </h3>
                                            <span class="flex-shrink-0 px-2.5 py-1 text-xs font-semibold rounded-lg" style="background-color: {{ $ticket->status->color }}20; color: {{ $ticket->status->color }}; border: 1px solid {{ $ticket->status->color }}40;">
                                                {{ $ticket->status->name }}</span>
                                        </div>
                                        <div class="flex items-center gap-4 text-sm text-gray-600">
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                </svg>
                                                #{{ $ticket->ticket_uid }}
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                </svg>
                                                {{ $ticket->department->name }}
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                {{ $ticket->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                    @if($ticket->publicReplies->count() > 0)
                                        <span class="ml-4 flex-shrink-0 px-3 py-1.5 bg-blue-50 text-blue-700 text-sm font-semibold rounded-lg border border-blue-200">
                                            {{ $ticket->publicReplies->count() }} {{ $ticket->publicReplies->count() === 1 ? __('creators-ticketing::resources.frontend.reply') : __('creators-ticketing::resources.frontend.replies') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-16">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">{{ __('creators-ticketing::resources.frontend.no_tickets_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ __('creators-ticketing::resources.frontend.no_tickets_desc') }}</p>
                        <button wire:click="$set('showForm', true)" class="mt-6 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ __('creators-ticketing::resources.frontend.create_new_btn') }}
                        </button>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>