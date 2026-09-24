<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl md:text-[28px] text-ink">Analyses avancées</h1>
        <p class="text-ink-soft text-base mt-1">Tendances, classement des membres et comparaison de périodes.</p>
    </x-slot>

    <!-- Filtre de période -->
    <div class="bg-card rounded-xl border border-line mb-8">
        <div class="p-6">
            <form method="GET" class="flex items-center gap-4">
                <x-input-label for="periode" class="mb-0 flex-shrink-0">Période</x-input-label>
                <select id="periode" name="periode" onchange="this.form.submit()"
                    class="h-[52px] px-4 rounded-lg border-[1.5px] border-line bg-card text-ink text-base focus:border-accent">
                    <option value="7" {{ $periode == 7 ? 'selected' : '' }}>7 derniers jours</option>
                    <option value="30" {{ $periode == 30 ? 'selected' : '' }}>30 derniers jours</option>
                    <option value="90" {{ $periode == 90 ? 'selected' : '' }}>3 derniers mois</option>
                    <option value="365" {{ $periode == 365 ? 'selected' : '' }}>12 derniers mois</option>
                </select>
            </form>
        </div>
    </div>

    <!-- Cartes de statistiques -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
        <div class="p-6 rounded-xl border border-line bg-card">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-accent-tint flex items-center justify-center text-accent flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h3 class="font-display font-semibold text-ink">Total membres</h3>
            </div>
            <p class="text-3xl font-display font-semibold text-ink">{{ $totalMembres }}</p>
        </div>

        <div class="p-6 rounded-xl border border-line bg-card">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-confirm-tint flex items-center justify-center text-confirm flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="font-display font-semibold text-ink">Présences (période)</h3>
            </div>
            <p class="text-3xl font-display font-semibold text-ink">{{ $presencesActuelles }}</p>
        </div>

        <div class="p-6 rounded-xl border border-line bg-card">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-accent-tint flex items-center justify-center text-accent flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h3 class="font-display font-semibold text-ink">Moyenne / jour</h3>
            </div>
            <p class="text-3xl font-display font-semibold text-ink">{{ $periode > 0 ? round($presencesActuelles / $periode, 1) : 0 }}</p>
        </div>
    </div>

    <!-- Comparaison avec la période précédente -->
    <div class="bg-card rounded-xl border border-line mb-8">
        <div class="p-6">
            <h2 class="text-lg text-ink mb-5">Comparaison avec la période précédente</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="p-5 rounded-lg bg-accent-tint">
                    <h3 class="font-display font-semibold text-accent">Période actuelle</h3>
                    <p class="text-3xl font-display font-semibold text-accent mt-1">{{ $presencesActuelles }}</p>
                    <p class="text-sm text-accent mt-1">Taux : {{ $tauxActuel }}%</p>
                </div>

                <div class="p-5 rounded-lg bg-paper">
                    <h3 class="font-display font-semibold text-ink">Période précédente</h3>
                    <p class="text-3xl font-display font-semibold text-ink mt-1">{{ $presencesPrecedentes }}</p>
                    <p class="text-sm text-ink-soft mt-1">Taux : {{ $tauxPrecedent }}%</p>
                </div>

                <div class="p-5 rounded-lg {{ $tendance >= 0 ? 'bg-confirm-tint' : 'bg-red-50' }}">
                    <h3 class="font-display font-semibold {{ $tendance >= 0 ? 'text-confirm' : 'text-red-700' }}">Évolution</h3>
                    <p class="text-3xl font-display font-semibold {{ $tendance >= 0 ? 'text-confirm' : 'text-red-700' }} mt-1">
                        {{ $tendance >= 0 ? '+' : '' }}{{ $tendance }}%
                    </p>
                    <p class="text-sm {{ $tendance >= 0 ? 'text-confirm' : 'text-red-700' }} mt-1">
                        {{ $tendance >= 0 ? 'Amélioration' : 'Diminution' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphique des présences par jour -->
    <div class="bg-card rounded-xl border border-line mb-8">
        <div class="p-6">
            <h2 class="text-lg text-ink mb-5">Évolution des présences</h2>
            <canvas id="presencesChart" width="400" height="100"></canvas>
        </div>
    </div>

    <!-- Classement des membres -->
    <div class="bg-card rounded-xl border border-line overflow-hidden">
        <div class="px-6 py-5 border-b border-line">
            <h2 class="text-lg text-ink">Classement par taux de présence</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-paper">
                    <tr>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">Rang</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">Membre</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">Présences</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">Taux</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">Progression</th>
                    </tr>
                </thead>
                <tbody class="bg-card divide-y divide-line">
                    @forelse($membresStats as $index => $membre)
                        <tr class="hover:bg-paper transition-colors">
                            <td class="py-4 px-4 sm:px-6 text-sm sm:text-base font-medium text-ink">
                                {{ $index + 1 }}
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-ink">
                                {{ $membre['name'] }}
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-ink-soft">
                                {{ $membre['total_presences'] }}/{{ $periode }}
                            </td>
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-confirm-tint text-confirm">Excellent</span>
                                @elseif($membre['taux_presence'] >= 60)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Bon</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">À améliorer</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <p class="text-ink-faint text-lg font-medium">Aucune donnée pour cette période</p>
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

        const data = presencesData.map(item => item.total);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Présences',
                    data: data,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: {{ $totalMembres }}
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</x-app-layout>
