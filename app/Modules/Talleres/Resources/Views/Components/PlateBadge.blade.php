@props(['plate' => ''])

<span {{ $attributes->merge(['class' => '
    inline-flex items-center gap-1.5 px-2.5 py-0.5
    font-mono text-xs tracking-widest uppercase
    text-emerald-400 bg-emerald-950/30
    border border-emerald-500/20 rounded-md
    shadow-[0_0_6px_rgba(52,211,153,0.25)]
']) }}>
    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_4px_rgba(52,211,153,0.5)]"></span>
    {{ $plate }}
</span>
