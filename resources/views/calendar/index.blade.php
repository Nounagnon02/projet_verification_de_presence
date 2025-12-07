<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            📅 {{ __('Agenda - Programmation des séances') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Messages de succès/erreur -->
            @if(session('success'))
                <div class="mb-6 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg mx-2 md:mx-0">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg mx-2 md:mx-0">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(isset($error) && $error)
                <div class="mb-6 bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 dark:border-yellow-600 text-yellow-700 dark:text-yellow-300 px-4 py-3 rounded-lg mx-2 md:mx-0">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $error }}
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mx-2 md:mx-0">
                <!-- Formulaire de création d'événement -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Créer un événement
                        </h3>

                        <form method="POST" action="{{ route('calendar.store') }}" class="space-y-4">
                            @csrf

                            <!-- Nom de l'événement -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Nom de la séance *
                                </label>
                                <input type="text" 
                                       name="name" 
                                       id="name" 
                                       value="{{ old('name') }}"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Ex: Réunion hebdomadaire"
                                       required>
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Description
                                </label>
                                <textarea name="description" 
                                          id="description" 
                                          rows="3"
                                          class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Description de l'événement...">{{ old('description') }}</textarea>
                            </div>

                            <!-- Date et heure de début -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Date de début *
                                    </label>
                                    <input type="date" 
                                           name="start_date" 
                                           id="start_date" 
                                           value="{{ old('start_date', date('Y-m-d')) }}"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                                <div>
                                    <label for="start_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Heure de début *
                                    </label>
                                    <input type="time" 
                                           name="start_time" 
                                           id="start_time" 
                                           value="{{ old('start_time', '09:00') }}"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                            </div>

                            <!-- Date et heure de fin -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Date de fin *
                                    </label>
                                    <input type="date" 
                                           name="end_date" 
                                           id="end_date" 
                                           value="{{ old('end_date', date('Y-m-d')) }}"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                                <div>
                                    <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Heure de fin *
                                    </label>
                                    <input type="time" 
                                           name="end_time" 
                                           id="end_time" 
                                           value="{{ old('end_time', '10:00') }}"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                            </div>

                            <!-- Bouton de soumission -->
                            <div class="pt-4">
                                <button type="submit" 
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Créer l'événement
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste des événements -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Événements à venir (30 jours)
                        </h3>

                        @if($events->count() > 0)
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                @foreach($events as $event)
                                    <div class="border dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <h4 class="font-medium text-gray-900 dark:text-white">
                                                    {{ $event->name }}
                                                </h4>
                                                @if($event->description)
                                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                                        {{ Str::limit($event->description, 100) }}
                                                    </p>
                                                @endif
                                                <div class="flex items-center mt-2 text-sm text-gray-600 dark:text-gray-300">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    @if($event->startDateTime)
                                                        {{ \Carbon\Carbon::parse($event->startDateTime)->format('d/m/Y H:i') }}
                                                        -
                                                        {{ \Carbon\Carbon::parse($event->endDateTime)->format('H:i') }}
                                                    @elseif($event->startDate)
                                                        {{ \Carbon\Carbon::parse($event->startDate)->format('d/m/Y') }}
                                                        (Journée entière)
                                                    @endif
                                                </div>
                                            </div>
                                            <form method="POST" action="{{ route('calendar.destroy', $event->id) }}" 
                                                  onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                                        title="Supprimer">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p>Aucun événement prévu pour les 30 prochains jours</p>
                                <p class="text-sm mt-2">Créez votre premier événement !</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Info box -->
            <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mx-2 md:mx-0">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p class="font-medium">Synchronisation avec Google Agenda</p>
                        <p class="mt-1">Les événements créés ici sont automatiquement synchronisés avec votre Google Agenda. Vous pouvez les consulter et les modifier depuis l'application Google Agenda sur votre téléphone ou ordinateur.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Synchroniser la date de fin avec la date de début
        document.getElementById('start_date').addEventListener('change', function() {
            const endDate = document.getElementById('end_date');
            if (!endDate.value || endDate.value < this.value) {
                endDate.value = this.value;
            }
        });

        // Synchroniser l'heure de fin avec l'heure de début (+ 1 heure)
        document.getElementById('start_time').addEventListener('change', function() {
            const startTime = this.value;
            const [hours, minutes] = startTime.split(':').map(Number);
            const endHours = (hours + 1) % 24;
            const endTime = `${String(endHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
            document.getElementById('end_time').value = endTime;
        });
    </script>
</x-app-layout>
