<x-app-layout>
    <div class="py-6 sm:py-8 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
            <div class="mb-6 sm:mb-8">
                <div class="text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-2">Statistiques de Présence</h1>
                    <p class="text-gray-600 text-sm sm:text-base md:text-lg">Consultez et analysez les données de présence</p>
                </div>
            </div>

            <!-- Formulaire de filtrage -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 mb-6 sm:mb-8">
                <div class="p-4 sm:p-6 md:p-8">
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 sm:mb-6">Filtres de recherche</h3>
                    <form method="GET" action="{{ route('statistiques') }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                            <!-- Filtre par date -->
                            <div>
                                <x-input-label for="date" :value="__('Date')" class="text-sm font-semibold text-gray-700 mb-2" />
                                <x-text-input
                                    id="date"
                                    class="block w-full py-3 px-4 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                    type="date"
                                    name="date"
                                    :value="$date"
                                />
                            </div>

                            <!-- Recherche par nom ou téléphone -->
                            <div>
                                <x-input-label for="search" :value="__('Rechercher')" class="text-sm font-semibold text-gray-700 mb-2" />
                                <x-text-input
                                    id="search"
                                    class="block w-full py-3 px-4 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                    type="text"
                                    name="search"
                                    :value="$search"
                                    placeholder="Nom ou téléphone"
                                />
                            </div>

                            <!-- Bouton de filtre -->
                            <div class="sm:col-span-2 lg:col-span-2 flex items-end">
                                <x-primary-button class="w-full py-3 px-6 bg-purple-600 hover:bg-purple-700 focus:bg-purple-700 rounded-lg font-semibold transition-all transform hover:scale-105">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                                    </svg>
                                    {{ __('Filtrer les résultats') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 sm:p-8 rounded-xl shadow-lg border border-blue-200 text-center transform hover:scale-105 transition-all">
                    <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-blue-800 mb-2">Présents</h3>
                    <p class="text-3xl sm:text-4xl font-bold text-blue-900">{{ $totalPresent }}</p>
                </div>
                
                <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 sm:p-8 rounded-xl shadow-lg border border-green-200 text-center transform hover:scale-105 transition-all">
                    <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-green-800 mb-2">Total Membres</h3>
                    <p class="text-3xl sm:text-4xl font-bold text-green-900">{{ $totalMembres }}</p>
                </div>
                
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 sm:p-8 rounded-xl shadow-lg border border-purple-200 text-center transform hover:scale-105 transition-all sm:col-span-2 lg:col-span-1">
                    <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-purple-800 mb-2">Taux de Présence</h3>
                    <p class="text-3xl sm:text-4xl font-bold text-purple-900">{{ $tauxPresence }}%</p>
                </div>
            </div>

            <!-- Liste des présences -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="px-4 sm:px-6 md:px-8 py-4 sm:py-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Liste des présences</h3>
                        <a href="{{ route('statistiques', array_merge(request()->all(), ['export' => 'pdf'])) }}"
                           class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-all transform hover:scale-105">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Exporter PDF
                        </a>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-gray-700 uppercase tracking-wider">Nom</th>
                                <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-gray-700 uppercase tracking-wider">Téléphone</th>
                                <th class="py-4 px-4 sm:px-6 text-center text-xs sm:text-sm font-semibold text-gray-700 uppercase tracking-wider">Heure</th>
                                <th class="py-4 px-4 sm:px-6 text-center text-xs sm:text-sm font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($presences as $presence)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-4 px-4 sm:px-6 text-sm sm:text-base font-medium text-gray-900">
                                        {{ $presence->member->name }}
                                    </td>
                                    <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-gray-600">
                                        {{ $presence->member->phone }}
                                    </td>
                                    <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ \Carbon\Carbon::parse($presence->time)->format('H:i') }}
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-center text-gray-600">
                                        {{ \Carbon\Carbon::parse($presence->date)->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <p class="text-gray-500 text-lg font-medium">Aucune présence enregistrée</p>
                                            <p class="text-gray-400 text-sm">Aucun membre n'est présent pour cette date.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
