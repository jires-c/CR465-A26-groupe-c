# 4. Architecture cible

Votre architecture devra suivre un modèle semblable au suivant :

```mermaid
flowchart TD
    Internet["Utilisateurs / Internet"] -->|"HTTP/HTTPS"| Proxy["Traefik (Reverse Proxy)"]
    Internet -.->|"bonus"| WAF["OpenAppSec (WAF, mode local)"]
    WAF -.->|"bonus"| Proxy

    Proxy --> Drupal["Drupal Commerce"]
    Proxy --> Moodle["Moodle"]
    Proxy --> Keycloak["Keycloak"]
    Proxy --> N8N["n8n"]
    Proxy --> Portainer["Portainer CE"]
    Proxy -.->|"optionnel/bonus"| Grafana["Grafana + Loki"]

    Drupal --> N8N
    N8N --> Moodle
    Moodle -.->|"optionnel, souhaité"| Opencast["Opencast"]
    N8N -.->|"bonus"| Redis["Redis"]

    Drupal --> Postgres["PostgreSQL"]
    Moodle --> Postgres
    Keycloak --> Postgres
    N8N --> Postgres

    classDef optional stroke-dasharray:5,5;
    class Opencast,Redis,Grafana,WAF optional;
```

Parcours métier : vente sur Drupal Commerce → workflow n8n → accès Moodle. Les nœuds en pointillé (WAF, Opencast, Redis, Grafana/Loki) sont optionnels ou bonus.

> Le WAF (OpenAppSec) apparaît en pointillé : il s'agit d'un composant **bonus**, non requis pour exposer Traefik. Voir [6. Bonus — WAF](06-bonus-waf-openappsec.md) pour les règles complètes.

Ce schéma n'est pas figé. Vous pouvez l'ajuster si vous justifiez clairement vos choix. Toutefois, les principes suivants doivent être respectés :

- les services de données ne doivent pas être exposés directement à Internet;
- les points d'entrée doivent être limités et centralisés;
- les réseaux internes doivent être segmentés;
- les flux d'authentification doivent être documentés;
- le flux d'attribution d'accès après commande doit être documenté;
- l'architecture doit illustrer une logique de défense en profondeur.
