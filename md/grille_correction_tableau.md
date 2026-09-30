# Grille de correction — format tableau

> Résumé condensé dérivé de `grille_correction_travail_session_containers.md`. En cas de divergence, le fichier détaillé fait foi.

| Critère | Ce qui est évalué | Excellent | Satisfaisant | Fragile | Insuffisant | Points |
|---|---|---|---|---|---|---:|
| VM as Code avec cloud-init | Présence d’un vrai fichier cloud-init, utilité réelle, automatisation de l’utilisateur, SSH, mises à jour, Docker, pare-feu hôte, préparation de l’hôte | Cloud-init complet, clair, reproductible, réellement utile au déploiement | Cloud-init pertinent mais partiellement incomplet ou peu expliqué | Cloud-init minimal, partiel ou surtout décoratif | Absence de vrai cloud-init ou usage non démontré | 5 |
| Architecture et schéma | Lisibilité, cohérence, zones de confiance, flux, exposition, parcours métier | Schéma clair, complet, juste et aligné avec le déploiement réel | Schéma généralement bon, avec quelques oublis mineurs | Schéma partiel, ambigu ou difficile à relier à la solution | Schéma absent ou incorrect | 5 |
| Déploiement conteneurisé | Qualité de la stack Docker Compose, structure, dépendances, reproductibilité (bonus si Opencast ou Redis intégrés) | Stack fonctionnelle, propre, stable et bien structurée | Stack presque complète avec quelques imprécisions mineures | Stack partiellement fonctionnelle ou difficile à reproduire | Solution non déployable ou incohérente | 3 |
| Sécurité des images | Versions explicites, choix d’images, scan local/hors-ligne (ex. Trivy, Grype) sans dépendance à un service cloud, provenance | Images bien choisies, versions explicites, scan ou justification convaincante | Bonnes pratiques visibles mais démonstration incomplète | Peu d’éléments de sécurité ou choix peu justifiés | Aucune preuve sérieuse de réflexion sur les images | 3 |
| Sécurité à l’exécution | Non-root, absence de mode privilégié, réduction des privilèges, options de sécurité, montages prudents, ressources | Bonnes pratiques visibles, expliquées et majoritairement appliquées | Plusieurs contrôles corrects, quelques écarts justifiés | Sécurité partielle ou surtout déclarative | Presque aucun durcissement réel | 5 |
| Segmentation réseau | Réseaux d’exposition, applicatifs, données, exposition minimale, flux justifiés | Segmentation claire, bien pensée et bien expliquée | Segmentation présente mais perfectible | Segmentation minimale ou peu cohérente | Réseau plat ou services sensibles exposés inutilement | 3 |
| Intégration Drupal Commerce → n8n → Moodle | Cohérence du parcours métier, vente, automatisation, attribution d’accès, démonstration | Flux cohérent, démontré, compréhensible et bien documenté | Flux présent mais partiellement simulé ou incomplet | Logique annoncée mais peu démontrée | Aucune intégration crédible entre les composants | 3 |
| IAM et SSO | Keycloak, SSO Moodle, compréhension des flux d’authentification | SSO fonctionnel, bien démontré et bien expliqué | SSO partiellement fonctionnel ou démonstration incomplète | Configuration présente mais peu convaincante | Absence de mise en œuvre réelle | 3 |
| Reverse proxy | Rôle de Traefik, centralisation de l’exposition | Proxy intégré et correctement expliqué | Présence partielle ou démonstration limitée | Compréhension faible, intégration incomplète | Absence ou incompréhension manifeste | 3 |
| Observabilité et exploitation | Usage de Portainer CE, états des conteneurs, journaux, diagnostic, éventuellement Grafana/Loki | Portainer CE opérationnel et utilisé intelligemment; diagnostic crédible montré | Portainer présent et exploité partiellement | Portainer peu exploité ou démonstration faible | Aucun usage pédagogique réel de l’observabilité | 3 |
| Qualité des livrables | Vidéo M365 conforme, GitBook, GitHub, document Word/PPT, cohérence globale, traçabilité des contributions individuelles | Tous les livrables sont complets, cohérents, clairs et professionnels | Très bons livrables avec quelques défauts mineurs | Livrables présents mais inégaux ou difficiles à exploiter | Plusieurs livrables absents, non accessibles ou de faible qualité | 4 |
| **Total** |  |  |  |  |  | **40** |
| Bonus — WAF (OpenAppSec) | Rôle d’OpenAppSec en mode local (sans compte cloud tiers), optionnel et non requis | WAF fonctionnel, en mode local, bien intégré et expliqué (+2) | WAF présent mais partiel ou peu expliqué (+1) | — | Absent ou en mode cloud, aucun bonus (0) | **+2 (hors total)** |

## Vérifications éliminatoires ou pénalités majeures

| Situation observée | Effet recommandé sur la correction |
|---|---|
| Secrets réels dans GitHub | -3 points sur Sécurité des images et -2 points sur Qualité des livrables |
| Conteneur privilégié sans justification sérieuse | -3 points sur Sécurité à l’exécution |
| PostgreSQL, Redis, n8n ou interface d’administration sensible exposés publiquement | -3 points sur Segmentation réseau et -2 points sur Architecture et schéma |
| Absence réelle de cloud-init | 0 à 1 point sur VM as Code (voir répartition détaillée) |
| Vidéo au-delà de 15 min 59 s | -2 points sur Qualité des livrables |
| Dépôt GitHub inutilisable | -3 points sur Déploiement conteneurisé et -2 points sur Qualité des livrables |
| Contributions individuelles non identifiables | -1 à -2 points sur Qualité des livrables |

Le WAF (OpenAppSec) est un bonus optionnel : son absence n’entraîne aucune pénalité. Une équipe qui le configure en mode cloud ne reçoit simplement pas le bonus de +2 points, sans impact sur les autres critères.

## Feuille de pointage par équipe

| Équipe | VM as Code /5 | Architecture /5 | Déploiement /3 | Images /3 | Exécution /5 | Réseaux /3 | Intégration /3 | IAM/SSO /3 | Reverse Proxy /3 | Observabilité /3 | Livrables /4 | Total /40 | Bonus WAF /2 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
|  |  |  |  |  |  |  |  |  |  |  |  |  |  |

## Références de correction

Les critères de sécurité à l’exécution et de moindre privilège s’alignent avec les recommandations OWASP sur les conteneurs, qui insistent notamment sur l’exécution sans privilèges élevés et la réduction de surface d’attaque.

L’usage de Portainer CE comme appui à la démonstration d’exploitation et de consultation des journaux est cohérent avec sa documentation d’utilisation des conteneurs et des logs.

La faisabilité d’une intégration pédagogique entre n8n et Moodle est compatible avec les capacités de services web et d’API externes de Moodle, ce qui soutient la logique du critère d’intégration fonctionnelle.
