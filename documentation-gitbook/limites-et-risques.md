# Limites et risques résiduels

| Risque ou limite | Amélioration proposée |
|---|---|
| VM et PostgreSQL uniques | Sauvegarde, restauration, haute disponibilité |
| Certificat autosigné | Autorité de certification reconnue |
| Réseau applicatif partagé | Microsegmentation |
| Secrets locaux | Coffre-fort et rotation |
| Rejeu du webhook non démontré | Contrôle anti-rejeu et idempotence |
| WAF non déployé | Intégration OpenAppSec |
| Socket Docker accessible à Portainer | Limitation des accès administratifs |
| Journaux uniquement | Métriques, alertes et traces |
| SSO limité à Moodle | Extension aux services compatibles |
| Paiement simulé, premier SKU uniquement | Intégration de paiement et gestion multi-articles |

L’étudiant doit se connecter une première fois à Moodle. Redis, OpenAppSec et l’application Opencast ne sont pas déployés. Les améliorations proposées ne sont pas des fonctionnalités de la V5.5.

[Retour à la documentation](../README.md)
