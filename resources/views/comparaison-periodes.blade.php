<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            Comparaison de Périodes
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <form method="GET" class="mb-6">
                        <select name="type" onchange="this.form.submit()" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="semaine" {{ $type == 'semaine' ? 'selected' : '' }}>Cette semaine vs précédente</option>
                            <option value="mois" {{ $type == 'mois' ? 'selected' : '' }}>Ce mois vs précédent</option>
                            <option value="annee" {{ $type == 'annee' ? 'selected' : '' }}>Cette année vs précédente</option>
                        </select>
                    </form>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">Période Actuelle</h3>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $presencesActuelles }}</p>
                            <p class="text-sm text-blue-600 dark:text-blue-400">Taux: {{ $tauxActuel }}%</p>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Période Précédente</h3>
                            <p class="text-3xl font-bold text-gray-600 dark:text-gray-400">{{ $presencesPrecedentes }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Taux: {{ $tauxPrecedent }}%</p>
                        </div>
                        
                        <div class="bg-{{ $evolution >= 0 ? 'green' : 'red' }}-50 dark:bg-{{ $evolution >= 0 ? 'green' : 'red' }}-900/20 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-{{ $evolution >= 0 ? 'green' : 'red' }}-800 dark:text-{{ $evolution >= 0 ? 'green' : 'red' }}-200">Évolution</h3>
                            <p class="text-3xl font-bold text-{{ $evolution >= 0 ? 'green' : 'red' }}-600 dark:text-{{ $evolution >= 0 ? 'green' : 'red' }}-400">
                                {{ $evolution >= 0 ? '+' : '' }}{{ $evolution }}%
                            </p>
                            <p class="text-sm text-{{ $evolution >= 0 ? 'green' : 'red' }}-600 dark:text-{{ $evolution >= 0 ? 'green' : 'red' }}-400">
                                {{ $evolution >= 0 ? 'Amélioration' : 'Diminution' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>