@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Conditions Générales d'Utilisation</h1>
    
    <div class="prose max-w-none">
        <h2>1. Objet</h2>
        <p>Le présent service permet la gestion et vérification de présence pour les organisations.</p>

        <h2>2. Utilisation du service</h2>
        <ul>
            <li>Service réservé aux utilisateurs authentifiés</li>
            <li>Utilisation conforme aux lois en vigueur</li>
            <li>Interdiction de partager ses identifiants</li>
        </ul>

        <h2>3. Données et sécurité</h2>
        <ul>
            <li>Hébergement sécurisé sur infrastructure Render</li>
            <li>Base de données Turso avec chiffrement</li>
            <li>Sauvegarde automatique des données</li>
        </ul>

        <h2>4. Responsabilités</h2>
        <p>L'utilisateur s'engage à utiliser le service de manière appropriée et à signaler tout dysfonctionnement.</p>

        <h2>5. Contact</h2>
        <p>Support technique : <a href="mailto:support@verification-presence.com" class="text-blue-600">support@verification-presence.com</a></p>
    </div>
</div>
@endsection