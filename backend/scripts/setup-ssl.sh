#!/bin/bash
# Script d'installation et configuration SSL avec Certbot (Let's Encrypt)
# Pour l'application de vérification de présence UAC

set -e

DOMAIN_FRONTEND="presence.uac.bj"
DOMAIN_API="api.presence.uac.bj"
EMAIL="admin@uac.bj"  # Remplacez par votre email

echo "=== Installation Certbot ==="
apt-get update
apt-get install -y certbot python3-certbot-nginx

echo "=== Arrêt de Nginx pour le challenge standalone ==="
systemctl stop nginx

echo "=== Obtention des certificats SSL ==="
# Mode standalone (nécessite port 80 libre)
certbot certonly --standalone \
    --non-interactive \
    --agree-tos \
    --email "$EMAIL" \
    -d "$DOMAIN_FRONTEND" \
    -d "$DOMAIN_API" \
    --preferred-challenges http

echo "=== Redémarrage de Nginx ==="
systemctl start nginx

echo "=== Configuration du renouvellement automatique ==="
# Le timer systemd certbot.timer gère déjà le renouvellement
systemctl enable certbot.timer
systemctl start certbot.timer

# Test du renouvellement (dry-run)
certbot renew --dry-run

echo "=== Configuration Nginx avec les certificats ==="
# Copier la config nginx
cp /home/prince-kangbode/Mes_projets/projet_verification_de_presence/backend/nginx/presence.conf /etc/nginx/sites-available/presence

# Activer le site
ln -sf /etc/nginx/sites-available/presence /etc/nginx/sites-enabled/presence

# Supprimer le site par défaut
rm -f /etc/nginx/sites-enabled/default

# Tester la config
nginx -t

# Recharger nginx
systemctl reload nginx

echo "=== Création du hook de renouvellement pour recharger Nginx ==="
cat > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh << 'EOF'
#!/bin/bash
systemctl reload nginx
EOF

chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh

echo "=== Vérification SSL ==="
openssl x509 -in /etc/letsencrypt/live/$DOMAIN_FRONTEND/fullchain.pem -text -noout | grep -E "(Subject:|Issuer:|Not After:)"

echo "=== Terminé ! ==="
echo "Frontend: https://$DOMAIN_FRONTEND"
echo "API: https://$DOMAIN_API"
echo ""
echo "Pour tester le renouvellement : certbot renew --dry-run"
echo "Logs certbot : /var/log/letsencrypt/letsencrypt.log"