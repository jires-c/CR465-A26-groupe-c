#!/bin/bash
set -euo pipefail

cd /opt/GroupeC/moodle

export MOODLE_DB_PASSWORD="$(cat /opt/GroupeC/secrets/moodle_db_password)"

docker compose \
  --env-file .env \
  -f docker-compose-groupec.yml \
  up -d

unset MOODLE_DB_PASSWORD
