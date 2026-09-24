<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">{{ __('Politique de confidentialité') }}</h1>

                <x-legal-translation-notice />

                <div class="prose max-w-none space-y-6">
                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Données collectées') }}</h2>
                        <p>{{ __("Nous collectons uniquement les données nécessaires au fonctionnement de l'application :") }}</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>{{ __('Nom et prénom') }}</li>
                            <li>{{ __('Numéro de téléphone') }}</li>
                            <li>{{ __('Données de présence (date, heure)') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Utilisation des données') }}</h2>
                        <p>{{ __('Vos données sont utilisées exclusivement pour :') }}</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>{{ __('La gestion des présences') }}</li>
                            <li>{{ __('La génération de statistiques') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Protection des données') }}</h2>
                        <p>{{ __('Nous mettons en place des mesures techniques et organisationnelles pour protéger vos données :') }}</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>{{ __('Chiffrement des échanges avec le serveur (HTTPS/TLS)') }}</li>
                            <li>{{ __('Accès restreint : un responsable ne voit que les membres de ses groupes') }}</li>
                            <li>{{ __('Consentement enregistré pour chaque membre') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Vos droits') }}</h2>
                        <p>{{ __('Conformément au RGPD, vous disposez des droits suivants :') }}</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>{{ __("Droit d'accès à vos données") }}</li>
                            <li>{{ __('Droit de rectification') }}</li>
                            <li>{{ __("Droit à l'effacement") }}</li>
                            <li>{{ __('Droit de portabilité') }}</li>
                            <li>{{ __("Droit d'opposition") }}</li>
                        </ul>
                    </section>
                </div>

                <div class="mt-8">
                    <a href="{{ route('welcome') }}" class="bg-accent hover:bg-accent text-white px-6 py-2 rounded-lg">
                        {{ __("Retour à l'accueil") }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
