@props(['value' => '', 'label' => '', 'icon' => null, 'trend' => null, 'color' => 'cyan'])

@php
    $colors = [
        'cyan' => 'text-cyan-400 border-cyan-500/20',
        'emerald' => 'text-emerald-400 border-emerald-500/20',
        'amber' => 'text-amber-400 border-amber-500/20',
        'rose' => 'text-rose-400 border-rose-500/20',
    ];
    $borderColor = $colors[$color] ?? $colors['cyan'];
@endphp

<div {{ $attributes->merge(['class' => "
    p-4 bg-gray-900/70 backdrop-blur-sm
    border border-white/5 rounded-xl
    transition-all duration-200 hover:bg-gray-900/80
"]) }}>
    <div class="flex items-start justify-between">
        <div>
            <div class="text-2xl font-semibold tracking-tight text-gray-100">{{ $value }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ $label }}</div>
        </div>
        @if ($icon)
            <div class="p-2 rounded-lg bg-gray-800/60 {{ $borderColor }}">
                <x-dynamic-component :component="$icon" class="w-5 h-5" />
            </div>
        @endif
    </div>
    @if ($trend)
        <div class="mt-3 flex items-center gap-1 text-xs {{ $borderColor }}">
            {{ $trend }}
        </div>
    @endif
</div>
