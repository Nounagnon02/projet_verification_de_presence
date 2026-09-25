<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl md:text-[28px] text-ink">{{ __('Analyses') }}</h1>
        <p class="text-ink-soft text-base mt-1">{{ __('Tendances, assiduité et classement des membres sur une période.') }}</p>
    </x-slot>

    {{-- Sélecteur de période : un seul pour toute la page --}}
    <div class="bg-card rounded-xl border border-line mb-8">
        <div class="p-6 flex flex-col gap-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-bold text-ink-soft mr-1">{{ __('Période') }}</span>
                @foreach($periodes as $p)
                    <a href="{{ route('analyses.index', ['periode' => $p]) }}"
                       class="px-4 py-2 rounded-lg text-sm font-bold transition-colors {{ ! $personnalisee && $periode === $p ? 'bg-accent text-white' : 'border-[1.5px] border-line-strong bg-card text-ink hover:bg-paper2' }}">
                        @switch($p)
                            @case(7) {{ __('7 derniers jours') }} @break
                            @case(30) {{ __('30 derniers jours') }} @break
                            @case(90) {{ __('3 derniers mois') }} @break
                            @default {{ __('12 derniers mois') }}
                        @endswitch
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('analyses.index') }}" class="flex flex-wrap items-end gap-4 pt-1 border-t border-line">
                <div class="pt-4">
                    <x-input-label for="start_date" class="mb-1.5">{{ __('Date de début') }}</x-input-label>
                    <x-text-input id="start_date" type="date" name="start_date" :value="$personnalisee ? $dateDebut : null" />
                </div>
                <div class="pt-4">
                    <x-input-label for="end_date" class="mb-1.5">{{ __('Date de fin') }}</x-input-label>
                    <x-text-input id="end_date" type="date" name="end_date" :value="$personnalisee ? $dateFin : null" />
                </div>
                <x-primary-button type="submit">{{ __('Appliquer') }}</x-primary-button>
                @if($personnalisee)
                    <a href="{{ route('analyses.index') }}" class="text-ink-soft hover:text-ink px-3 py-2 text-sm font-semibold">
                        {{ __('Réinitialiser') }}
                    </a>
                @endif
            </form>

            <p class="text-sm text-ink-faint">
                {{ __('Du :debut au :fin', [
                    'debut' => \Carbon\Carbon::parse($dateDebut)->translatedFormat(__('date.short')),
                    'fin' => \Carbon\Carbon::parse($dateFin)->translatedFormat(__('date.short')),
                ]) }}
            </p>
        </div>
    </div>

    {{-- Chiffres clés --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="p-6 rounded-xl border border-line bg-card">
            <h3 class="font-display font-semibold text-ink text-sm">{{ __('Total membres') }}</h3>
            <p class="text-3xl font-display font-semibold text-ink mt-2">{{ $totalMembres }}</p>
        </div>
        <div class="p-6 rounded-xl border border-line bg-card">
            <h3 class="font-display font-semibold text-ink text-sm">{{ __('Présences') }}</h3>
            <p class="text-3xl font-display font-semibold text-confirm mt-2">{{ $presencesActuelles }}</p>
        </div>
        <div class="p-6 rounded-xl border border-line bg-card">
            <h3 class="font-display font-semibold text-ink text-sm">{{ __('Moyenne / jour') }}</h3>
            <p class="text-3xl font-display font-semibold text-ink mt-2">{{ $periode > 0 ? round($presencesActuelles / $periode, 1) : 0 }}</p>
        </div>
        <div class="p-6 rounded-xl border border-line bg-card">
            <h3 class="font-display font-semibold text-ink text-sm">{{ __('Moy. par événement') }}</h3>
            <p class="text-3xl font-display font-semibold text-ink mt-2">{{ $stats['avg_per_event'] }}</p>
            <p class="text-xs text-ink-faint mt-1">{{ trans_choice(':count événement|:count événements', $stats['total_events']) }}</p>
        </div>
    </div>

    {{-- Comparaison avec la période précédente --}}
    <div class="bg-card rounded-xl border border-line mb-8">
        <div class="p-6">
            <h2 class="text-lg text-ink mb-5">{{ __('Comparaison avec la période précédente') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="p-5 rounded-lg bg-accent-tint">
                    <h3 class="font-display font-semibold text-accent">{{ __('Période actuelle') }}</h3>
                    <p class="text-3xl font-display font-semibold text-accent mt-1">{{ $presencesActuelles }}</p>
                    <p class="text-sm text-accent mt-1">{{ __('Taux : :rate%', ['rate' => $tauxActuel]) }}</p>
                </div>
                <div class="p-5 rounded-lg bg-paper">
                    <h3 class="font-display font-semibold text-ink">{{ __('Période précédente') }}</h3>
                    <p class="text-3xl font-display font-semibold text-ink mt-1">{{ $presencesPrecedentes }}</p>
                    <p class="text-sm text-ink-soft mt-1">{{ __('Taux : :rate%', ['rate' => $tauxPrecedent]) }}</p>
                </div>
                <div class="p-5 rounded-lg {{ $tendance >= 0 ? 'bg-confirm-tint' : 'bg-red-50' }}">
                    <h3 class="font-display font-semibold {{ $tendance >= 0 ? 'text-confirm' : 'text-red-700' }}">{{ __('Évolution') }}</h3>
                    <p class="text-3xl font-display font-semibold {{ $tendance >= 0 ? 'text-confirm' : 'text-red-700' }} mt-1">
                        {{ $tendance >= 0 ? '+' : '' }}{{ $tendance }}%
                    </p>
                    <p class="text-sm {{ $tendance >= 0 ? 'text-confirm' : 'text-red-700' }} mt-1">
                        {{ $tendance >= 0 ? __('Amélioration') : __('Diminution') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Courbe d'évolution --}}
    <div class="bg-card rounded-xl border border-line mb-8">
        <div class="p-6">
            <h2 class="text-lg text-ink mb-5">{{ __('Évolution des présences') }}</h2>
            <canvas id="presencesChart" width="400" height="100"></canvas>
        </div>
    </div>

    {{-- Assiduité jour par jour --}}
    <div class="bg-card rounded-xl border border-line mb-8">
        <div class="p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
                <h2 class="text-lg text-ink">{{ __('Assiduité jour par jour') }}</h2>
                <div class="flex items-center text-sm text-ink-soft">
                    <span class="mr-2">{{ __('Moins') }}</span>
                    <div class="flex gap-1">
                        <div class="w-4 h-4 rounded bg-paper2"></div>
                        <div class="w-4 h-4 rounded bg-green-200"></div>
                        <div class="w-4 h-4 rounded bg-green-400"></div>
                        <div class="w-4 h-4 rounded bg-confirm"></div>
                        <div class="w-4 h-4 rounded bg-green-700"></div>
                    </div>
                    <span class="ml-2">{{ __('Plus') }}</span>
                </div>
            </div>

            <div class="flex gap-2">
                {{-- Noms de jours : Carbon les traduit, la grille indexe dayOfWeek de 0 (dimanche) à 6 --}}
                @php $weekStart = \Carbon\Carbon::now()->startOfWeek(\Carbon\CarbonInterface::SUNDAY); @endphp
                <div class="flex flex-col gap-1 text-xs text-ink-faint flex-shrink-0">
                    @for($d = 0; $d < 7; $d++)
                        <span class="h-4 leading-4">{{ $weekStart->copy()->addDays($d)->translatedFormat(__('date.weekday_short')) }}</span>
                    @endfor
                </div>

                <div class="overflow-x-auto">
                    <div class="flex gap-1 min-w-max">
                        @foreach(collect($heatmapData)->groupBy('week') as $weekDays)
                            <div class="flex flex-col gap-1">
                                @for($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++)
                                    @php $dayData = $weekDays->firstWhere('dayOfWeek', $dayOfWeek); @endphp
                                    @if($dayData)
                                        <div class="w-4 h-4 rounded cursor-pointer transition-transform hover:scale-125 @switch($dayData['intensity']) @case(0) bg-paper2 @break @case(1) bg-green-200 @break @case(2) bg-green-400 @break @case(3) bg-confirm @break @default bg-green-700 @endswitch"
                                            title="{{ \Carbon\Carbon::parse($dayData['date'])->translatedFormat(__('date.long')) }} — {{ trans_choice(':count présence|:count présences', $dayData['total']) }}"></div>
                                    @else
                                        <div class="w-4 h-4"></div>
                                    @endif
                                @endfor
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-6 pt-6 border-t border-line">
                <div>
                    <h3 class="text-sm font-semibold text-ink-soft">{{ __('Meilleur jour') }}</h3>
                    <p class="text-xl font-display font-semibold text-ink mt-1">{{ $stats['best_day'] ?? '—' }}</p>
                    @if($stats['best_day'])
                        <p class="text-sm text-confirm">{{ trans_choice(':count présence|:count présences', $stats['best_day_count']) }}</p>
                    @endif
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-ink-soft">{{ __('Heure la plus active') }}</h3>
                    <p class="text-xl font-display font-semibold text-ink mt-1">{{ $stats['best_hour'] ?? '—' }}</p>
                    @if($stats['best_hour'])
                        <p class="text-sm text-accent">{{ trans_choice(':count pointage|:count pointages', $stats['best_hour_count']) }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Classement des membres --}}
    <div class="bg-card rounded-xl border border-line overflow-hidden">
        <div class="px-6 py-5 border-b border-line">
            <h2 class="text-lg text-ink">{{ __('Classement par taux de présence') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-paper">
                    <tr>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Rang') }}</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Membre') }}</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Présences') }}</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Taux') }}</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Progression') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-card divide-y divide-line">
                    @forelse($membresStats as $index => $membre)
                        <tr class="hover:bg-paper transition-colors">
                            <td class="py-4 px-4 sm:px-6 text-sm sm:text-base font-medium text-ink">{{ $loop->iteration }}</td>
                            <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-ink">{{ $membre['name'] }}</td>
                            <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-ink-soft">{{ $membre['total_presences'] }}/{{ $periode }}</td>
                            <td class="py-4 px-4 sm:px-6">
                                <div class="flex items-center">
                                    <div class="w-16 bg-paper2 rounded-full h-2 mr-2">
                                        <div class="bg-accent h-2 rounded-full" style="width: {{ min($membre['taux_presence'], 100) }}%"></div>
                                    </div>
                                    <span class="text-sm text-ink">{{ $membre['taux_presence'] }}%</span>
                                </div>
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-sm">
                                @if($membre['taux_presence'] >= 80)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-confirm-tint text-confirm">{{ __('Excellent') }}</span>
                                @elseif($membre['taux_presence'] >= 60)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">{{ __('Bon') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">{{ __('À améliorer') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <p class="text-ink-faint text-lg font-medium">{{ __('Aucune donnée pour cette période') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('presencesChart').getContext('2d');
        const presencesData = @json($presencesParJour);

        // Même locale de dates que côté serveur. On réutilise la clé « carbon »
        // de config/locales.php : le fon n'a pas de données de format dans les
        // navigateurs, elle le renvoie donc sur le français, comme Carbon.
        const dateLocale = @json(config('locales.supported.'.app()->getLocale().'.carbon', 'fr'));

        const labels = presencesData.map(item => {
            const date = new Date(item.jour);
            return date.toLocaleDateString(dateLocale, { month: 'short', day: 'numeric' });
        });

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: @json(__('Présences')),
                    data: presencesData.map(item => item.total),
                    borderColor: 'rgb(166, 71, 42)',
                    backgroundColor: 'rgba(166, 71, 42, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: {{ max($totalMembres, 1) }} } },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</x-app-layout>
