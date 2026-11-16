#!/bin/bash
set -euo pipefail
# Accéder au répertoire Moodle de l'infrastructure GroupeC
cd /opt/GroupeC/moodle
# Récupérer le mot de passe PostgreSQL généré par GroupeC
# Le mot de passe ne doit jamais être publié sur GitHub
export MOODLE_DB_PASSWORD="$(cat /opt/GroupeC/secrets/moodle_db_password)"
# Démarrer Moodle avec les variables d'environnement privées
docker compose \
  --env-file .env \
  -f docker-compose-groupec.yml \
  up -d
# Supprimer la variable du shell après le démarrage
unset MOODLE_DB_PASSWORD
