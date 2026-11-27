# Présentation du projet

## Contexte et objectifs
Dans le cadre du cours CR465, le Groupe C a conçu une plateforme pédagogique conteneurisée, sécurisée et automatisée sur une seule VM Ubuntu 24.04 LTS. L'approche Infrastructure as Code repose sur Cloud-init et Docker Compose avec Docker Rootless.

Les objectifs sont le déploiement reproductible, la segmentation réseau, l’authentification Moodle via Keycloak, l’attribution automatique des formations après commande et la centralisation des journaux.

## Scénario fonctionnel
L’étudiant se connecte à Moodle via Keycloak, commande une formation sur Drupal Commerce et obtient son inscription à Moodle après confirmation du paiement simulé et exécution du workflow n8n.

**Référence : Cloud-init V5.5.**

[Retour à la documentation](../README.md)
