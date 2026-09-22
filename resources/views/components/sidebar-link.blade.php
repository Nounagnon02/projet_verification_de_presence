@props(['href', 'active' => false])

<a href="{{ $href }}"
    {{ $attributes->merge(['class' => 'flex items-center gap-3.5 px-4 py-3 rounded-lg text-base font-bold leading-none transition-colors '
        . ($active
            ? 'bg-accent-tint text-accent'
            : 'text-ink-soft hover:bg-paper hover:text-ink')]) }}>
    {{ $slot }}
</a>
