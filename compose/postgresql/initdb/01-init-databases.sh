#!/usr/bin/env bash
# EXPLICATION : Arrete le script d initialisation si une commande, variable ou pipeline echoue.
set -euo pipefail

# Lance par le point d'entree de postgres:17 UNIQUEMENT sur volume vide.
# Secrets generes sur la VM et montes dans /run/secrets; pas dans GitHub.
# Noms de bases/roles fixes et mots de passe aleatoires hexadecimaux.
# EXPLICATION : Repete la preparation pour 5 applications (Opencast optionnel inclus).
for app in moodle keycloak drupal n8n opencast; do
  # EXPLICATION : Lit le secret de l application dans le conteneur. La variable Bash ne sera pas imprimee.
  password="$(cat "/run/secrets/${app}_db_password")"
  # EXPLICATION : Controle que le secret est une chaine hexadecimale de 64 caracteres (32 octets aleatoires).
  [[ "$password" =~ ^[0-9a-f]{64}$ ]] || { echo "Mot de passe invalide pour $app" >&2; exit 1; }

  # \gexec execute les instructions SQL produites par SELECT sans
  # ajouter de bloc DO ni de transaction autour de CREATE DATABASE.
  # EXPLICATION : Ouvre psql; -X ignore les reglages utilisateur, -q est discret, ON_ERROR_STOP arrete sur SQL invalide. <<SQL commence le SQL.
  psql -X -q -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<SQL
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', '${app}', '${password}')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='${app}')
\gexec
SELECT format('CREATE DATABASE %I OWNER %I', '${app}', '${app}')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname='${app}')
\gexec
SQL
  # Isolation: aucun role applicatif ne peut se connecter aux AUTRES BDs.
  # Par defaut, PostgreSQL accorde CONNECT au role PUBLIC ; on le retire.
  psql -X -q -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
    -c "REVOKE CONNECT ON DATABASE \"${app}\" FROM PUBLIC; GRANT CONNECT ON DATABASE \"${app}\" TO \"${app}\";"
  # Le schema public ne doit pas etre accessible librement aux autres roles.
  psql -X -q -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$app" \
    -c "REVOKE ALL ON SCHEMA public FROM PUBLIC; GRANT USAGE, CREATE ON SCHEMA public TO \"${app}\";"

  # EXPLICATION : Efface la variable temporaire de mot de passe apres creation du role.
  unset password
  # EXPLICATION : Affiche seulement le nom de l application dans les journaux, jamais son mot de passe.
  echo "Base et role prets: ${app}"
done
