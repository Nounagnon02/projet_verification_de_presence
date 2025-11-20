@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Politique de Confidentialité</h1>
    
    <div class="prose max-w-none">
        <h2>1. Collecte des données</h2>
        <p>Nous collectons uniquement les données nécessaires au fonctionnement du système :</p>
        <ul>
            <li>Nom et prénom</li>
            <li>Adresse email</li>
            <li>Données de présence (date, heure)</li>
        </ul>

        <h2>2. Sécurité</h2>
        <p>Mesures de sécurité mises en place :</p>
        <ul>
            <li>Chiffrement HTTPS</li>
            <li>Hachage sécurisé des mots de passe</li>
            <li>Base de données Turso avec chiffrement</li>
        </ul>

        <h2>3. Vos droits RGPD</h2>
        <ul>
            <li>Droit d'accès à vos données</li>
            <li>Droit de rectification</li>
            <li>Droit à l'effacement</li>
        </ul>

        <h2>4. Contact</h2>
        <p>Questions : <a href="mailto:privacy@verification-presence.com" class="text-blue-600">privacy@verification-presence.com</a></p>
    </div>
</div>
@endsection