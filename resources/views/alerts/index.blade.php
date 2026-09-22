<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink dark:text-gray-200 leading-tight">
            Configuration des Alertes
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="bg-confirm-tint dark:bg-green-900 border border-confirm/30 dark:border-green-600 text-confirm dark:text-green-300 px-4 py-3 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <!-- État réel de la fonctionnalité -->
            <div class="mb-6 p-4 bg-accent-tint border border-accent/20 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-accent mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-accent">
                        <p class="font-semibold">Ce qui fonctionne aujourd'hui</p>
                        <p class="mt-1">« Vérifier maintenant » affiche les membres qui n'ont pas encore pointé, à relancer vous-même (téléphone). L'envoi automatique de SMS et de rappels n'est pas encore actif : les réglages ci-dessous sont enregistrés en prévision, mais ne déclenchent aucun envoi pour l'instant.</p>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6" x-data="{ loading: false, loaded: false, date: '', absents: [] }">
                <div class="bg-card p-4 rounded-lg border border-line">
                    <div class="text-2xl font-display font-semibold text-ink">{{ $stats['total_events'] }}</div>
                    <div class="text-sm text-ink-soft">Événements (30j)</div>
                </div>
                <div class="bg-card p-4 rounded-lg border border-line">
                    <div class="text-2xl font-display font-semibold text-confirm">{{ $stats['avg_presence_rate'] }}%</div>
                    <div class="text-sm text-ink-soft">Taux de présence</div>
                </div>
                <div class="bg-card p-4 rounded-lg border border-line col-span-2 md:col-span-1">
                    <button type="button" :disabled="loading" class="w-full flex flex-col items-center gap-1.5 text-center disabled:opacity-50"
                        @click="
                            loading = true;
                            fetch('{{ route('alerts.absent-members', $group) }}')
                                .then(r => r.json())
                                .then(data => { date = data.date; absents = data.members; loaded = true; })
                                .finally(() => loading = false);
                        ">
                        <svg class="w-6 h-6 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.35-4.35"/></svg>
                        <div class="text-sm font-semibold text-accent" x-text="loading ? 'Vérification…' : 'Vérifier maintenant'"></div>
                    </button>
                </div>

                <div x-show="loaded" x-cloak class="col-span-2 md:col-span-3 bg-card rounded-lg border border-line p-5">
                    <h3 class="text-sm font-semibold text-ink mb-3">
                        Membres n'ayant pas pointé <span x-show="date">le <span x-text="date"></span></span>
                    </h3>
                    <p class="text-sm text-ink-soft" x-show="absents.length === 0">Tous les membres ont pointé pour l'instant.</p>
                    <ul class="divide-y divide-line" x-show="absents.length > 0">
                        <template x-for="membre in absents" :key="membre.id">
                            <li class="flex items-center justify-between py-2.5">
                                <span class="text-sm font-medium text-ink" x-text="membre.name"></span>
                                <a :href="'tel:' + membre.phone" class="text-sm text-accent font-semibold" x-text="membre.phone"></a>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <!-- Formulaire de configuration -->
            <div class="bg-card dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <form method="POST" action="{{ route('alerts.update', $group) }}">
                    @csrf
                    @method('PUT')

                    <!-- Activation générale -->
                    <div class="mb-6 p-4 bg-paper dark:bg-gray-700 rounded-lg">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" {{ $settings->is_active ? 'checked' : '' }} 
                                   class="rounded border-line-strong dark:border-gray-600 text-accent focus:ring-accent-focus">
                            <span class="ml-3 text-lg font-semibold text-ink dark:text-white">
                                Activer les alertes et rappels
                            </span>
                        </label>
                    </div>

                    <!-- Alertes d'absence -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-ink dark:text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Alertes d'absence
                        </h3>

                        <div class="space-y-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="absence_alerts_enabled" {{ $settings->absence_alerts_enabled ? 'checked' : '' }} 
                                       class="rounded border-line-strong dark:border-gray-600 text-accent">
                                <span class="ml-2 text-ink-soft dark:text-gray-300">Activer les alertes d'absence</span>
                            </label>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-ink-soft dark:text-gray-300 mb-1">
                                        Heure de début d'événement
                                    </label>
                                    <input type="time" name="event_start_time" value="{{ $settings->event_start_time->format('H:i') }}" 
                                           class="w-full rounded-lg border-line-strong dark:border-gray-700 dark:bg-gray-900">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-ink-soft dark:text-gray-300 mb-1">
                                        Délai avant alerte (minutes)
                                    </label>
                                    <input type="number" name="alert_after_minutes" value="{{ $settings->alert_after_minutes }}" 
                                           min="5" max="120"
                                           class="w-full rounded-lg border-line-strong dark:border-gray-700 dark:bg-gray-900">
                                    <p class="text-xs text-ink-faint dark:text-gray-400 mt-1">
                                        Envoyer une alerte X minutes après le début
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-ink-soft dark:text-gray-300 mb-1">
                                    Message d'alerte personnalisé
                                </label>
                                <textarea name="alert_message_template" rows="3" 
                                          class="w-full rounded-lg border-line-strong dark:border-gray-700 dark:bg-gray-900"
                                          placeholder="Bonjour {name}, vous n'êtes pas encore enregistré pour l'événement du {date}..."
                                >{{ $settings->alert_message_template }}</textarea>
                                <p class="text-xs text-ink-faint dark:text-gray-400 mt-1">
                                    Variables disponibles: {name}, {date}, {event}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Rappels -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-ink dark:text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-accent" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                            </svg>
                            Rappels de pointage
                        </h3>

                        <div class="space-y-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="reminders_enabled" {{ $settings->reminders_enabled ? 'checked' : '' }} 
                                       class="rounded border-line-strong dark:border-gray-600 text-accent">
                                <span class="ml-2 text-ink-soft dark:text-gray-300">Activer les rappels avant événements</span>
                            </label>

                            <div>
                                <label class="block text-sm font-medium text-ink-soft dark:text-gray-300 mb-1">
                                    Envoyer rappel (heures avant)
                                </label>
                                <input type="number" name="reminder_hours_before" value="{{ $settings->reminder_hours_before }}" 
                                       min="1" max="72"
                                       class="w-full rounded-lg border-line-strong dark:border-gray-700 dark:bg-gray-900">
                            </div>
                        </div>
                    </div>

                    <!-- Canaux de notification -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-ink dark:text-white mb-4">Canaux de notification</h3>
                        <div class="space-y-3">
                            <label class="flex items-center opacity-50">
                                <input type="checkbox" name="sms_enabled" disabled
                                       class="rounded border-line-strong dark:border-gray-600">
                                <span class="ml-2 text-ink-soft dark:text-gray-300">SMS (bientôt disponible)</span>
                            </label>
                            <label class="flex items-center opacity-50">
                                <input type="checkbox" name="email_enabled" disabled
                                       class="rounded border-line-strong dark:border-gray-600">
                                <span class="ml-2 text-ink-soft dark:text-gray-300">Email (bientôt disponible)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Admin contacts -->
                    <div class="mb-6 p-4 bg-paper dark:bg-gray-700 rounded-lg">
                        <h3 class="text-sm font-semibold text-ink dark:text-white mb-3">
                            Contact administrateur (pour recevoir les résumés)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-ink-soft dark:text-gray-400 mb-1">Téléphone</label>
                                <input type="text" name="admin_phone" value="{{ $settings->admin_phone }}" 
                                       class="w-full rounded-lg border-line-strong dark:border-gray-700 dark:bg-gray-900">
                            </div>
                            <div>
                                <label class="block text-xs text-ink-soft dark:text-gray-400 mb-1">Email</label>
                                <input type="email" name="admin_email" value="{{ $settings->admin_email }}" 
                                       class="w-full rounded-lg border-line-strong dark:border-gray-700 dark:bg-gray-900">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-accent hover:bg-accent-hover text-white font-bold py-3 px-6 rounded-lg transition-colors">
                        Enregistrer les paramètres
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
