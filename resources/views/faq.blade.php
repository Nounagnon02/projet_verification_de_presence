<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">{{ __('FAQ') }}</h1>

                <div class="space-y-6">
                    <div class="border-b pb-4">
                        <h3 class="text-lg font-semibold mb-2">{{ __('Comment ajouter un membre ?') }}</h3>
                        <p class="text-ink-soft">{{ __('Ouvrez « Membres », cliquez sur « + Ajouter », renseignez le nom et le téléphone, choisissez le ou les groupes et cochez le consentement RGPD.') }}</p>
                    </div>

                    <div class="border-b pb-4">
                        <h3 class="text-lg font-semibold mb-2">{{ __('Comment fonctionne la carte QR ?') }}</h3>
                        <p class="text-ink-soft">{{ __('Chaque membre a une carte QR personnelle : dans la liste des membres, le bouton « Carte QR » en génère le PDF à imprimer. Pour pointer, ouvrez une session depuis le tableau de bord, puis scannez les cartes.') }}</p>
                    </div>

                    <div class="border-b pb-4">
                        <h3 class="text-lg font-semibold mb-2">{{ __('Les données sont-elles sécurisées ?') }}</h3>
                        <p class="text-ink-soft">{{ __('Les échanges avec le serveur sont chiffrés et les données sont hébergées en Europe. Le consentement de chaque membre est enregistré.') }}</p>
                    </div>

                    <div class="border-b pb-4">
                        <h3 class="text-lg font-semibold mb-2">{{ __('Puis-je exporter les statistiques ?') }}</h3>
                        <p class="text-ink-soft">{{ __('Oui, le bouton « Exporter PDF » de la page Statistiques produit un document reprenant les filtres appliqués.') }}</p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-2">{{ __('Comment retirer le consentement RGPD ?') }}</h3>
                        <p class="text-ink-soft">{{ __('Ouvrez « Confidentialité (RGPD) » et utilisez « Retirer mon consentement » dans la section « Votre consentement personnel ».') }}</p>
                    </div>
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
