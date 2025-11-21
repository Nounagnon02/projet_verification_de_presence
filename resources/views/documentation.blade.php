<x-guest-layout>
    <div class="min-h-screen bg-gray-50 py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Documentation</h1>
                
                <div class="space-y-8">
                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Guide de démarrage</h2>
                        <ol class="list-decimal list-inside space-y-2">
                            <li>Créez votre compte</li>
                            <li>Ajoutez vos premiers membres</li>
                            <li>Vérifiez les présences</li>
                            <li>Consultez les statistiques</li>
                        </ol>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Fonctionnalités avancées</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-blue-50 p-4 rounded">
                                <h3 class="font-semibold">QR Codes</h3>
                                <p class="text-sm">Générez des codes pour présence mobile</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded">
                                <h3 class="font-semibold">Signatures</h3>
                                <p class="text-sm">Validez avec des signatures électroniques</p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded">
                                <h3 class="font-semibold">Comparaisons</h3>
                                <p class="text-sm">Analysez les tendances de présence</p>
                            </div>
                            <div class="bg-orange-50 p-4 rounded">
                                <h3 class="font-semibold">Export PDF</h3>
                                <p class="text-sm">Exportez vos rapports</p>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Support technique</h2>
                        <p>Pour une aide personnalisée, contactez notre équipe support.</p>
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