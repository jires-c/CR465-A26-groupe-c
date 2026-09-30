# 1. Présentation du projet

## Mandat

Votre équipe doit concevoir, automatiser, déployer et documenter une plateforme pédagogique conteneurisée composée de plusieurs services open source intégrés entre eux. La plateforme doit être déployée sur **une VM unique provisionnée en mode VM as Code à l'aide de cloud-init**, puis exploitée au moyen de conteneurs orchestrés avec Docker Compose.

Ce travail vise à vous placer dans une situation réaliste de conception et de déploiement d'une plateforme numérique auto-hébergée, tout en vous imposant une complexité raisonnable pour un premier projet de sécurité des conteneurs. Vous devrez livrer une architecture fonctionnelle, explicable, reproductible et suffisamment sécurisée pour illustrer les bonnes pratiques vues au cours, notamment le durcissement des images, le durcissement à l'exécution, la réduction des privilèges, la segmentation des réseaux, le durcissement de l'hôte, l'observabilité et l'intégration de contrôles de sécurité en amont du déploiement (shift left).

## Composants imposés

L'architecture devra intégrer les composants suivants :

- **Traefik** comme reverse proxy d'entrée et contrôleur d'exposition web.
- **OpenAppSec** *(bonus, optionnel — voir [6. Bonus — WAF](06-bonus-waf-openappsec.md))* de Check Point comme couche WAF ou protection applicative en frontal.
- **Drupal Commerce** comme portail transactionnel de vente des formations et contenus pédagogiques.
- **Moodle** comme LMS principal et plateforme de consommation des contenus après attribution des droits d'accès.
- **Keycloak** comme fournisseur d'identité et de SSO.
- **n8n** comme couche d'intégration visuelle no-code ou low-code entre Drupal Commerce et Moodle pour automatiser l'attribution des accès après achat simulé ou confirmé.
- **Opencast** *(optionnel, fortement souhaité)* comme service vidéo compatible avec Moodle.
- **PostgreSQL** comme base de données unique lorsque cela est réaliste pour les services choisis.
- **Redis** *(bonus)* pour le cache ou les sessions lorsque pertinent.
- **Portainer CE** comme interface graphique de gestion et d'exploitation des conteneurs.
- **Un volet minimal d'observabilité**, idéalement centré sur les journaux applicatifs, avec **Grafana + Loki** *(bonus)* si votre équipe est capable de l'intégrer sans compromettre la qualité globale du projet.

## Parcours fonctionnel attendu

Un utilisateur consulte une offre de formation dans Drupal Commerce, réalise un paiement fictif, simulé ou en environnement sandbox, puis un workflow n8n déclenche l'attribution ou la synchronisation d'un droit d'accès dans Moodle, où le contenu est ensuite consommé.

Le projet n'a pas pour objectif de reproduire une architecture d'entreprise complète, mais plutôt de démontrer une compréhension claire et progressive des principes fondamentaux de sécurité appliqués à un environnement conteneurisé réaliste.
