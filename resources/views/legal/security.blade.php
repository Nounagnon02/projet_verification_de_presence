<x-guest-layout>
    <div class="min-h-screen bg-gray-50 py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Sécurité</h1>
                
                <div class="prose max-w-none space-y-6">
                    <section>
                        <h2 class="text-xl font-semibold mb-3">Mesures de sécurité</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-green-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-green-800 mb-2">🔐 Chiffrement</h3>
                                <p class="text-sm">{{ $securityInfo['encryption'] }}</p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-blue-800 mb-2">☁️ Hébergement</h3>
                                <p class="text-sm">{{ $securityInfo['hosting'] }}</p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-purple-800 mb-2">🗄️ Base de données</h3>
                                <p class="text-sm">{{ $securityInfo['database'] }}</p>
                            </div>
                            <div class="bg-orange-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-orange-800 mb-2">💾 Sauvegarde</h3>
                                <p class="text-sm">{{ $securityInfo['backup'] }}</p>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Conformité</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach($securityInfo['compliance'] as $standard)
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                    ✓ {{ $standard }}
                                </span>
                            @endforeach
                        </div>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Audit de sécurité</h2>
                        <p class="bg-gray-50 p-4 rounded-lg">
                            Dernier audit : <strong>{{ $securityInfo['last_audit'] }}</strong>
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">Signalement de vulnérabilité</h2>
                        <p>
                            Si vous découvrez une faille de sécurité, merci de nous contacter immédiatement 
                            pour que nous puissions la corriger rapidement.
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