<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Statistiques Avancées') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Filtres -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" class="flex items-center space-x-4">
                        <label class="text-sm font-medium text-gray-700">Période:</label>
                        <select name="periode" class="border-gray-300 rounded-md" onchange="this.form.submit()">
                            <option value="7" {{ $periode == '7' ? 'selected' : '' }}>7 derniers jours</option>
                            <option value="30" {{ $periode == '30' ? 'selected' : '' }}>30 derniers jours</option>
                            <option value="90" {{ $periode == '90' ? 'selected' : '' }}>90 derniers jours</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Total Membres</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $totalMembres }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Présences Totales</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $presencesActuelles }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Tendance</p>
                                <p class="text-2xl font-semibold {{ $tendance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $tendance >= 0 ? '+' : '' }}{{ $tendance }}%
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Moyenne/Jour</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $periode > 0 ? round($presencesActuelles / $periode, 1) : 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphique des présences par jour -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Évolution des Présences</h3>
                    <canvas id="presencesChart" width="400" height="100"></canvas>
                </div>
            </div>

            <!-- Classement des membres -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Classement par Taux de Présence</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rang</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Membre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Présences</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taux</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progression</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($membresStats as $index => $membre)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $index + 1 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $membre['name'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $membre['total_presences'] }}/{{ $periode }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($membre['taux_presence'], 100) }}%"></div>
                                                </div>
                                                <span class="text-sm text-gray-900">{{ $membre['taux_presence'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($membre['taux_presence'] >= 80)
                                                <span class="text-green-600">Excellent</span>
                                            @elseif($membre['taux_presence'] >= 60)
                                                <span class="text-yellow-600">Bon</span>
                                            @else
                                                <span class="text-red-600">À améliorer</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('presencesChart').getContext('2d');
        const presencesData = @json($presencesParJour);
        
        const labels = presencesData.map(item => {
            const date = new Date(item.jour);
            return date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
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