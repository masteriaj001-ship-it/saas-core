@props(['padding' => 'p-4'])

<div {{ $attributes->merge(['class' => "
    {$padding}
    bg-gray-900/70 backdrop-blur-sm
    border border-white/5 rounded-xl
    transition-all duration-200
    hover:bg-gray-900/80 hover:border-white/10
"]) }}>
    {{ $slot }}
</div>
