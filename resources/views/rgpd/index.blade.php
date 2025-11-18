<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            Protection des Données (RGPD)
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-4">Vos droits RGPD</h3>
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-4">
                            <p class="text-sm">
                                Statut actuel : 
                                @if(Auth::user()->gdpr_consent)
                                    <span class="text-green-600 font-semibold">✓ Consentement accordé</span>
                                    <span class="text-xs text-gray-500 block">
                                        Le {{ Auth::user()->gdpr_consent_at?->format('d/m/Y à H:i') }}
                                    </span>
                                @else
                                    <span class="text-red-600 font-semibold">✗ Consentement non accordé</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <h4 class="font-semibold mb-2">Données collectées</h4>
                            <ul class="list-disc list-inside text-sm space-y-1">
                                <li>Nom et prénom</li>
                                <li>Numéro de téléphone</li>
                                <li>Données de présence (date, heure)</li>
                                <li>Signatures électroniques (si utilisées)</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold mb-2">Finalité du traitement</h4>
                            <p class="text-sm">
                                Gestion des présences pour les réunions et événements de l'organisation.
                            </p>
                        </div>

                        <div>
                            <h4 class="font-semibold mb-2">Vos droits</h4>
                            <ul class="list-disc list-inside text-sm space-y-1">
                                <li>Droit d'accès à vos données</li>
                                <li>Droit de rectification</li>
                                <li>Droit à l'effacement</li>
                                <li>Droit de portabilité</li>
                                <li>Droit d'opposition</li>
                            </ul>
                        </div>

                        <div class="flex space-x-4 pt-4">
                            @if(!Auth::user()->gdpr_consent)
                                <form method="POST" action="{{ route('rgpd.consent') }}">
                                    @csrf
                                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
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
        </div>
    </div>
</x-app-layout>