<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h2 class="text-2xl font-bold mb-6 text-center">Statistiques de Présence</h2>

                    <!-- Formulaire de filtrage -->
                    <form method="GET" action="{{ route('statistiques') }}" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Filtre par date -->
                            <div>
                                <x-input-label for="date" :value="__('Date')" />
                                <x-text-input
                                    id="date"
                                    class="block mt-1 w-full"
                                    type="date"
                                    name="date"
                                    :value="$date"
                                />
                            </div>

                            <!-- Recherche par nom ou téléphone -->
                            <div>
                                <x-input-label for="search" :value="__('Rechercher')" />
                                <x-text-input
                                    id="search"
                                    class="block mt-1 w-full"
                                    type="text"
                                    name="search"
                                    :value="$search"
                                    placeholder="Nom ou téléphone"
                                />
                            </div>

                            <!-- Bouton de filtre -->
                            <div class="flex items-end">
                                <x-primary-button class="w-full">
                                    {{ __('Filtrer') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>

                    <!-- Statistiques -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-100 p-4 rounded-lg text-center">
                            <h3 class="text-lg font-semibold text-blue-800">Présents</h3>
                            <p class="text-2xl font-bold text-blue-800">{{ $totalPresent }}</p>
                        </div>
                        <div class="bg-green-100 p-4 rounded-lg text-center">
                            <h3 class="text-lg font-semibold text-green-800">Total Membres</h3>
                            <p class="text-2xl font-bold text-green-800">{{ $totalMembres }}</p>
                        </div>
                        <div class="bg-purple-100 p-4 rounded-lg text-center">
                            <h3 class="text-lg font-semibold text-purple-800">Taux de Présence</h3>
                            <p class="text-2xl font-bold text-purple-800">{{ $tauxPresence }}%</p>
                        </div>
                    </div>

                    <!-- Liste des présences -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">Nom</th>
                                    <th class="py-3 px-6 text-left">Téléphone</th>
                                    <th class="py-3 px-6 text-center">Heure d'arrivée</th>
                                    <th class="py-3 px-6 text-center">Date</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm">
                                @forelse($presences as $presence)
                                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                                        <td class="py-3 px-6 text-left">
                                            {{ $presence->member->name }}
                                        </td>
                                        <td class="py-3 px-6 text-left">
                                            {{ $presence->member->phone }}
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            {{ \Carbon\Carbon::parse($presence->time)->format('H:i') }}
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            {{ \Carbon\Carbon::parse($presence->date)->format('d/m/Y') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-4 px-6 text-center">
                                            Aucune présence enregistrée pour cette date.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Bouton d'export -->
                    <div class="mt-6">
                        <a href="{{ route('statistiques', array_merge(request()->all(), ['export' => 'pdf'])) }}"
                           class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                            Exporter en PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
