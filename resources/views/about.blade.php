<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">{{ __('À propos') }}</h1>

                <div class="prose max-w-none">
                    <h2 class="text-xl font-semibold mb-4">{{ __('Notre mission') }}</h2>
                    <p class="mb-6">
                        {{ __("L'application de vérification de présence a été conçue pour simplifier la gestion des présences lors de réunions, formations et événements. Notre objectif est de fournir un outil simple, efficace et sécurisé.") }}
                    </p>

                    <h2 class="text-xl font-semibold mb-4">{{ __('Fonctionnalités') }}</h2>
                    <ul class="list-disc list-inside mb-6 space-y-2">
                        <li>{{ __('Gestion des membres par groupes') }}</li>
                        <li>{{ __('Vérification de présence rapide') }}</li>
                        <li>{{ __('Carte QR personnelle pour chaque membre') }}</li>
                        <li>{{ __('Statistiques et analyses') }}</li>
                        <li>{{ __("Suivi de l'assiduité dans le temps") }}</li>
                        <li>{{ __('Conformité RGPD') }}</li>
                    </ul>

                    <h2 class="text-xl font-semibold mb-4">{{ __('Contact') }}</h2>
                    <p>
                        {{ __("Pour toute question ou suggestion, n'hésitez pas à nous contacter.") }}
                    </p>
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
