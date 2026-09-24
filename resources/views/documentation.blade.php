<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">{{ __('Documentation') }}</h1>

                <div class="space-y-8">
                    <section>
                        <h2 class="text-2xl font-semibold mb-4">{{ __('Guide de démarrage') }}</h2>
                        <ol class="list-decimal list-inside space-y-2">
                            <li>{{ __('Créez votre compte et votre premier groupe') }}</li>
                            <li>{{ __('Ajoutez vos membres') }}</li>
                            <li>{{ __('Ouvrez une session et pointez les présences') }}</li>
                            <li>{{ __('Consultez les statistiques') }}</li>
                        </ol>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">{{ __('Fonctionnalités avancées') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-accent-tint p-4 rounded">
                                <h3 class="font-semibold">{{ __('Carte QR') }}</h3>
                                <p class="text-sm">{{ __('Imprimez la carte de chaque membre et scannez-la pendant la session') }}</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded">
                                <h3 class="font-semibold">{{ __('Assiduité') }}</h3>
                                <p class="text-sm">{{ __("Carte d'activité jour par jour et score de régularité") }}</p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded">
                                <h3 class="font-semibold">{{ __('Analyses avancées') }}</h3>
                                <p class="text-sm">{{ __('Tendances, classement des membres et comparaison de périodes') }}</p>
                            </div>
                            <div class="bg-orange-50 p-4 rounded">
                                <h3 class="font-semibold">{{ __('Export PDF') }}</h3>
                                <p class="text-sm">{{ __('Exportez vos rapports') }}</p>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">{{ __('Support technique') }}</h2>
                        <p>{{ __('Pour une aide personnalisée, contactez-nous depuis la page Contact.') }}</p>
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
