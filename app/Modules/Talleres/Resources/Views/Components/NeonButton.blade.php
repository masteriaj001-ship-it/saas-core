@props(['variant' => 'primary', 'type' => 'button'])

@php
    $base = '
        inline-flex items-center justify-center gap-2
        px-4 py-2 text-sm font-medium
        rounded-lg transition-all duration-200
        focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-950
        disabled:opacity-50 disabled:cursor-not-allowed
    ';
    $variants = [
        'primary' => '
            text-cyan-400 bg-cyan-950/40 border border-cyan-500/30
            hover:bg-cyan-950/60 hover:border-cyan-400/50
            hover:shadow-[0_0_14px_rgba(34,211,238,0.35)]
            focus:ring-cyan-400/50
        ',
        'success' => '
            text-emerald-400 bg-emerald-950/40 border border-emerald-500/30
            hover:bg-emerald-950/60 hover:border-emerald-400/50
            hover:shadow-[0_0_14px_rgba(52,211,153,0.35)]
            focus:ring-emerald-400/50
        ',
        'danger' => '
            text-rose-400 bg-rose-950/40 border border-rose-500/30
            hover:bg-rose-950/60 hover:border-rose-400/50
            hover:shadow-[0_0_14px_rgba(244,63,94,0.35)]
            focus:ring-rose-400/50
        ',
        'ghost' => '
            text-gray-400 hover:text-gray-100
            hover:bg-gray-800/60
        ',
    ];
    $class = $variants[$variant] ?? $variants['primary'];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => "{$base} {$class}"]) }}>
    {{ $slot }}
</button>
