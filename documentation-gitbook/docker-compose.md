# Déploiement Docker Compose

Chaque service possède une configuration Compose ; le script `/opt/GroupeC/deploy.sh` orchestre le démarrage et les dépendances. L’image Drupal est construite localement.

| Service | Version |
|---|---|
| Traefik | 3.5 |
| PostgreSQL | 17 |
| Moodle | 5.3.0 |
| Keycloak | 26.8.2 |
| Drupal Commerce | Drupal 11, image personnalisée |
| n8n | 2.17.0 |
| Portainer CE | 2.45.3 |
| Grafana | 12.3.0 |
| Loki | 3.5.5 |
| Alloy | 1.10.2 |
| WEBTEST | Nginx 1.28-alpine (temporaire) |

## Données
Une seule instance PostgreSQL héberge cinq bases et rôles distincts : `moodle`, `keycloak`, `drupal`, `n8n`, `opencast`. La base Opencast est réservée ; l’application, Redis et OpenAppSec ne sont pas déployés.

## Exploitation
```bash
docker ps
cd /opt/GroupeC/keycloak
docker compose config --quiet
docker compose ps
docker compose logs --tail=100 keycloak
```
Les commandes Docker sont exécutées sans sudo sous l’utilisateur configuré pour Rootless.

[Retour à la documentation](../README.md)
