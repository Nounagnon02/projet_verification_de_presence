<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            🗓️ Heatmap de Présence
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Filtres de période -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
                <form method="GET" action="{{ route('heatmap.index') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date de début</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" 
                               class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date de fin</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" 
                               class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-blue-500">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        Appliquer
                    </button>
                    <a href="{{ route('heatmap.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white px-4 py-2">
                        Réinitialiser
                    </a>
                </form>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ $stats['total_presences'] }}</div>
                    <div class="text-sm text-blue-700 dark:text-blue-400">Présences totales</div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                    <div class="text-2xl font-bold text-green-900 dark:text-green-300">{{ $stats['total_events'] }}</div>
                    <div class="text-sm text-green-700 dark:text-green-400">Événements</div>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                    <div class="text-2xl font-bold text-purple-900 dark:text-purple-300">{{ $stats['avg_per_event'] }}</div>
                    <div class="text-sm text-purple-700 dark:text-purple-400">Moy. par événement</div>
                </div>
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 p-4 rounded-lg border border-orange-200 dark:border-orange-800">
                    <div class="text-2xl font-bold text-orange-900 dark:text-orange-300">{{ $stats['best_hour'] }}</div>
                    <div class="text-sm text-orange-700 dark:text-orange-400">Heure la plus active</div>
                </div>
            </div>

            <!-- Heatmap principale -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Activité de présence
                </h3>

                <!-- Légende -->
                <div class="flex items-center justify-end mb-4 text-sm text-gray-600 dark:text-gray-400">
                    <span class="mr-2">Moins</span>
                    <div class="flex gap-1">
                        <div class="w-4 h-4 rounded bg-gray-200 dark:bg-gray-700"></div>
                        <div class="w-4 h-4 rounded bg-green-200 dark:bg-green-900"></div>
                        <div class="w-4 h-4 rounded bg-green-400 dark:bg-green-700"></div>
                        <div class="w-4 h-4 rounded bg-green-500 dark:bg-green-500"></div>
                        <div class="w-4 h-4 rounded bg-green-700 dark:bg-green-400"></div>
                    </div>
                    <span class="ml-2">Plus</span>
                </div>

                <!-- Grille de la heatmap -->
                <div class="overflow-x-auto">
                    <div class="flex gap-1 min-w-max">
                        @php
                            $weeks = collect($heatmapData)->groupBy('week');
                        @endphp
                        
                        @foreach($weeks as $weekNum => $weekDays)
                            <div class="flex flex-col gap-1">
                                @for($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++)
                                    @php
                                        $dayData = $weekDays->firstWhere('dayOfWeek', $dayOfWeek);
                                    @endphp
                                    
                                    @if($dayData)
                                        <div class="w-4 h-4 rounded cursor-pointer transition-transform hover:scale-125
                                            @switch($dayData['intensity'])
                                                @case(0) bg-gray-200 dark:bg-gray-700 @break
                                                @case(1) bg-green-200 dark:bg-green-900 @break
                                                @case(2) bg-green-400 dark:bg-green-700 @break
                                                @case(3) bg-green-500 dark:bg-green-500 @break
                                                @case(4) bg-green-700 dark:bg-green-400 @break
                                            @endswitch"
                                            title="{{ \Carbon\Carbon::parse($dayData['date'])->translatedFormat('l d M Y') }} - {{ $dayData['total'] }} présence(s)"
                                            data-date="{{ $dayData['date'] }}"
                                            data-count="{{ $dayData['total'] }}">
                                        </div>
                                    @else
                                        <div class="w-4 h-4"></div>
                                    @endif
                                @endfor
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Labels jours -->
                <div class="flex mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col gap-1 mr-2">
                        <span class="h-4 leading-4">Dim</span>
                        <span class="h-4 leading-4">Lun</span>
                        <span class="h-4 leading-4">Mar</span>
                        <span class="h-4 leading-4">Mer</span>
                        <span class="h-4 leading-4">Jeu</span>
                        <span class="h-4 leading-4">Ven</span>
                        <span class="h-4 leading-4">Sam</span>
                    </div>
                </div>
            </div>

            <!-- Meilleur jour -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        Meilleur jour
                    </h3>
                    <div class="text-center py-4">
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['best_day'] }}</div>
                        <div class="text-lg text-green-600 dark:text-green-400 mt-2">
                            {{ $stats['best_day_count'] }} présences
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        Heure la plus active
                    </h3>
                    <div class="text-center py-4">
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['best_hour'] }}</div>
                        <div class="text-lg text-blue-600 dark:text-blue-400 mt-2">
                            {{ $stats['best_hour_count'] }} pointages
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info box -->
            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p class="font-medium">💡 Comment lire cette heatmap ?</p>
                        <p class="mt-1">Chaque carré représente un jour. Plus la couleur est foncée, plus il y a eu de présences ce jour-là. Survolez un carré pour voir les détails.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
