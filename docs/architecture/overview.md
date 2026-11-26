# Architecture

## Réseaux Docker (créés par deploy.sh)

| Réseau | Portée | Rôle |
|---|---|---|
| `GroupeC-exposition` | bridge | Frontal / entrée |
| `GroupeC-applicatif` | bridge, 172.28.240.0/24 | Traefik ↔ applications (IP Traefik fixe .10) |
| `GroupeC-donnees` | bridge `--internal` | Applications ↔ PostgreSQL |
| `GroupeC-observabilite` | bridge `--internal` | Grafana / Loki / Alloy |

## Services et exposition

Tout passe par Traefik en HTTPS sur le port **4443** ; aucun service ne publie
de port direct sur l'hôte (sauf Traefik). Entrée `web:80` redirigée vers 4443.

| Service | Image | Réseaux | Accès |
|---|---|---|---|
| Traefik | traefik:v3.5 | exposition, applicatif | `:4443`, dashboard BasicAuth |
| PostgreSQL 17 | postgres:17 | donnees | interne, 5 bases/5 rôles |
| Keycloak | keycloak:26.8.2 | applicatif, donnees | `keycloak.groupec.test:4443` |
| Moodle 5.3 | erseco/alpine-moodle | applicatif, donnees | `moodle.groupec.test:4443` |
| Drupal 11 + Commerce | build local PHP 8.3 | applicatif, donnees | `drupal.groupec.test:4443/formations` |
| n8n 2.17 | n8nio/n8n | applicatif, donnees | `n8n.groupec.test:4443` |
| Grafana / Loki / Alloy | 12.3 / 3.5.5 / 1.10.2 | applicatif, observabilite | `grafana.groupec.test:4443` |
| Portainer CE | 2.45.3 | applicatif | `portainer.groupec.test:4443` |

## Flux d'achat Drupal → n8n → Moodle

1. Achat d'une formation sur Drupal Commerce (`/formations`), paiement **simulé**.
2. À la commande payée, le module `groupec_n8n` POST le webhook n8n
   (`/webhook/commande-payee`), authentifié par l'en-tête `X-GroupeC-Token`.
3. Le workflow `GroupeCMoodle001` cherche l'étudiant (par e-mail) et le cours
   (par SKU) dans Moodle, puis l'inscrit via `enrol_manual_enrol_users`.
4. Codes de retour : 403 (token ou événement), 422 (étudiant ou cours absent),
   502 (erreur API Moodle), 200 (inscription transmise).

Le détail d'intégration est dans `README-INTEGRATION.txt`.
