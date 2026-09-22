<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl md:text-[28px] text-ink">Tableau de bord</h1>
        <p class="text-ink-soft text-base mt-1">
            @if($groups->count() > 0)
                Vous encadrez {{ $groups->count() }} {{ Str::plural('groupe', $groups->count()) }}.
            @else
                Vous ne dirigez aucun groupe pour le moment.
            @endif
        </p>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-confirm-tint border border-confirm/30 text-confirm font-semibold px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 font-semibold px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Liens rapides -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <a href="{{ route('dashboardV') }}" class="flex items-center gap-4 p-5 rounded-xl border border-line bg-card hover:border-line-strong transition-colors">
            <div class="w-11 h-11 flex-shrink-0 rounded-lg bg-confirm-tint flex items-center justify-center text-confirm">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="8.4"/></svg>
            </div>
            <div>
                <div class="font-display font-semibold text-ink">Vérifier la présence</div>
                <div class="text-sm text-ink-soft">Marquer les présents manuellement</div>
            </div>
        </a>
        <a href="{{ route('membres') }}" class="flex items-center gap-4 p-5 rounded-xl border border-line bg-card hover:border-line-strong transition-colors">
            <div class="w-11 h-11 flex-shrink-0 rounded-lg bg-accent-tint flex items-center justify-center text-accent">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.3 19c0-3.3 2.6-5.6 5.7-5.6s5.7 2.3 5.7 5.6" stroke-linecap="round"/><path d="M16 8h4M18 6v4" stroke-linecap="round"/></svg>
            </div>
            <div>
                <div class="font-display font-semibold text-ink">Ajouter un membre</div>
                <div class="text-sm text-ink-soft">Enregistrer un ou plusieurs nouveaux membres</div>
            </div>
        </a>
        <a href="{{ route('statistiques.avancees') }}" class="flex items-center gap-4 p-5 rounded-xl border border-line bg-card hover:border-line-strong transition-colors">
            <div class="w-11 h-11 flex-shrink-0 rounded-lg bg-accent-tint flex items-center justify-center text-accent">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19V11M12 19V5M19 19v-7"/></svg>
            </div>
            <div>
                <div class="font-display font-semibold text-ink">Analyses avancées</div>
                <div class="text-sm text-ink-soft">Tendances, classement et comparaison de périodes</div>
            </div>
        </a>
    </div>

    <!-- Mes groupes : ouverture/scan de session -->
    <div class="mb-8">
        <h2 class="text-xl text-ink mb-4">Mes groupes</h2>

        <div class="flex flex-col border border-line rounded-xl bg-card overflow-hidden">
            @forelse($groups as $group)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-6 py-6 {{ !$loop->last ? 'border-b border-line' : '' }}">
                    <div class="flex flex-col gap-1.5">
                        <span class="font-display font-semibold text-lg text-ink">{{ $group->name }}</span>
                        @if($activeSessions->has($group->id))
                            <div class="flex items-center gap-2 text-[15px]">
                                <span class="w-2 h-2 rounded-full bg-confirm inline-block"></span>
                                <span class="font-bold text-confirm">Session en cours</span>
                                <span class="text-ink-faint">· {{ $group->members_count }} membre(s)</span>
                            </div>
                        @else
                            <span class="text-[15px] text-ink-faint">{{ $group->members_count }} membre(s)</span>
                        @endif
                    </div>
                    @if($activeSessions->has($group->id))
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('sessions.scan', $activeSessions[$group->id]) }}" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-accent font-bold text-base text-white hover:bg-accent-hover transition-colors">
                                Scanner les présences
                            </a>
                            <form method="POST" action="{{ route('sessions.close', $activeSessions[$group->id]) }}">
                                @csrf
                                <x-secondary-button type="submit">Fermer la session</x-secondary-button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('sessions.open', $group) }}">
                            @csrf
                            <x-primary-button type="submit">Ouvrir une session</x-primary-button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-ink-soft px-6 py-6">Vous ne dirigez aucun groupe pour le moment.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
