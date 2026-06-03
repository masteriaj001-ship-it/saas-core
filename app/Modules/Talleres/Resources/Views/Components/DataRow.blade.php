@props(['label' => '', 'value' => '', 'mono' => false])

<div {{ $attributes->merge(['class' => 'flex items-baseline justify-between gap-4 py-1.5']) }}>
    <span class="text-xs text-gray-400 shrink-0">{{ $label }}</span>
    <span @class([
        'text-sm text-gray-100 text-right truncate',
        'font-mono text-xs tracking-wider' => $mono,
    ])>{{ $value }}</span>
</div>
