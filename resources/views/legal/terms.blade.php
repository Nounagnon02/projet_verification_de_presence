<x-guest-layout>
    <div class="min-h-screen bg-gray-50 py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Conditions Générales d'Utilisation</h1>
                
                <div class="prose max-w-none space-y-6">
                    <section>
                        <h2 class="text-xl font-semibold mb-3">Objet</h2>
                        <p>
                            Les présentes conditions générales d'utilisation (CGU) régissent l'utilisation de l'application 
                            de vérification de présence. En utilisant cette application, vous acceptez ces conditions.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Utilisation autorisée</h2>
                        <p>L'application est destinée à :</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>La gestion des présences lors d'événements</li>
                            <li>La génération de statistiques de présence</li>
                            <li>L'organisation de réunions et formations</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Obligations de l'utilisateur</h2>
                        <p>En utilisant l'application, vous vous engagez à :</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>Fournir des informations exactes</li>
                            <li>Respecter la confidentialité des données</li>
                            <li>Ne pas utiliser l'application à des fins illégales</li>
                            <li>Obtenir le consentement des personnes enregistrées</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Responsabilité</h2>
                        <p>
                            L'utilisateur est responsable de l'utilisation qu'il fait de l'application et des données 
                            qu'il y saisit. Nous nous efforçons de maintenir la disponibilité du service mais ne 
                            garantissons pas une disponibilité de 100%.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Modification des CGU</h2>
                        <p>
                            Nous nous réservons le droit de modifier ces conditions à tout moment. 
                            Les utilisateurs seront informés des modifications importantes.
                        </p>
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