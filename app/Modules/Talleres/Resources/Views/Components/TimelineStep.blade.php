@props(['title' => '', 'description' => '', 'status' => 'pending', 'icon' => null, 'last' => false])

@php
    $dotColors = [
        'completed' => 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.5)]',
        'active' => 'bg-cyan-400 shadow-[0_0_6px_rgba(34,211,238,0.5)]',
        'pending' => 'bg-gray-600',
        'failed' => 'bg-rose-500 shadow-[0_0_6px_rgba(244,63,94,0.5)]',
    ];
    $dotClass = $dotColors[$status] ?? $dotColors['pending'];
    $titleColors = [
        'completed' => 'text-emerald-400',
        'active' => 'text-cyan-400',
        'pending' => 'text-gray-400',
        'failed' => 'text-rose-400',
    ];
    $titleClass = $titleColors[$status] ?? $titleColors['pending'];
@endphp

<div {{ $attributes->merge(['class' => 'relative flex gap-4']) }}>
    <div class="flex flex-col items-center">
        <div class="{{ $dotClass }} w-3 h-3 rounded-full ring-4 ring-gray-950 z-10 transition-all duration-300"></div>
        @unless ($last)
            <div class="w-px flex-1 bg-gray-800 -mt-0.5"></div>
        @endunless
    </div>
    <div class="pb-8 flex-1 -mt-0.5">
        <div class="flex items-center gap-2">
            @if ($icon)
                <x-dynamic-component :component="$icon" class="w-4 h-4 {{ $titleClass }}" />
            @endif
            <h4 class="text-sm font-medium {{ $titleClass }}">{{ $title }}</h4>
        </div>
        @if ($description)
            <p class="mt-1 text-xs text-gray-500 leading-relaxed">{{ $description }}</p>
        @endif
    </div>
</div>
