@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full h-[52px] px-4 rounded-lg border-[1.5px] border-line bg-card text-ink text-base placeholder:text-ink-faint focus:border-accent disabled:opacity-60 disabled:cursor-not-allowed']) }}>
