# Architecture globale

## Vue d’ensemble
Une VM Ubuntu 24.04 LTS héberge les services Docker Rootless. Traefik centralise les accès HTTPS ; PostgreSQL 17 héberge les bases applicatives distinctes. Quatre réseaux séparent exposition, applications, données et observabilité.

| Couche | Composants |
|---|---|
| Infrastructure | Cloud-init, Docker Rootless, Docker Compose |
| Exposition | Traefik |
| Applications | Moodle, Keycloak, Drupal Commerce, n8n |
| Données | PostgreSQL |
| Administration | Portainer |
| Observabilité | Alloy, Loki, Grafana |

```mermaid
graph TD
 U[Utilisateurs] -->|HTTPS 4443| T[Traefik]
 T --> M[Moodle]
 T --> K[Keycloak]
 T --> D[Drupal Commerce]
 T --> N[n8n]
 T --> P[Portainer]
 T --> G[Grafana]
 M -->|OIDC| K
 D -->|Webhook| N
 N -->|API| M
 M --> PG[PostgreSQL]
 K --> PG
 D --> PG
 N --> PG
 A[Alloy] --> L[Loki]
 G --> L
```

Schéma logique simplifié ; les appartenances réseau sont détaillées dans le chapitre dédié. WEBTEST est omis pour la lisibilité.

[Retour à la documentation](../README.md)
