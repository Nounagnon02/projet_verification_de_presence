<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Suivi des absences') }} — {{ $group->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-confirm-tint border border-confirm/30 text-confirm px-4 py-3 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6" x-data="{ loading: false, loaded: false, date: '', absents: [] }">
                <div class="bg-card p-4 rounded-lg border border-line">
                    <div class="text-2xl font-display font-semibold text-ink">{{ $stats['total_events'] }}</div>
                    <div class="text-sm text-ink-soft">{{ __('Événements (30 j)') }}</div>
                </div>
                <div class="bg-card p-4 rounded-lg border border-line">
                    <div class="text-2xl font-display font-semibold text-confirm">{{ $stats['avg_presence_rate'] }}%</div>
                    <div class="text-sm text-ink-soft">{{ __('Taux de présence') }}</div>
                </div>
                <div class="bg-card p-4 rounded-lg border border-line col-span-2 md:col-span-1">
                    <button type="button" :disabled="loading" class="w-full flex flex-col items-center gap-1.5 text-center disabled:opacity-50"
                        @click="
                            loading = true;
                            fetch('{{ route('alerts.absent-members', $group) }}')
                                .then(r => r.json())
                                .then(data => { date = data.date; absents = data.members; loaded = true; })
                                .finally(() => loading = false);
                        ">
                        <svg class="w-6 h-6 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.35-4.35"/></svg>
                        <div class="text-sm font-semibold text-accent" x-text="loading ? @js(__('Vérification…')) : @js(__('Vérifier maintenant'))"></div>
                    </button>
                </div>

                <div x-show="loaded" x-cloak class="col-span-2 md:col-span-3 bg-card rounded-lg border border-line p-5">
                    <h3 class="text-sm font-semibold text-ink mb-3">
                        {{ __('Membres n\'ayant pas pointé') }} <span x-show="date">{{ __('le') }} <span x-text="date"></span></span>
                    </h3>
                    <p class="text-sm text-ink-soft" x-show="absents.length === 0">{{ __('Tous les membres ont pointé pour l\'instant.') }}</p>
                    <ul class="divide-y divide-line" x-show="absents.length > 0">
                        <template x-for="membre in absents" :key="membre.id">
                            <li class="flex items-center justify-between py-2.5">
                                <span class="text-sm font-medium text-ink" x-text="membre.name"></span>
                                <a :href="'tel:' + membre.phone" class="text-sm text-accent font-semibold" x-text="membre.phone"></a>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="p-4 bg-paper2 border border-line rounded-lg">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-ink-faint mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm text-ink-soft">
                        {{ __('La relance se fait par téléphone : appuyez sur un numéro pour appeler. Aucun SMS ni email n\'est envoyé automatiquement.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
