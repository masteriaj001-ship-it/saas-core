@props(['label' => '', 'name' => '', 'type' => 'text', 'value' => '', 'placeholder' => '', 'required' => false])

<div {{ $attributes->whereDoesntStartWith('wire:model') }}>
    @if ($label)
        <label for="{{ $name }}" class="block text-xs font-medium text-gray-400 mb-1.5 tracking-wide uppercase">
            {{ $label }}
            @if ($required)
                <span class="text-rose-400 ml-0.5">*</span>
            @endif
        </label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        @if ($required) required @endif
        {{ $attributes->whereStartsWith('wire:model') }}
        class="
            w-full px-3 py-2 text-sm text-gray-100
            bg-gray-800/60 border border-white/10 rounded-lg
            placeholder:text-gray-500
            transition-all duration-200
            focus:outline-none focus:border-cyan-400/50
            focus:shadow-[0_0_8px_rgba(34,211,238,0.2)]
            focus:bg-gray-800/80
        "
    >
</div>
