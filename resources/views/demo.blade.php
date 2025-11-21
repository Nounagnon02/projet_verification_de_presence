<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            Démonstration
        </h2>
    </x-slot>

    <div class="py-12">
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">🎯 Démonstration du système</h1>
        <p class="text-lg text-gray-600">Découvrez les fonctionnalités en action</p>
    </div>

    <div class="grid md:grid-cols-2 gap-8 mb-8">
        <!-- Capture d'écran Dashboard -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-gray-800 p-4">
                <h3 class="text-white font-semibold">📊 Dashboard Principal</h3>
            </div>
            <div class="p-6">
                <div class="bg-gray-100 h-48 rounded flex items-center justify-center mb-4">
                    <div class="text-center text-gray-500">
                        <div class="text-4xl mb-2">📈</div>
                        <p>Interface de gestion</p>
                        <p class="text-sm">Statistiques en temps réel</p>
                    </div>
                </div>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Vue d'ensemble des présences</li>
                    <li>• Graphiques interactifs</li>
                    <li>• Export PDF automatique</li>
                </ul>
            </div>
        </div>

        <!-- QR Code Demo -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-blue-600 p-4">
                <h3 class="text-white font-semibold">📱 QR Code</h3>
            </div>
            <div class="p-6">
                <div class="bg-gray-100 h-48 rounded flex items-center justify-center mb-4">
                    <div class="text-center text-gray-500">
                        <div class="text-4xl mb-2">⬜</div>
                        <p>QR Code généré</p>
                        <p class="text-sm">Scan rapide</p>
                    </div>
                </div>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Génération automatique</li>
                    <li>• Vérification instantanée</li>
                    <li>• Compatible mobile</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Workflow -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-8 rounded-lg mb-8">
        <h2 class="text-2xl font-semibold mb-6 text-center">🔄 Processus simplifié</h2>
        <div class="grid md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="bg-white p-4 rounded-lg shadow mb-3">
                    <div class="text-2xl mb-2">👤</div>
                    <h3 class="font-semibold">1. Inscription</h3>
                </div>
                <p class="text-sm text-gray-600">Création de compte sécurisée</p>
            </div>
            <div class="text-center">
                <div class="bg-white p-4 rounded-lg shadow mb-3">
                    <div class="text-2xl mb-2">📱</div>
                    <h3 class="font-semibold">2. Vérification</h3>
                </div>
                <p class="text-sm text-gray-600">Scan QR ou saisie manuelle</p>
            </div>
            <div class="text-center">
                <div class="bg-white p-4 rounded-lg shadow mb-3">
                    <div class="text-2xl mb-2">📊</div>
                    <h3 class="font-semibold">3. Suivi</h3>
                </div>
                <p class="text-sm text-gray-600">Statistiques automatiques</p>
            </div>
            <div class="text-center">
                <div class="bg-white p-4 rounded-lg shadow mb-3">
                    <div class="text-2xl mb-2">📄</div>
                    <h3 class="font-semibold">4. Export</h3>
                </div>
                <p class="text-sm text-gray-600">Rapports PDF détaillés</p>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="text-center bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-2xl font-semibold mb-4">Prêt à commencer ?</h2>
        <p class="text-gray-600 mb-6">Créez votre compte gratuitement et testez toutes les fonctionnalités</p>
        <div class="space-x-4">
            @guest
                <a href="{{ route('register') }}" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    Créer un compte
                </a>
                <a href="{{ route('login') }}" class="border border-blue-600 text-blue-600 px-6 py-3 rounded-lg hover:bg-blue-50 transition-colors">
                    Se connecter
                </a>
            @else
                <a href="{{ route('dashboard') }}" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    Accéder au Dashboard
                </a>
            @endguest
        </div>
        </div>
    </div>
</x-app-layout>