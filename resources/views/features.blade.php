<x-guest-layout>
    <div class="min-h-screen bg-gray-50 py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Fonctionnalités</h1>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">📝 Gestion des membres</h3>
                        <p class="text-sm">Ajout, modification et suppression des membres par groupes</p>
                    </div>
                    
                    <div class="bg-green-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">✅ Vérification rapide</h3>
                        <p class="text-sm">Interface simple pour marquer les présences</p>
                    </div>
                    
                    <div class="bg-purple-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">QR Code</h3>
                        <p class="text-sm">Génération et scan de QR codes pour présence mobile</p>
                    </div>
                    
                    <div class="bg-orange-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">Statistiques</h3>
                        <p class="text-sm">Analyses détaillées et comparaisons de périodes</p>
                    </div>
                    
                    <div class="bg-red-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">✍️ Signatures</h3>
                        <p class="text-sm">Signatures électroniques pour validation</p>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-lg font-semibold mb-3">RGPD</h3>
                        <p class="text-sm">Conformité totale avec la protection des données</p>
                    </div>
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