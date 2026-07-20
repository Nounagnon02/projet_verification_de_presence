@component('mail::message')
# Bienvenue sur Présence

Bonjour **{{ $name }}**,

Votre compte étudiant a été créé avec succès dans le **Système de Gestion de Présence**.

## Votre identifiant unique

@component('mail::panel')
**{{ $idUnique }}**
@endcomponent

Cet identifiant vous servira à **valider votre présence** lors de chaque séance de cours en le saisissant après avoir scanné le QR Code affiché par votre enseignant.

### Instructions
1. Scannez le QR Code affiché en classe avec votre smartphone
2. Saisissez votre identifiant unique (ci-dessus)
3. Votre présence est enregistrée !

> ⚠️ **Important :** Conservez précieusement cet identifiant. Il est personnel et non modifiable.

@component('mail::button', ['url' => config('app.frontend_url')])
Accéder à la plateforme
@endcomponent

Cordialement,<br>
**L'équipe Présence**

---
*Ce message est automatique, merci de ne pas y répondre.*
@endcomponent
