@component('mail::message')
# Votre Identifiant Unique - Présence

Bonjour **{{ $prenom }} {{ $nom }}**,

Votre inscription dans le système de gestion de présence a été finalisée.

## Identifiant unique

@component('mail::panel')
**{{ $identifiant }}**
@endcomponent

**Filière :** {{ $filiere }}<br>
**Année académique :** {{ $annee }}

Cet identifiant vous permet de **valider votre présence** aux cours.

@component('mail::button', ['url' => config('app.frontend_url') . '/attendance/validate'])
Valider ma présence
@endcomponent

Cordialement,<br>
**L'équipe Présence**
@endcomponent
