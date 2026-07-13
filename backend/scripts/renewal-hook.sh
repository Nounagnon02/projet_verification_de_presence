#!/bin/bash
# Hook de déploiement post-renouvellement Certbot
# Recharge Nginx après le renouvellement des certificats

systemctl reload nginx

# Optionnel : notifier une webhook (Slack, Discord, etc.)
# curl -X POST "https://hooks.slack.com/services/XXX" -d '{"text":"Certificats SSL renouvelés pour presence.uac.bj"}'

exit 0