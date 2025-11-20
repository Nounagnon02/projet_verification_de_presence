@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">À propos du système</h1>
        <p class="text-xl text-gray-600">Solution moderne de gestion de présence</p>
    </div>

    <div class="grid md:grid-cols-2 gap-8 mb-12">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold mb-4">🔧 Technologie</h2>
            <ul class="space-y-2 text-gray-700">
                <li>• Framework Laravel 12</li>
                <li>• Base de données Turso (SQLite distribué)</li>
                <li>• Hébergement Render avec HTTPS</li>
                <li>• Interface responsive Tailwind CSS</li>
                <li>• QR Codes pour vérification rapide</li>
            </ul>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold mb-4">🔒 Sécurité</h2>
            <ul class="space-y-2 text-gray-700">
                <li>• Chiffrement HTTPS automatique</li>
                <li>• Authentification sécurisée</li>
                <li>• Conformité RGPD</li>
                <li>• Données chiffrées en base</li>
                <li>• Sauvegarde automatique</li>
            </ul>
        </div>
    </div>

    <div class="bg-gray-50 p-8 rounded-lg mb-8">
        <h2 class="text-2xl font-semibold mb-4">📊 Fonctionnalités</h2>
        <div class="grid md:grid-cols-3 gap-6">
            <div>
                <h3 class="font-semibold text-lg mb-2">Vérification</h3>
                <p class="text-gray-600">Enregistrement rapide des présences via interface web ou QR Code</p>
            </div>
            <div>
                <h3 class="font-semibold text-lg mb-2">Statistiques</h3>
                <p class="text-gray-600">Tableaux de bord avec graphiques et export PDF</p>
            </div>
            <div>
                <h3 class="font-semibold text-lg mb-2">Gestion</h3>
                <p class="text-gray-600">Administration des utilisateurs et paramètres</p>
            </div>
        </div>
    </div>

    <div class="text-center">
        <h2 class="text-2xl font-semibold mb-4">📞 Support</h2>
        <p class="text-gray-600 mb-4">Besoin d'aide ou de fonctionnalités supplémentaires ?</p>
        <a href="mailto:support@verification-presence.com" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
            Nous contacter
        </a>
    </div>
</div>
@endsection