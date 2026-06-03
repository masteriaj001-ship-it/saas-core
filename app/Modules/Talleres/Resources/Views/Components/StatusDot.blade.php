@props(['status' => 'inactive', 'size' => 'w-2 h-2'])

@php
    $colors = [
        'active' => 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.5)]',
        'in_progress' => 'bg-cyan-400 shadow-[0_0_6px_rgba(34,211,238,0.5)]',
        'maintenance' => 'bg-amber-400 shadow-[0_0_6px_rgba(245,158,11,0.5)]',
        'disposed' => 'bg-rose-500 shadow-[0_0_6px_rgba(244,63,94,0.5)]',
        'inactive' => 'bg-gray-500',
        'completed' => 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.5)]',
        'cancelled' => 'bg-rose-500 shadow-[0_0_6px_rgba(244,63,94,0.5)]',
        'draft' => 'bg-gray-400',
        'urgent' => 'bg-rose-500 shadow-[0_0_6px_rgba(244,63,94,0.5)]',
        'warning' => 'bg-amber-400 shadow-[0_0_6px_rgba(245,158,11,0.5)]',
    ];
    $dotClass = $colors[$status] ?? 'bg-gray-500';
@endphp

<span {{ $attributes->merge(['class' => "inline-block {$size} rounded-full {$dotClass} transition-all duration-300"]) }}></span>
