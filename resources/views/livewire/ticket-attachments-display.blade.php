@php
    $wrapperId = 'attachments-' . uniqid();
    $hasFiles = false;
    $processedFiles = [];
    
    foreach ($files as $index => $file) {
        if (!is_string($file)) continue;
        
        $hasFiles = true;
        $filename = basename($file);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg']);
        
        if (\Illuminate\Support\Facades\Route::has('creators-ticketing.attachment')) {
            $url = route('creators-ticketing.attachment', ['ticketId' => $ticketId, 'filename' => $filename]);
        } else {
            $url = url('/private/ticket-attachments/' . $ticketId . '/' . $filename);
        }
        
        $processedFiles[] = [
            'filename' => $filename,
            'url' => $url,
            'isImage' => $isImage,
            'ext' => $ext,
        ];
    }
@endphp

<div
    id="{{ $wrapperId }}"
    x-data="{
        open: false,
        activeIndex: 0,
        images: [],
        wrapperId: '{{ $wrapperId }}',
        init() {
            setTimeout(() => {
                this.images = Array.from(document.querySelectorAll('#' + this.wrapperId + ' [data-lightbox-src]')).map(el => ({
                    src: el.getAttribute('data-lightbox-src'),
                    alt: el.getAttribute('data-lightbox-alt') || 'Attachment'
                }));
            }, 100);
        },
        openGallery(src) {
            this.activeIndex = this.images.findIndex(img => img.src === src);
            if (this.activeIndex !== -1) {
                this.open = true;
                document.body.style.overflow = 'hidden';
            }
        },
        closeGallery() {
            this.open = false;
            document.body.style.overflow = '';
        },
        next() {
            if (!this.images.length) return;
            this.activeIndex = (this.activeIndex + 1) % this.images.length;
        },
        prev() {
            if (!this.images.length) return;
            this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length;
        },
        get activeImage() {
            return this.images[this.activeIndex] || { src: '', alt: '' };
        }
    }"
    @keydown.escape.window="if(open) closeGallery()"
    @keydown.arrow-right.window="if(open) next()"
    @keydown.arrow-left.window="if(open) prev()"
    class="mt-1 w-full not-prose"
    wire:ignore
>
    <style>
        [x-cloak] { display: none !important; }
        .attachment-thumb-img { max-width: 100%; height: 100%; object-fit: cover; display: block; }
        .attachment-thumb-container { width: 5.5rem; height: 5.5rem; min-width: 5.5rem; min-height: 5.5rem; cursor: pointer !important; }
    </style>

    @if($label)
        <h3>{{ $label }}</h3><br>
    @endif

    <div class="flex flex-wrap gap-3 items-start">
        @foreach($processedFiles as $file)
            @php
                $safeUrl = e($file['url']);
                $safeName = e($file['filename']);
            @endphp

            @if($file['isImage'])
                <div class="attachment-thumb-container rounded-lg overflow-hidden border border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer transition-shadow hover:shadow-lg" 
                     @click="openGallery('{{ $safeUrl }}')">
                    <div data-lightbox-src="{{ $safeUrl }}" data-lightbox-alt="{{ $safeName }}" class="hidden"></div>
                    <img src="{{ $safeUrl }}" 
                         alt="{{ $safeName }}" 
                         class="attachment-thumb-img" 
                         loading="lazy"
                         onerror="this.parentElement.innerHTML='<div class=\'flex flex-col items-center justify-center p-2\'><svg class=\'w-8 h-8 text-gray-400\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z\'></path></svg><p class=\'text-xs text-red-500 mt-1 text-center\'>Failed to load</p></div>';">
                </div>
            @else
                <a href="{{ $safeUrl }}" 
                   target="_blank" 
                   download
                   class="rounded-lg border border-gray-200 bg-gray-50 flex flex-col items-center justify-center gap-1 p-3 text-xs text-gray-700 hover:bg-gray-100 transition-colors w-full min-w-0"
                   title="{{ $safeName }}">
                    <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>

                    <span class="block w-full text-[10px] text-gray-600 text-center leading-tight overflow-hidden whitespace-nowrap text-ellipsis">
                        {{ $safeName }}
                    </span>
                </a>
            @endif
        @endforeach
    </div>

    @if(!$hasFiles)
        <div class="text-sm italic text-gray-400 mt-2">{{ __('creators-ticketing::resources.frontend.no_file_attached') }}</div>
    @endif

    <template x-teleport="body">
        <div 
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeGallery()"
            style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.95);display:flex;align-items:center;justify-content:center;z-index:9999;padding:2rem;"
            x-cloak
        >
            <button 
                @click.stop="closeGallery()"
                style="position:absolute;top:2rem;right:2rem;width:3.5rem;height:3.5rem;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:2rem;cursor:pointer;border:2px solid rgba(255,255,255,0.3);backdrop-filter:blur(10px);transition:all 0.2s;z-index:10000;font-weight:300;"
                onmouseover="this.style.background='rgba(255,255,255,0.25)';this.style.transform='scale(1.1)';this.style.borderColor='rgba(255,255,255,0.5)'"
                onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='scale(1)';this.style.borderColor='rgba(255,255,255,0.3)'"
            >
                ×
            </button>
            
            <template x-if="images.length > 1">
                <button 
                    @click.stop="prev()"
                    style="position:absolute;left:2rem;top:50%;transform:translateY(-50%);width:3.5rem;height:3.5rem;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;cursor:pointer;border:2px solid rgba(255,255,255,0.3);backdrop-filter:blur(10px);transition:all 0.2s;z-index:10000;font-weight:300;"
                    onmouseover="this.style.background='rgba(255,255,255,0.25)';this.style.transform='translateY(-50%) scale(1.1)';this.style.borderColor='rgba(255,255,255,0.5)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='translateY(-50%) scale(1)';this.style.borderColor='rgba(255,255,255,0.3)'"
                >
                    ‹
                </button>
            </template>
            
            <template x-if="images.length > 1">
                <button 
                    @click.stop="next()"
                    style="position:absolute;right:2rem;top:50%;transform:translateY(-50%);width:3.5rem;height:3.5rem;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;cursor:pointer;border:2px solid rgba(255,255,255,0.3);backdrop-filter:blur(10px);transition:all 0.2s;z-index:10000;font-weight:300;"
                    onmouseover="this.style.background='rgba(255,255,255,0.25)';this.style.transform='translateY(-50%) scale(1.1)';this.style.borderColor='rgba(255,255,255,0.5)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='translateY(-50%) scale(1)';this.style.borderColor='rgba(255,255,255,0.3)'"
                >
                    ›
                </button>
            </template>
            
            <template x-if="images.length > 1">
                <div style="position:absolute;bottom:2rem;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.7);color:white;padding:0.5rem 1rem;border-radius:2rem;font-size:0.875rem;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.2);z-index:10000;">
                    <span x-text="activeIndex + 1"></span> / <span x-text="images.length"></span>
                </div>
            </template>
            
            <div style="position:relative;display:flex;align-items:center;justify-content:center;width:100%;height:100%;">
                <img 
                    :src="activeImage.src" 
                    :alt="activeImage.alt"
                    @click.stop
                    style="max-width:100%;max-height:100%;object-fit:contain;border-radius:0.5rem;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);"
                    x-transition:enter="transition ease-out duration-300 transform"
                    x-transition:enter-start="scale-95 opacity-0"
                    x-transition:enter-end="scale-100 opacity-100"
                >
            </div>
        </div>
    </template>
</div>