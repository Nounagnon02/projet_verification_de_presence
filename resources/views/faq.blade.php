<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">FAQ</h1>
                
                <div class="space-y-6">
                    <div class="border-b pb-4">
                        <h3 class="text-lg font-semibold mb-2">Comment ajouter un membre ?</h3>
                        <p class="text-ink-soft">Allez dans "Ajouter un membre", remplissez le nom et téléphone, et cochez le consentement RGPD.</p>
                    </div>
                    
                    <div class="border-b pb-4">
                        <h3 class="text-lg font-semibold mb-2">Comment générer un QR Code ?</h3>
                        <p class="text-ink-soft">Dans le menu "Plus" → "Générer QR Code", choisissez la date et générez le code.</p>
                    </div>
                    
                    <div class="border-b pb-4">
                        <h3 class="text-lg font-semibold mb-2">Les données sont-elles sécurisées ?</h3>
                        <p class="text-ink-soft">Oui, toutes les données sont chiffrées et nous respectons le RGPD.</p>
                    </div>
                    
                    <div class="border-b pb-4">
                        <h3 class="text-lg font-semibold mb-2">Puis-je exporter les statistiques ?</h3>
                        <p class="text-ink-soft">Oui, vous pouvez exporter les statistiques en PDF depuis la page statistiques.</p>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold mb-2">Comment retirer le consentement RGPD ?</h3>
                        <p class="text-ink-soft">Allez dans "RGPD" et cliquez sur "Retirer mon consentement".</p>
                    </div>
                </div>

                <div class="mt-8">
                    <a href="{{ route('welcome') }}" class="bg-accent hover:bg-accent text-white px-6 py-2 rounded-lg">
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>