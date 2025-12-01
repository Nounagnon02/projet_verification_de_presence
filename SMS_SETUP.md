# Configuration SMS avec Lara SMS

## Installation

1. **Package déjà installé :**
```bash
yasser-elgammal/lara-sms
```

2. **Configuration publiée :**
```bash
config/sms.php
```

## Configuration

### Mode Développement (Logs uniquement)
```env
SMS_DEFAULT_GATEWAY=infobip
# Laissez les clés vides pour mode développement
```

### Mode Production avec Twilio
```env
SMS_DEFAULT_GATEWAY=twilio
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=+1234567890
```

### Mode Production avec Vonage (ex-Nexmo)
```env
SMS_DEFAULT_GATEWAY=vonage
VONAGE_API_KEY=your_vonage_key
VONAGE_API_SECRET=your_vonage_secret
VONAGE_SENDER=YourApp
```

### Fallback automatique
```env
SMS_FALLBACK_STRATEGY=try_all
# ou fail_fast pour arrêter au premier échec
```

## Utilisation

Le système enverra automatiquement un SMS avec le code membre lors de l'ajout d'un nouveau membre.

**Message envoyé :**
"Bonjour [Nom], votre code membre est: [12345678]. Conservez-le précieusement."

## Logs

En mode développement, les SMS sont loggés dans `storage/logs/laravel.log`
En mode production, les SMS sont envoyés via l'API configurée avec fallback automatique.