#!/bin/bash
set -e

echo "╔══════════════════════════════════════════════════╗"
echo "║   PRÉSENCE UAC — Tests Automatisés              ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

# ─── Fonctions ────────────────────────────────────────────

usage() {
  echo "Usage: $0 [environnement] [suite]"
  echo ""
  echo "Environnements :"
  echo "  prod       Production (Render + Vercel)  [défaut]"
  echo "  local      Localhost (php artisan serve + vite)"
  echo "  all        Production + Local"
  echo ""
  echo "Suites :"
  echo "  health     Tests de santé API"
  echo "  auth       Tests d'authentification"
  echo "  api        Tous les tests API"
  echo "  front      Tests frontend (navigateur)"
  echo "  full       Tous les tests [défaut]"
  echo ""
  echo "Exemples :"
  echo "  $0 prod health       # Santé de la prod"
  echo "  $0 local auth        # Auth en local"
  echo "  $0 all               # Tout partout"
  echo ""
  exit 1
}

# ─── Parsing des arguments ───────────────────────────────

ENV="${1:-prod}"
SUITE="${2:-full}"

case "$ENV" in
  prod|production)
    PROJECT="api-production"
    [ "$SUITE" = "front" ] || [ "$SUITE" = "full" ] && FRONT_PROJECT="frontend-production"
    ENV_LABEL="PRODUCTION"
    ;;
  local|dev)
    PROJECT="api-local"
    [ "$SUITE" = "front" ] || [ "$SUITE" = "full" ] && FRONT_PROJECT="frontend-local"
    ENV_LABEL="LOCAL"
    ;;
  all)
    PROJECT="api-production"
    FRONT_PROJECT="frontend-production"
    LOCAL_PROJECT="api-local"
    LOCAL_FRONT_PROJECT="frontend-local"
    ENV_LABEL="ALL"
    ;;
  *)
    usage
    ;;
esac

# ─── Exécution ────────────────────────────────────────────

run_tests() {
  local project="$1"
  local label="$2"
  local grep_pattern="$3"

  echo -e "\n${BLUE}═══════════════════════════════════════${NC}"
  echo -e "${BLUE}  Tests $label${NC}"
  echo -e "${BLUE}═══════════════════════════════════════${NC}\n"

  local cmd="npx playwright test --project=$project --reporter=list"
  [ -n "$grep_pattern" ] && cmd="$cmd --grep=\"$grep_pattern\""

  if eval "$cmd"; then
    echo -e "\n${GREEN}✅ $label : TOUS LES TESTS PASSENT${NC}\n"
  else
    echo -e "\n${RED}❌ $label : CERTAINS TESTS ONT ÉCHOUÉ${NC}\n"
    return 1
  fi
}

FAILURES=0

case "$SUITE" in
  health)
    run_tests "$PROJECT" "Santé API ($ENV_LABEL)" "health"
    ;;
  auth)
    run_tests "$PROJECT" "Auth ($ENV_LABEL)" "Authentification"
    ;;
  api)
    run_tests "$PROJECT" "API ($ENV_LABEL)"
    ;;
  front)
    if [ -n "$FRONT_PROJECT" ]; then
      run_tests "$FRONT_PROJECT" "Frontend ($ENV_LABEL)"
    else
      echo -e "${YELLOW}Frontend non disponible pour cet environnement${NC}"
    fi
    ;;
  full)
    run_tests "$PROJECT" "API ($ENV_LABEL)" || FAILURES=$((FAILURES + 1))
    if [ -n "$FRONT_PROJECT" ]; then
      run_tests "$FRONT_PROJECT" "Frontend ($ENV_LABEL)" || FAILURES=$((FAILURES + 1))
    fi
    if [ -n "$LOCAL_PROJECT" ]; then
      run_tests "$LOCAL_PROJECT" "API (LOCAL)" || FAILURES=$((FAILURES + 1))
    fi
    if [ -n "$LOCAL_FRONT_PROJECT" ]; then
      run_tests "$LOCAL_FRONT_PROJECT" "Frontend (LOCAL)" || FAILURES=$((FAILURES + 1))
    fi
    ;;
  *)
    usage
    ;;
esac

# ─── Rapport final ─────────────────────────────────────────

echo ""
echo "╔══════════════════════════════════════════════════╗"
if [ $FAILURES -eq 0 ]; then
  echo -e "║  ${GREEN}✅ TOUS LES TESTS SONT PASSÉS${NC}            ║"
else
  echo -e "║  ${RED}❌ $FAILURES SUITE(S) EN ÉCHEC${NC}                ║"
fi
echo "╚══════════════════════════════════════════════════╝"

exit $FAILURES
