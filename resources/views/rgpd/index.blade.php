<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink dark:text-white leading-tight">
            Protection des Données (RGPD)
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 bg-confirm-tint border border-confirm/30 text-confirm px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Consentement des membres -->
            <div class="bg-card dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-ink dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-1">Consentement de vos membres</h3>
                    <p class="text-sm text-ink-soft dark:text-gray-400 mb-4">
                        Statut de consentement enregistré à la création de chaque membre.
                    </p>

                    @if($membres->isEmpty())
                        <p class="text-sm text-ink-faint dark:text-gray-400">Vous ne dirigez aucun membre pour le moment.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-paper dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">Membre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">Groupes</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">Statut</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">Méthode</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-card dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($membres as $membre)
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-ink dark:text-white">{{ $membre->name }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-ink-soft dark:text-gray-300">{{ $membre->groups->pluck('name')->implode(', ') }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                @if($membre->rgpd_consent)
                                                    <span class="text-confirm font-semibold">✓ Accordé</span>
                                                @else
                                                    <span class="text-red-600 font-semibold">✗ Non accordé</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-ink-soft dark:text-gray-300">
                                                {{ $membre->rgpd_consent_at?->format('d/m/Y à H:i') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-ink-soft dark:text-gray-300">
                                                {{ $membre->consent_method ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-card dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-ink dark:text-gray-100 space-y-6">
                    <div>
                        <h4 class="font-semibold mb-2">Données collectées sur les membres</h4>
                        <ul class="list-disc list-inside text-sm space-y-1">
                            <li>Nom et prénom</li>
                            <li>Numéro de téléphone</li>
                            <li>Données de présence (date, heure)</li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold mb-2">Finalité du traitement</h4>
                        <p class="text-sm">
                            Gestion des présences pour les réunions et événements de l'organisation.
                        </p>
                    </div>

                    <div>
                        <h4 class="font-semibold mb-2">Droits de vos membres</h4>
                        <ul class="list-disc list-inside text-sm space-y-1">
                            <li>Droit d'accès à leurs données</li>
                            <li>Droit de rectification</li>
                            <li>Droit à l'effacement</li>
                            <li>Droit de portabilité</li>
                            <li>Droit d'opposition</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Consentement du compte responsable -->
            <div class="bg-card dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-ink dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-1">Votre consentement personnel</h3>
                    <p class="text-sm text-ink-soft dark:text-gray-400 mb-4">
                        Ceci concerne votre propre compte responsable — pas les données de vos membres ci-dessus.
                    </p>
                    <div class="bg-accent-tint dark:bg-blue-900/20 p-4 rounded-lg mb-4">
                        <p class="text-sm">
                            Statut actuel :
                            @if(Auth::user()->gdpr_consent)
                                <span class="text-confirm font-semibold">✓ Consentement accordé</span>
                                <span class="text-xs text-ink-faint block">
                                    Le {{ Auth::user()->gdpr_consent_at?->format('d/m/Y à H:i') }}
                                </span>
                            @else
                                <span class="text-red-600 font-semibold">✗ Consentement non accordé</span>
                            @endif
                        </p>
                    </div>

                    @if(!Auth::user()->gdpr_consent)
                        <form method="POST" action="{{ route('rgpd.consent') }}">
                            @csrf
                            <button type="submit" class="bg-confirm hover:opacity-90 transition-opacity text-white px-4 py-2 rounded">
                                Donner mon consentement
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('rgpd.withdraw') }}">
                            @csrf
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded"
                                    onclick="return confirm('Êtes-vous sûr de vouloir retirer votre consentement ?')">
                                Retirer mon consentement
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>