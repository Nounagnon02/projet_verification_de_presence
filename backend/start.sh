#!/bin/bash
set -e
cd /var/www/html

# Laravel lit les variables d'environnement directement depuis le container Render.
# On crée un .env minimal — les vraies valeurs viennent des env vars Render.
cat > .env << 'ENVEOF'
APP_NAME="Présence UAC"
APP_ENV=production
APP_DEBUG=false
ENVEOF

# Optimisations Laravel
php artisan config:clear
php artisan migrate --force --no-interaction

# Données essentielles uniquement (évite le timeout avec les grosses données de démo)
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=AnneeAcademiqueSeeder --force
php artisan db:seed --class=FiliereSeeder --force
php artisan db:seed --class=FiliereAnneeSeeder --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Le plan gratuit de Render n'offre ni worker ni cron : sans ce processus, la
# file `jobs` (e-mails d'identifiants, import IA) n'est jamais consommée et le
# planificateur (QR codes, clôture des séances) ne tourne pas. `schedule:run`
# exécute les tâches de routes/console.php, dont le drainage de la file.
# Lancé en www-data pour ne pas créer de fichiers root dans storage/.
#
# Activé par ENABLE_SCHEDULER=true, une fois le mailer opérationnel : le job
# d'e-mail journalise ses erreurs au lieu de les relancer, donc drainer la file
# avec un mailer cassé détruirait les e-mails en attente.
if [ "${ENABLE_SCHEDULER:-false}" = "true" ]; then
    (
        while true; do
            runuser -u www-data -- php artisan schedule:run >> /dev/null 2>&1 || true
            sleep 60
        done
    ) &
fi

echo "✓ Application ready!"
apache2-foreground
