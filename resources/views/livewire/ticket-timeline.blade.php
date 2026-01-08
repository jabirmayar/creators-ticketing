<div x-data="{ 
    isDark: document.documentElement.classList.contains('dark') || 
            localStorage.getItem('theme') === 'dark' ||
            (localStorage.getItem('theme') === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
}" 
x-init="
    $watch('$root.classList', value => {
        isDark = document.documentElement.classList.contains('dark');
    });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (localStorage.getItem('theme') === 'system') {
            isDark = e.matches;
        }
    });
">
    @forelse($activities as $index => $activity)
        @php
            $isLast = $limit ? ($index === count($activities) - 1) : ($activities->hasMorePages() ? false : ($index === $activities->count() - 1));
            $isReplyOrNote = str_contains($activity->description, 'Reply') || str_contains($activity->description, 'Internal note');
            $descriptionKey = \Illuminate\Support\Str::slug($activity->description, '_');
        @endphp
        
        <div style="position: relative; display: flex; gap: 1rem; padding-bottom: 2rem;">
            <div style="position: relative; display: flex; flex-direction: column; align-items: center;">
                <div :style="isDark ? 
                    'display: flex; height: 2.5rem; width: 2.5rem; align-items: center; justify-content: center; border-radius: 9999px; border: 2px solid; background-color: rgba(31, 41, 55, 0.5); border-color: rgba(55, 65, 81, 0.8); color: #9ca3af;' : 
                    'display: flex; height: 2.5rem; width: 2.5rem; align-items: center; justify-content: center; border-radius: 9999px; border: 2px solid; background-color: #f9fafb; border-color: #e5e7eb; color: #6b7280;'">
                    <span style="font-weight: 600;">•</span>
                </div>
                
                @if(!$isLast)
                    <div :style="isDark ? 
                        'position: absolute; left: 50%; top: 2.5rem; height: 100%; width: 1px; background-color: rgba(55, 65, 81, 0.6); transform: translateX(-50%);' : 
                        'position: absolute; left: 50%; top: 2.5rem; height: 100%; width: 1px; background-color: #e5e7eb; transform: translateX(-50%);'">
                    </div>
                @endif
            </div>
            
            <div style="flex: 1; padding-top: 0.25rem;">
                <div :style="isDark ? 
                    'border-radius: 0.5rem; border: 1px solid rgba(55, 65, 81, 0.8); background-color: rgba(31, 41, 55, 0.4); padding: 1rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3);' : 
                    'border-radius: 0.5rem; border: 1px solid #e5e7eb; background-color: white; padding: 1rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);'">
                    <div style="margin-bottom: 0.5rem; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                        <p :style="isDark ? 
                            'font-size: 0.875rem; font-weight: 600; color: #f9fafb;' : 
                            'font-size: 0.875rem; font-weight: 600; color: #111827;'">
                            {{ __('creators-ticketing::resources.activities.' . $descriptionKey) }}
                        </p>
                        <span :style="isDark ? 
                            'white-space: nowrap; font-size: 0.75rem; color: #9ca3af;' : 
                            'white-space: nowrap; font-size: 0.75rem; color: #6b7280;'">
                            {{ $activity->created_at->diffForHumans() }}
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span :style="isDark ? 
                            'font-size: 0.75rem; font-weight: 500; color: #9ca3af;' : 
                            'font-size: 0.75rem; font-weight: 500; color: #6b7280;'">
                            👤 {{ \daacreators\CreatorsTicketing\Support\UserNameResolver::resolve($activity->user) ?? __('creators-ticketing::resources.timeline.system') }}
                        </span>
                    </div>

                    @if($activity->old_value || $activity->new_value)
                        <div :style="isDark ? 
                            'margin-top: 0.75rem; border-top: 1px solid rgba(55, 65, 81, 0.6); padding-top: 0.75rem;' : 
                            'margin-top: 0.75rem; border-top: 1px solid #f3f4f6; padding-top: 0.75rem;'">
                            @if($isReplyOrNote && $activity->new_value)
                                <div :style="isDark ? 
                                    'border-radius: 0.375rem; background-color: rgba(17, 24, 39, 0.6); padding: 0.75rem; font-size: 0.875rem; color: #d1d5db; border-left: 3px solid #6366f1; font-style: italic;' : 
                                    'border-radius: 0.375rem; background-color: #f9fafb; padding: 0.75rem; font-size: 0.875rem; color: #374151; border-left: 3px solid #6366f1; font-style: italic;'">
                                    <div :style="isDark ? 'color: #9ca3af;' : 'color: #6b7280;'">
                                        {!! \Illuminate\Support\Str::limit(strip_tags($activity->new_value), 150) !!}
                                    </div>
                                </div>
                            @else
                                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                                    @if($activity->old_value)
                                        <span :style="isDark ? 
                                            'display: inline-flex; align-items: center; gap: 0.25rem; border-radius: 0.375rem; background-color: rgba(55, 65, 81, 0.6); padding: 0.25rem 0.625rem; font-size: 0.75rem; color: #d1d5db;' : 
                                            'display: inline-flex; align-items: center; gap: 0.25rem; border-radius: 0.375rem; background-color: #f3f4f6; padding: 0.25rem 0.625rem; font-size: 0.75rem; color: #374151;'">
                                            {{ __('creators-ticketing::resources.timeline.from') }} <strong>{{ $activity->old_value }}</strong>
                                        </span>
                                    @endif
                                    
                                    @if($activity->old_value && $activity->new_value)
                                        <span :style="isDark ? 'color: #6b7280;' : 'color: #9ca3af;'">→</span>
                                    @endif
                                    
                                    @if($activity->new_value)
                                        <span :style="isDark ? 
                                            'display: inline-flex; align-items: center; gap: 0.25rem; border-radius: 0.375rem; background-color: rgba(16, 185, 129, 0.2); padding: 0.25rem 0.625rem; font-size: 0.75rem; color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);' : 
                                            'display: inline-flex; align-items: center; gap: 0.25rem; border-radius: 0.375rem; background-color: #d1fae5; padding: 0.25rem 0.625rem; font-size: 0.75rem; color: #065f46;'">
                                            {{ __('creators-ticketing::resources.timeline.to') }} <strong>{{ $activity->new_value }}</strong>
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
                
                <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem; padding-left: 1rem; padding-right: 1rem;">
                    <div :style="isDark ? 
                        'height: 1px; flex: 1; background-color: rgba(55, 65, 81, 0.5);' : 
                        'height: 1px; flex: 1; background-color: #f3f4f6;'">
                    </div>
                    <span :style="isDark ? 
                        'font-size: 0.75rem; color: #6b7280;' : 
                        'font-size: 0.75rem; color: #9ca3af;'">
                        {{ $activity->created_at->format('M d, Y • H:i:s') }}
                    </span>
                    <div :style="isDark ? 
                        'height: 1px; flex: 1; background-color: rgba(55, 65, 81, 0.5);' : 
                        'height: 1px; flex: 1; background-color: #f3f4f6;'">
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; text-align: center;">
            <p :style="isDark ? 
                'font-size: 0.875rem; font-weight: 500; color: #9ca3af;' : 
                'font-size: 0.875rem; font-weight: 500; color: #6b7280;'">
                {{ __('creators-ticketing::resources.timeline.empty_title') }}
            </p>
            <p :style="isDark ? 
                'margin-top: 0.25rem; font-size: 0.75rem; color: #6b7280;' : 
                'margin-top: 0.25rem; font-size: 0.75rem; color: #9ca3af;'">
                {{ __('creators-ticketing::resources.timeline.empty_desc') }}
            </p>
        </div>
    @endforelse
    
    @if(!$limit && method_exists($activities, 'hasPages') && $activities->hasPages())
        <div :style="isDark ? 
            'margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(55, 65, 81, 0.6);' : 
            'margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;'">
            {{ $activities->links() }}
        </div>
    @endif
</div>