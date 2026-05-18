# Variables d'environnement Render.com pour Turso

## Variables à configurer dans Render.com Dashboard :

### Application
- `APP_NAME` = "Vérification de Présence"
- `APP_ENV` = production
- `APP_KEY` = base64:tuvn9jjJZcGM4RX1Q0xo4tGSq9193+fQkCobHyyYOJ4=
- `APP_DEBUG` = false
- `APP_URL` = https://projet-verification-de-presence-3.onrender.com

### Base de données Turso
- `DB_CONNECTION` = turso
- `TURSO_DATABASE_URL` = libsql://presenceverif-nounagnon.aws-eu-west-1.turso.io
- `TURSO_AUTH_TOKEN` = eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.eyJhIjoicnciLCJpYXQiOjE3NjM2MzQ4NjMsImlkIjoiMzY5NmY4NDgtMjQzYi00NzExLTgzMTgtODU1N2ZmMzczYzhhIiwicmlkIjoiZjQ4OTQ1ZDYtMzViZi00N2Y4LThjYTMtYWMyOTY2MGQ0NTc2In0.AUBRMUtW_lOMcKt6WmRtpQQ2yYkIaHkwW69kuPuoL9lHPdUOx9obswjIBSBIMRFv6XdH4uCctvLGfLN4lBOsCQ

### Mail
- `MAIL_MAILER` = log
- `MAIL_FROM_ADDRESS` = noreply@verification-presence.com

### Session & Cache
- `SESSION_DRIVER` = database
- `CACHE_STORE` = database
- `QUEUE_CONNECTION` = sync

### Autres
- `APP_LOCALE` = fr
- `LOG_LEVEL` = error
