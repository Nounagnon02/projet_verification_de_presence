@php
    // Seules les langues relues (ready) sont proposées. Une langue non prête
    // reste joignable par URL (/language/<code>) mais n'est pas offerte.
    $locales = collect(config('locales.supported', []))->filter(fn ($l) => $l['ready'] ?? false);
    $current = app()->getLocale();
@endphp

@if ($locales->count() > 1)
    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
        <button type="button"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-haspopup="true"
                class="p-2 rounded-lg text-ink-soft hover:bg-paper2 hover:text-ink transition-colors flex items-center gap-1.5"
                title="{{ __('Changer de langue') }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
            </svg>
            <span class="text-xs font-bold uppercase">{{ $current }}</span>
        </button>

        <div x-show="open"
             x-cloak
             x-transition.opacity
             @click.outside="open = false"
             class="absolute right-0 mt-2 w-44 bg-card rounded-lg shadow-lg border border-line z-50">
            <div class="py-2">
                @foreach ($locales as $code => $locale)
                    <a href="{{ route('language.switch', $code) }}"
                       @if ($code === $current) aria-current="true" @endif
                       class="flex items-center gap-3 px-4 py-2 text-sm font-semibold hover:bg-paper2 {{ $code === $current ? 'bg-accent-tint text-accent' : 'text-ink-soft' }}">
                        <span class="text-xs font-bold w-8 uppercase">{{ $code }}</span>
                        {{ $locale['native'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
