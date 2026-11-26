CR465 Groupe C - INDEX D'INTEGRATION V5.4
=========================================
Ce fichier est un index. Voir README-FINAL-CR465.txt et README-V5-INTEGRATION.txt.

ACTIFS EN CLOUD-INIT : Docker Rootless, Traefik, PostgreSQL, Portainer,
Grafana/Loki/Alloy, Moodle, Keycloak, Drupal Commerce, n8n, Nginx WEBTEST.
SEULEMENT PREPARES OU COMMENTES : application Opencast, Redis, OpenAppSec WAF.

REPERTOIRES :
/opt/GroupeC/drupal/docker-compose.yml
/opt/GroupeC/n8n/docker-compose.yml
/opt/GroupeC/moodle/docker-compose.yml
/opt/GroupeC/keycloak/docker-compose.yml
/opt/GroupeC/traefik/docker-compose.yml
/opt/GroupeC/postgresql/docker-compose.yml
/opt/GroupeC/observabilite/{grafana,loki,alloy}/docker-compose.yml

AUCUN fichier de secret ne doit etre mis sur GitHub.
Le premier login Keycloak d'etudiant-test est corrige par le plugin
local_groupec_ssofix (ne modifie pas les comptes hors demonstration).
