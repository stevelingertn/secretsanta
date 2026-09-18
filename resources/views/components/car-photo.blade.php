@props(['car', 'size' => 'thumb'])
{{-- Real uploaded photo, or an honest placeholder. Never a stock photo standing in for an entered car. --}}
@php($url = $size === 'full' ? $car->photoUrl() : $car->thumbUrl())
<div {{ $attributes->merge(['class' => 'relative aspect-[4/3] overflow-hidden bg-ink-soft']) }}>
    @if ($url)
        <img src="{{ $url }}" alt="Car #{{ $car->entry_number }}: {{ $car->description }}" loading="lazy" decoding="async" class="h-full w-full object-cover">
    @else
        <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-white/70">
            <svg aria-hidden="true" viewBox="0 0 64 32" class="w-20 fill-none stroke-current" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 22v-5l6-2 8-7h20l10 7 10 2v5h-5"/>
                <path d="M14 22h31"/>
                <circle cx="10" cy="22" r="4"/>
                <circle cx="49" cy="22" r="4"/>
            </svg>
            <span class="text-sm font-semibold uppercase tracking-wide">No photo yet</span>
        </div>
    @endif
</div>
