<x-guest-layout>
    <div class="min-h-screen bg-gray-50 py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">À propos</h1>
                
                <div class="prose max-w-none">
                    <h2 class="text-xl font-semibold mb-4">Notre Mission</h2>
                    <p class="mb-6">
                        L'application de vérification de présence a été conçue pour simplifier la gestion des présences 
                        lors de réunions, formations et événements. Notre objectif est de fournir un outil simple, 
                        efficace et sécurisé.
                    </p>

                    <h2 class="text-xl font-semibold mb-4">Fonctionnalités</h2>
                    <ul class="list-disc list-inside mb-6 space-y-2">
                        <li>Gestion des membres par groupes</li>
                        <li>Vérification de présence rapide</li>
                        <li>Génération de QR codes</li>
                        <li>Statistiques et analyses</li>
                        <li>Signatures électroniques</li>
                        <li>Conformité RGPD</li>
                    </ul>

                    <h2 class="text-xl font-semibold mb-4">Contact</h2>
                    <p>
                        Pour toute question ou suggestion, n'hésitez pas à nous contacter.
                    </p>
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