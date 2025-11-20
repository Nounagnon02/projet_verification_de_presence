@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">🔒 Sécurité et Infrastructure</h1>
    
    <div class="grid md:grid-cols-2 gap-8 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4 text-green-700">✅ Chiffrement</h2>
            <ul class="space-y-2 text-gray-700">
                <li>• <strong>Transport :</strong> HTTPS/TLS 1.3</li>
                <li>• <strong>Base de données :</strong> {{ $securityInfo['encryption'] }}</li>
                <li>• <strong>Mots de passe :</strong> Bcrypt (Laravel)</li>
                <li>• <strong>Sessions :</strong> Chiffrées côté serveur</li>
            </ul>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-4 text-blue-700">🏗️ Infrastructure</h2>
            <ul class="space-y-2 text-gray-700">
                <li>• <strong>Hébergement :</strong> {{ $securityInfo['hosting'] }}</li>
                <li>• <strong>Base de données :</strong> {{ $securityInfo['database'] }}</li>
                <li>• <strong>Sauvegarde :</strong> {{ $securityInfo['backup'] }}</li>
                <li>• <strong>Monitoring :</strong> 24/7</li>
            </ul>
        </div>
    </div>

    <div class="bg-gray-50 p-6 rounded-lg mb-8">
        <h2 class="text-xl font-semibold mb-4">📋 Conformité</h2>
        <div class="grid md:grid-cols-3 gap-4">
            @foreach($securityInfo['compliance'] as $standard)
            <div class="bg-white p-4 rounded text-center">
                <span class="text-green-600 font-semibold">✓ {{ $standard }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-blue-50 p-6 rounded-lg">
        <h2 class="text-xl font-semibold mb-4">🛡️ Mesures de protection</h2>
        <ul class="grid md:grid-cols-2 gap-2 text-gray-700">
            <li>• Protection CSRF automatique</li>
            <li>• Validation stricte des entrées</li>
            <li>• Limitation du taux de requêtes</li>
            <li>• Logs de sécurité complets</li>
            <li>• Authentification à deux facteurs</li>
            <li>• Expiration automatique des sessions</li>
        </ul>
    </div>

    <div class="text-center mt-8 p-4 bg-green-50 rounded-lg">
        <p class="text-sm text-gray-600">
            <strong>Dernier audit de sécurité :</strong> {{ $securityInfo['last_audit'] }}
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Signaler une vulnérabilité : <a href="mailto:security@verification-presence.com" class="text-blue-600">security@verification-presence.com</a>
        </p>
    </div>
</div>
@endsection