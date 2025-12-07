<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            🔔 Configuration des Alertes
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Statistiques -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ $stats['total_events'] }}</div>
                    <div class="text-sm text-blue-700 dark:text-blue-400">Événements (30j)</div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                    <div class="text-2xl font-bold text-green-900 dark:text-green-300">{{ $stats['avg_presence_rate'] }}%</div>
                    <div class="text-sm text-green-700 dark:text-green-400">Taux de présence</div>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 p-4 rounded-lg border border-purple-200 dark:border-purple-800 col-span-2 md:col-span-1">
                    <button onclick="checkAbsences()" class="w-full text-center">
                        <div class="text-2xl font-bold text-purple-900 dark:text-purple-300">🔍</div>
                        <div class="text-sm text-purple-700 dark:text-purple-400">Vérifier maintenant</div>
                    </button>
                </div>
            </div>

            <!-- Formulaire de configuration -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <form method="POST" action="{{ route('alerts.update') }}">
                    @csrf
                    @method('PUT')

                    <!-- Activation générale -->
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" {{ $settings->is_active ? 'checked' : '' }} 
                                   class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 text-lg font-semibold text-gray-900 dark:text-white">
                                🔔 Activer les alertes et rappels
                            </span>
                        </label>
                    </div>

                    <!-- Alertes d'absence -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Alertes d'absence
                        </h3>

                        <div class="space-y-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="absence_alerts_enabled" {{ $settings->absence_alerts_enabled ? 'checked' : '' }} 
                                       class="rounded border-gray-300 dark:border-gray-600 text-blue-600">
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Activer les alertes d'absence</span>
                            </label>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Heure de début d'événement
                                    </label>
                                    <input type="time" name="event_start_time" value="{{ $settings->event_start_time->format('H:i') }}" 
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Délai avant alerte (minutes)
                                    </label>
                                    <input type="number" name="alert_after_minutes" value="{{ $settings->alert_after_minutes }}" 
                                           min="5" max="120"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Envoyer une alerte X minutes après le début
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Message d'alerte personnalisé
                                </label>
                                <textarea name="alert_message_template" rows="3" 
                                          class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                          placeholder="Bonjour {name}, vous n'êtes pas encore enregistré pour l'événement du {date}..."
                                >{{ $settings->alert_message_template }}</textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Variables disponibles: {name}, {date}, {event}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Rappels -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                            </svg>
                            Rappels de pointage
                        </h3>

                        <div class="space-y-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="reminders_enabled" {{ $settings->reminders_enabled ? 'checked' : '' }} 
                                       class="rounded border-gray-300 dark:border-gray-600 text-blue-600">
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Activer les rappels avant événements</span>
                            </label>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Envoyer rappel (heures avant)
                                </label>
                                <input type="number" name="reminder_hours_before" value="{{ $settings->reminder_hours_before }}" 
                                       min="1" max="72"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            </div>
                        </div>
                    </div>

                    <!-- Canaux de notification -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Canaux de notification</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="sms_enabled" {{ $settings->sms_enabled ? 'checked' : '' }} 
                                       class="rounded border-gray-300 dark:border-gray-600 text-blue-600">
                                <span class="ml-2 text-gray-700 dark:text-gray-300">📱 SMS</span>
                            </label>
                            <label class="flex items-center opacity-50">
                                <input type="checkbox" name="email_enabled" disabled
                                       class="rounded border-gray-300 dark:border-gray-600">
                                <span class="ml-2 text-gray-700 dark:text-gray-300">📧 Email (bientôt disponible)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Admin contacts -->
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                            👤 Contact administrateur (pour recevoir les résumés)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Téléphone</label>
                                <input type="text" name="admin_phone" value="{{ $settings->admin_phone }}" 
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Email</label>
                                <input type="email" name="admin_email" value="{{ $settings->admin_email }}" 
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                        💾 Enregistrer les paramètres
                    </button>
                </form>
            </div>

            <!-- Info -->
            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p class="font-medium">💡 Comment ça marche ?</p>
                        <p class="mt-1">Les alertes d'absence sont envoyées automatiquement aux membres qui n'ont pas pointé après le délai configuré. Les rappels sont envoyés avant les événements programmés dans l'agenda.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function checkAbsences() {
            fetch('{{ route("alerts.check-now") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'processed') {
                    alert(`✅ Vérification effectuée!\n${data.alerts_sent} alerte(s) envoyée(s)`);
                } else if (data.status === 'no_event') {
                    alert('ℹ️ Aucun événement programmé aujourd\'hui');
                } else {
                    alert('ℹ️ Alertes désactivées');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('❌ Erreur lors de la vérification');
            });
        }
    </script>
</x-app-layout>
