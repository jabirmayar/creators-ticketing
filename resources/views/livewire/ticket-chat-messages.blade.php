<div
    x-data="{
        imageModal: false,
        imageUrl: '',
        currentImageIndex: 0,
        imageUrls: [],
        init() {
            this.scrollToBottom();
            this.$wire.on('scroll-to-bottom', () => this.scrollToBottom());
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.chatContainer;
                if (container) container.scrollTop = container.scrollHeight;
            });
        },
        openImage(url, allImages = []) {
            this.imageUrls = allImages;
            this.currentImageIndex = allImages.indexOf(url);
            this.imageUrl = url;
            this.imageModal = true;
            document.body.style.overflow = 'hidden';
        },
        closeImage() {
            this.imageModal = false;
            this.imageUrl = '';
            this.imageUrls = [];
            this.currentImageIndex = 0;
            document.body.style.overflow = '';
        },
        nextImage() {
            if (this.imageUrls.length > 0) {
                this.currentImageIndex = (this.currentImageIndex + 1) % this.imageUrls.length;
                this.imageUrl = this.imageUrls[this.currentImageIndex];
            }
        },
        prevImage() {
            if (this.imageUrls.length > 0) {
                this.currentImageIndex = (this.currentImageIndex - 1 + this.imageUrls.length) % this.imageUrls.length;
                this.imageUrl = this.imageUrls[this.currentImageIndex];
            }
        }
    }"
    wire:poll.5s
    @keydown.escape.window="closeImage()"
    @keydown.arrow-right.window="if (imageModal) nextImage()"
    @keydown.arrow-left.window="if (imageModal) prevImage()"
>
    <div 
        x-ref="chatContainer"
        style="height:500px;overflow-y:auto;display:flex;flex-direction:column-reverse;padding:1.5rem;background:#000;border-radius:0.5rem;"
    >
        <div style="display:flex;flex-direction:column-reverse;gap:1rem;">
            @foreach($replies->reverse() as $reply)
                @php
                    $isRequester = $reply->user_id === $reply->ticket->user_id;
                    
                    preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/', $reply->content, $imageMatches);
                    $allImages = $imageMatches[1] ?? [];
                    $imageCount = count($allImages);
                    
                    $content = preg_replace_callback('/<img([^>]+)src="([^"]+)"([^>]*)>/', function($matches) use ($allImages, $imageCount) {
                        $escapedUrl = str_replace("'", "\\'", $matches[2]);
                        $escapedImages = str_replace('"', '&quot;', json_encode($allImages));
                        
                        $additionalStyles = '';
                        if ($imageCount > 1) {
                            $additionalStyles = 'border:2px solid #f59e0b;box-shadow:0 4px 6px -1px rgba(245, 158, 11, 0.3);';
                        }
                        
                        return '<img ' . $matches[1] . 'src="' . $matches[2] . '" ' . $matches[3] . ' style="cursor:pointer;max-width:300px;border-radius:0.5rem;margin:0.5rem 0;display:block;' . $additionalStyles . '" x-on:click="openImage(\'' . $escapedUrl . '\', ' . $escapedImages . ')">';
                    }, $reply->content);
                @endphp

                <div wire:key="reply-{{ $reply->id }}" style="display:flex;align-items:end;gap:0.625rem;{{ $isRequester ? 'flex-direction:row;' : 'flex-direction:row-reverse;' }}">
                    <div style="width:2.5rem;height:2.5rem;border-radius:9999px;background:#f43f5e;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:0.875rem;flex-shrink:0;">
                        {{ strtoupper(substr(daacreators\CreatorsTicketing\Support\UserNameResolver::resolve($reply->user) ?? 'U', 0, 1)) }}
                    </div>

                    <div style="display:flex;flex-direction:column;gap:0.25rem;max-width:70%;{{ $isRequester ? 'align-items:start;' : 'align-items:end;' }}">
                        <div style="padding:0.625rem 1rem;border-radius:1rem;{{ $isRequester ? 'background:#374151;color:white;border-top-right-radius:0.25rem;' : 'background:#047857;color:white;border-top-left-radius:0.25rem;' }}">
                            <div style="font-size:0.875rem;word-break:break-word;position:relative;">
                                {!! $content !!}
                                @if($imageCount > 1)
                                    <div style="position:absolute;top:0.5rem;right:0.5rem;background:rgba(0,0,0,0.7);color:white;padding:0.25rem 0.5rem;border-radius:1rem;font-size:0.75rem;font-weight:600;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.2);">
                                        {{ $imageCount }} <span style="font-size:0.625rem;">📷</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <span style="font-size:0.75rem;color:#6b7280;padding:0 0.5rem;">
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
            @endforeach
        </div>
    </div>

    <div 
        x-show="imageModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="closeImage()"
        style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.95);display:flex;align-items:center;justify-content:center;z-index:9999;padding:2rem;"
        x-cloak
    >
        <button 
            @click.stop="closeImage()"
            style="position:absolute;top:2rem;right:2rem;width:3.5rem;height:3.5rem;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:2rem;cursor:pointer;border:2px solid rgba(255,255,255,0.3);backdrop-filter:blur(10px);transition:all 0.2s;z-index:10000;font-weight:300;"
            onmouseover="this.style.background='rgba(255,255,255,0.25)';this.style.transform='scale(1.1)';this.style.borderColor='rgba(255,255,255,0.5)'"
            onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='scale(1)';this.style.borderColor='rgba(255,255,255,0.3)'"
        >
            ×
        </button>
        
        <template x-if="imageUrls.length > 1">
            <button 
                @click.stop="prevImage()"
                style="position:absolute;left:2rem;top:50%;transform:translateY(-50%);width:3.5rem;height:3.5rem;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;cursor:pointer;border:2px solid rgba(255,255,255,0.3);backdrop-filter:blur(10px);transition:all 0.2s;z-index:10000;font-weight:300;"
                onmouseover="this.style.background='rgba(255,255,255,0.25)';this.style.transform='scale(1.1)';this.style.borderColor='rgba(255,255,255,0.5)'"
                onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='scale(1)';this.style.borderColor='rgba(255,255,255,0.3)'"
            >
                ‹
            </button>
        </template>
        
        <template x-if="imageUrls.length > 1">
            <button 
                @click.stop="nextImage()"
                style="position:absolute;right:2rem;top:50%;transform:translateY(-50%);width:3.5rem;height:3.5rem;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;cursor:pointer;border:2px solid rgba(255,255,255,0.3);backdrop-filter:blur(10px);transition:all 0.2s;z-index:10000;font-weight:300;"
                onmouseover="this.style.background='rgba(255,255,255,0.25)';this.style.transform='scale(1.1)';this.style.borderColor='rgba(255,255,255,0.5)'"
                onmouseout="this.style.background='rgba(255,255,255,0.15)';this.style.transform='scale(1)';this.style.borderColor='rgba(255,255,255,0.3)'"
            >
                ›
            </button>
        </template>
        
        <template x-if="imageUrls.length > 1">
            <div style="position:absolute;bottom:2rem;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.7);color:white;padding:0.5rem 1rem;border-radius:2rem;font-size:0.875rem;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.2);">
                <span x-text="currentImageIndex + 1"></span> / <span x-text="imageUrls.length"></span>
            </div>
        </template>
        
        <div style="position:relative;display:flex;align-items:center;justify-content:center;width:100%;height:100%;">
            <img 
                :src="imageUrl" 
                @click.stop
                style="max-width:100%;max-height:100%;object-fit:contain;border-radius:0.5rem;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);"
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="scale-95 opacity-0"
                x-transition:enter-end="scale-100 opacity-100"
            >
        </div>
    </div>
</div>