<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">{{ __('Fonctionnalités') }}</h1>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">{{ __('Gestion des membres') }}</h3>
                        <p class="text-sm text-ink-soft">{{ __('Ajout, modification et suppression des membres par groupes') }}</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">{{ __('Vérification rapide') }}</h3>
                        <p class="text-sm text-ink-soft">{{ __('Interface simple pour marquer les présences') }}</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">{{ __('Carte QR') }}</h3>
                        <p class="text-sm text-ink-soft">{{ __('Une carte QR par membre, à scanner pour pointer sa présence') }}</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">{{ __('Statistiques') }}</h3>
                        <p class="text-sm text-ink-soft">{{ __('Analyses détaillées et comparaisons de périodes') }}</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">{{ __('Assiduité') }}</h3>
                        <p class="text-sm text-ink-soft">{{ __("Score de régularité par membre et carte d'activité jour par jour") }}</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">{{ __('RGPD') }}</h3>
                        <p class="text-sm text-ink-soft">{{ __('Consentement enregistré pour chaque membre') }}</p>
                    </div>
                </div>

                <div class="mt-8">
                    <a href="{{ route('welcome') }}" class="bg-accent hover:bg-accent-hover text-white px-6 py-2 rounded-lg">
                        {{ __("Retour à l'accueil") }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
