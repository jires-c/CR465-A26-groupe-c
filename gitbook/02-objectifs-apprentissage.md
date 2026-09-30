# 2. Objectifs d'apprentissage

À la fin de ce travail, votre équipe devra être capable de :

- Concevoir une architecture multi-services cohérente et segmentée, intégrant une logique de commerce numérique, d'identité et de consommation pédagogique.
- Déployer une VM Linux à l'aide d'un mécanisme d'initialisation déclaratif avec cloud-init.
- Déployer une plateforme applicative conteneurisée avec Docker Compose.
- Mettre en place un reverse proxy et des points d'entrée HTTPS *(un WAF est un bonus optionnel, voir [6. Bonus — WAF](06-bonus-waf-openappsec.md))*.
- Configurer un mécanisme de SSO avec Keycloak, au minimum entre Keycloak et Moodle.
- Mettre en place une logique d'intégration visuelle avec n8n entre Drupal Commerce et Moodle, sans exiger de développement logiciel avancé.
- Appliquer des mesures de durcissement sur les images, les conteneurs et l'hôte.
- Réduire la surface d'attaque par une segmentation réseau et une exposition minimale des services.
- Intégrer une forme de visibilité opérationnelle et de journalisation utile en contexte d'incident.
- Documenter les choix architecturaux, les compromis, les limites et les risques résiduels.
