
#!/usr/bin/env bash
set -euo pipefail

ROOT="/opt/GroupeC"
KEYCLOAK_DIR="$ROOT/keycloak"
SECRETS_DIR="$ROOT/secrets"

echo "[1/5] Verification des prerequis"

command -v docker >/dev/null || {
  echo "ERREUR: Docker absent"
  exit 1
}

for network in GroupeC-applicatif GroupeC-donnees; do
  docker network inspect "$network" >/dev/null || {
    echo "ERREUR: Reseau absent: $network"
    exit 1
  }
done

echo "[2/5] Verification des secrets"
# Générer un secret administrateur uniquement s'il n'existe pas
if [ ! -s "$SECRETS_DIR/keycloak_admin_password" ]; then
  (
    umask 077
    openssl rand -hex 24 > "$SECRETS_DIR/keycloak_admin_password"
  )
  echo "Secret administrateur Keycloak genere."
fi
for secret in keycloak_db_password keycloak_admin_password; do
  if [ ! -s "$SECRETS_DIR/$secret" ]; then
    echo "ERREUR: Secret absent: $secret"
    exit 1
  fi
done

echo "[3/5] Chargement des variables"

export KC_DB_PASSWORD
export KC_BOOTSTRAP_ADMIN_PASSWORD

KC_DB_PASSWORD="$(cat "$SECRETS_DIR/keycloak_db_password")"
KC_BOOTSTRAP_ADMIN_PASSWORD="$(cat "$SECRETS_DIR/keycloak_admin_password")"

# Installation de la route HTTPS Keycloak
echo "Installation de la route Traefik Keycloak"

ROUTE_SOURCE="$KEYCLOAK_DIR/keycloak-route.yaml"
ROUTE_DEST="$ROOT/traefik/dynamic/keycloak-route.yaml"

if [ ! -f "$ROUTE_SOURCE" ]; then
  echo "ERREUR: Fichier route Keycloak introuvable"
  exit 1
fi

# Copier la route sans remplacer les autres configurations
install -m 0644 "$ROUTE_SOURCE" "$ROUTE_DEST"

echo "Route HTTPS Keycloak installee."

echo "[4/5] Validation et deploiement"

cd "$KEYCLOAK_DIR"

docker compose config --quiet
docker compose up -d

unset KC_DB_PASSWORD
unset KC_BOOTSTRAP_ADMIN_PASSWORD

echo "[5/5] Verification du conteneur"

docker compose ps

echo "Deploiement Keycloak termine."
