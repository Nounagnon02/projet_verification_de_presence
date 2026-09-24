@if (app()->getLocale() !== config('app.fallback_locale', 'fr'))
    {{-- Les textes légaux n'ont pas été relus par un juriste dans les autres
         langues : la version française reste la seule qui engage. --}}
    <p class="mb-6 px-4 py-3 rounded-lg bg-paper2 border border-line text-sm text-ink-soft">
        {{ __('Traduction indicative. Seule la version française fait foi.') }}
    </p>
@endif
