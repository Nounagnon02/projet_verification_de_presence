<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-card border-[1.5px] border-line-strong font-bold text-base text-ink hover:bg-paper2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors']) }}>
    {{ $slot }}
</button>
