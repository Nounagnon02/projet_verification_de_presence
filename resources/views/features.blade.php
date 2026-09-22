<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">Fonctionnalités</h1>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">Gestion des membres</h3>
                        <p class="text-sm text-ink-soft">Ajout, modification et suppression des membres par groupes</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">Vérification rapide</h3>
                        <p class="text-sm text-ink-soft">Interface simple pour marquer les présences</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">QR Code</h3>
                        <p class="text-sm text-ink-soft">Génération et scan de QR codes pour présence mobile</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">Statistiques</h3>
                        <p class="text-sm text-ink-soft">Analyses détaillées et comparaisons de périodes</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">Signatures</h3>
                        <p class="text-sm text-ink-soft">Signatures électroniques pour validation</p>
                    </div>

                    <div class="bg-paper2 border border-line p-6 rounded-lg">
                        <h3 class="font-display text-lg font-semibold mb-3">RGPD</h3>
                        <p class="text-sm text-ink-soft">Conformité totale avec la protection des données</p>
                    </div>
                </div>

                <div class="mt-8">
                    <a href="{{ route('welcome') }}" class="bg-accent hover:bg-accent-hover text-white px-6 py-2 rounded-lg">
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>