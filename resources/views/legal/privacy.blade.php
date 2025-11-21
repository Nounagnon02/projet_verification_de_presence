<x-guest-layout>
    <div class="min-h-screen bg-gray-50 py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Politique de Confidentialité</h1>
                
                <div class="prose max-w-none space-y-6">
                    <section>
                        <h2 class="text-xl font-semibold mb-3">Données collectées</h2>
                        <p>Nous collectons uniquement les données nécessaires au fonctionnement de l'application :</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>Nom et prénom</li>
                            <li>Numéro de téléphone</li>
                            <li>Données de présence (date, heure)</li>
                            <li>Signatures électroniques (optionnel)</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Utilisation des données</h2>
                        <p>Vos données sont utilisées exclusivement pour :</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>La gestion des présences</li>
                            <li>La génération de statistiques</li>
                            <li>L'amélioration de nos services</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Protection des données</h2>
                        <p>Nous mettons en place des mesures techniques et organisationnelles pour protéger vos données :</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>Chiffrement des données</li>
                            <li>Accès restreint</li>
                            <li>Sauvegardes sécurisées</li>
                            <li>Conformité RGPD</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Vos droits</h2>
                        <p>Conformément au RGPD, vous disposez des droits suivants :</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>Droit d'accès à vos données</li>
                            <li>Droit de rectification</li>
                            <li>Droit à l'effacement</li>
                            <li>Droit de portabilité</li>
                            <li>Droit d'opposition</li>
                        </ul>
                    </section>
                </div>

                <div class="mt-8">
                    <a href="{{ route('welcome') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>