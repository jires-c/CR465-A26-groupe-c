# 8. Grille d'évaluation

Voici comment votre travail sera évalué. Ce tableau est un résumé condensé — pour la répartition détaillée par critère (barèmes Excellent / Satisfaisant / Fragile / Insuffisant), consultez `md/grille_correction_travail_session_containers.md`, qui fait foi en cas de divergence.

| Critère | Ce qui est évalué | Points |
|---|---|---:|
| VM as Code avec cloud-init | Présence d'un vrai fichier cloud-init, utilité réelle, automatisation de l'utilisateur, SSH, mises à jour, Docker, pare-feu hôte, préparation de l'hôte | 5 |
| Architecture et schéma | Lisibilité, cohérence, zones de confiance, flux, exposition, parcours métier | 5 |
| Déploiement conteneurisé | Qualité de la stack Docker Compose, structure, dépendances, reproductibilité (bonus qualitatif si Opencast ou Redis intégrés) | 3 |
| Sécurité des images | Versions explicites, choix d'images, scan local/hors-ligne (ex. Trivy, Grype) sans dépendance à un service cloud, provenance | 3 |
| Sécurité à l'exécution | Non-root, absence de mode privilégié, réduction des privilèges, options de sécurité, montages prudents, ressources | 5 |
| Segmentation réseau | Réseaux d'exposition, applicatifs, données, exposition minimale, flux justifiés | 3 |
| Intégration Drupal Commerce → n8n → Moodle | Cohérence du parcours métier, vente, automatisation, attribution d'accès, démonstration | 3 |
| IAM et SSO | Keycloak, SSO Moodle, compréhension des flux d'authentification | 3 |
| Reverse proxy | Rôle de Traefik, centralisation de l'exposition | 3 |
| Observabilité et exploitation | Usage de Portainer CE, états des conteneurs, journaux, diagnostic, éventuellement Grafana/Loki | 3 |
| Qualité des livrables | Vidéo M365 conforme, GitBook, GitHub, document Word/PPT, cohérence globale, traçabilité des contributions individuelles | 4 |
| **Total** |  | **40** |
| **Bonus — WAF (OpenAppSec)** | Ajout d'un WAF fonctionnel en frontal de Traefik, configuré en mode local et expliqué — voir [6. Bonus — WAF](06-bonus-waf-openappsec.md) | **+2 (hors total)** |

## Attentes par critère

### VM as Code

Le fichier cloud-init doit faire plus qu'installer quelques paquets. Il doit réellement illustrer une approche déclarative d'initialisation de la VM et éviter un maximum d'étapes manuelles répétitives.

### Architecture et schéma

Le schéma doit être lisible, légendé et cohérent avec ce qui est réellement déployé. Un schéma très beau mais non conforme au déploiement réel sera pénalisé.

### Déploiement conteneurisé

La stack doit être stable, compréhensible et assez mature pour être démontrée. La reproductibilité comptera davantage que la sophistication.

### Sécurité des images et à l'exécution

Les protections doivent être visibles dans le code, dans la configuration ou dans la démonstration. Il ne suffit pas d'écrire que le projet est « sécurisé ».

### Intégration fonctionnelle

Le scénario attendu doit démontrer un enchaînement réaliste entre la commande de formation dans Drupal Commerce, le workflow n8n et l'attribution d'un accès exploitable dans Moodle. La solution peut être partiellement simulée, mais elle doit être cohérente et démontrable.

### Observabilité

Portainer CE doit servir à visualiser l'environnement. Si vous ajoutez Loki, Grafana ou un autre mécanisme léger de journalisation, vous devez montrer en quoi cela vous aide à comprendre un comportement normal ou anormal, y compris dans le flux Drupal Commerce → n8n → Moodle.

## Pénalités majeures

Certaines situations (secrets committés, conteneurs privilégiés, services sensibles exposés publiquement, etc.) entraînent des pénalités importantes — voir [9. Pénalités majeures](09-penalites-majeures.md) pour la liste complète.
