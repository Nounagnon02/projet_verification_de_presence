<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">{{ __("Conditions générales d'utilisation") }}</h1>

                <x-legal-translation-notice />

                <div class="prose max-w-none space-y-6">
                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Objet') }}</h2>
                        <p>
                            {{ __("Les présentes conditions générales d'utilisation (CGU) régissent l'utilisation de l'application de vérification de présence. En utilisant cette application, vous acceptez ces conditions.") }}
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Utilisation autorisée') }}</h2>
                        <p>{{ __("L'application est destinée à :") }}</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>{{ __("La gestion des présences lors d'événements") }}</li>
                            <li>{{ __('La génération de statistiques de présence') }}</li>
                            <li>{{ __("L'organisation de réunions et formations") }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __("Obligations de l'utilisateur") }}</h2>
                        <p>{{ __("En utilisant l'application, vous vous engagez à :") }}</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>{{ __('Fournir des informations exactes') }}</li>
                            <li>{{ __('Respecter la confidentialité des données') }}</li>
                            <li>{{ __("Ne pas utiliser l'application à des fins illégales") }}</li>
                            <li>{{ __('Obtenir le consentement des personnes enregistrées') }}</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Responsabilité') }}</h2>
                        <p>
                            {{ __("L'utilisateur est responsable de l'utilisation qu'il fait de l'application et des données qu'il y saisit. Nous nous efforçons de maintenir la disponibilité du service mais ne garantissons pas une disponibilité de 100 %.") }}
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Modification des CGU') }}</h2>
                        <p>
                            {{ __('Nous nous réservons le droit de modifier ces conditions à tout moment. Les utilisateurs seront informés des modifications importantes.') }}
                        </p>
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
