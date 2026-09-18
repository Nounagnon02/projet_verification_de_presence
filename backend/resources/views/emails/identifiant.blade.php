@component('mail::message')
# Vos identifiants de connexion - Présence

Bonjour **{{ $prenom }} {{ $nom }}**,

Votre inscription dans le système de gestion de présence a été finalisée.

## Identifiant unique

@component('mail::panel')
**{{ $identifiant }}**
@endcomponent

{{-- Le code n'accompagne l'identifiant que lorsqu'il vient d'être tiré : il
     n'est jamais relu depuis la base, qui n'en garde que le hachage. Un renvoi
     d'identifiants sans nouveau tirage n'a donc rien à afficher ici. --}}
@isset($code)
## Code d'accès

@component('mail::panel')
**{{ $code }}**
@endcomponent

Ce code est **personnel et secret** : il est le seul élément de votre connexion
que personne d'autre ne peut deviner. Ne le communiquez à aucun camarade — toute
présence validée avec lui vous sera imputée.
@endisset

**Filière :** {{ $filiere }}<br>
**Année académique :** {{ $annee }}

@isset($code)
Ces identifiants vous permettent de **valider votre présence** aux cours.
@else
Cet identifiant vous permet de **valider votre présence** aux cours.
@endisset

@component('mail::button', ['url' => config('app.frontend_url') . '/attendance/validate'])
Valider ma présence
@endcomponent

Cordialement,<br>
**L'équipe Présence**
@endcomponent
