# Chaine de tests backend — SGP-UAC
#
# La suite exige un Postgres jetable sur 127.0.0.1:55433 (voir .env.testing).
# Sans lui, les 271 tests echouent sur « connection refused », ce qui se lit
# comme une regression alors que seule la base manque. Ces cibles rendent ce
# prerequis explicite et reproductible.

PG_CONTENEUR := uac-test-pg
PG_PORT      := 55433
PG_BASE      := presence_uac_test
PG_USER      := postgres
PG_PASS      := postgres
PG_IMAGE     := postgres:15-alpine

.PHONY: aide db-up db-down db-reset db-logs test test-couverture migrer fraiche lint

aide:
	@echo "Cibles disponibles :"
	@echo "  make db-up            Demarre (ou cree) le Postgres de test sur :$(PG_PORT)"
	@echo "  make db-down          Arrete le conteneur, sans le supprimer"
	@echo "  make db-reset         Supprime le conteneur et le recree vide"
	@echo "  make db-logs          Affiche les journaux du conteneur"
	@echo "  make test             db-up puis php artisan test"
	@echo "  make test-couverture  Idem avec rapport de couverture (exige PCOV ou Xdebug)"
	@echo "  make migrer           Applique les migrations sur la base de test"
	@echo "  make lint             Verifie la syntaxe de tous les fichiers PHP de app/"

## Demarre le conteneur s'il existe, le cree sinon, puis attend qu'il reponde.
db-up:
	@if [ -z "$$(docker ps -q -f name=^$(PG_CONTENEUR)$$)" ]; then \
		if [ -n "$$(docker ps -aq -f name=^$(PG_CONTENEUR)$$)" ]; then \
			echo "→ Redemarrage de $(PG_CONTENEUR)"; \
			docker start $(PG_CONTENEUR) >/dev/null; \
		else \
			echo "→ Creation de $(PG_CONTENEUR)"; \
			docker run -d --name $(PG_CONTENEUR) \
				-p $(PG_PORT):5432 \
				-e POSTGRES_PASSWORD=$(PG_PASS) \
				-e POSTGRES_DB=$(PG_BASE) \
				$(PG_IMAGE) >/dev/null; \
		fi; \
	else \
		echo "→ $(PG_CONTENEUR) tourne deja"; \
	fi
	@printf "→ Attente de Postgres"
	@for i in $$(seq 1 30); do \
		if docker exec $(PG_CONTENEUR) pg_isready -U $(PG_USER) -q 2>/dev/null; then \
			echo " — pret"; exit 0; \
		fi; \
		printf "."; sleep 1; \
	done; \
	echo " — DELAI DEPASSE"; docker logs --tail 20 $(PG_CONTENEUR); exit 1

db-down:
	@docker stop $(PG_CONTENEUR) >/dev/null 2>&1 && echo "→ $(PG_CONTENEUR) arrete" || echo "→ deja arrete"

## Base vierge. Supprime aussi les verrous de migration, indexes sur l'empreinte
## du repertoire de migrations (voir tests/TestCase.php).
db-reset:
	@docker rm -f $(PG_CONTENEUR) >/dev/null 2>&1 || true
	@rm -f /tmp/uac_migrations_*.lock
	@$(MAKE) --no-print-directory db-up

db-logs:
	@docker logs --tail 50 $(PG_CONTENEUR)

migrer: db-up
	@php artisan migrate --force

test: db-up
	@php artisan test

## Exige un pilote de couverture. Sans PCOV ni Xdebug, PHPUnit signale
## « No code coverage driver available » et la mesure est impossible.
test-couverture: db-up
	@php -m | grep -qiE 'pcov|xdebug' || { \
		echo "Aucun pilote de couverture. Installer PCOV :"; \
		echo "  sudo pecl install pcov && sudo phpenmod pcov"; \
		echo "ou Xdebug :"; \
		echo "  sudo pecl install xdebug"; \
		exit 1; \
	}
	@php artisan test --coverage --min=70

## Une classe qui ne compile pas ne fait pas echouer « artisan list » : elle est
## simplement absente du registre. D'ou ce lint explicite.
lint:
	@erreurs=0; \
	for f in $$(find app database routes tests -name '*.php'); do \
		php -l "$$f" >/dev/null 2>&1 || { echo "ERREUR DE SYNTAXE : $$f"; erreurs=1; }; \
	done; \
	[ $$erreurs -eq 0 ] && echo "→ Syntaxe PHP : aucune erreur"
