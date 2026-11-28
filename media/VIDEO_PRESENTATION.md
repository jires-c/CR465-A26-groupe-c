# CR465 — Groupe C | Présentation orale finale

## Enregistrement de la démonstration — Plateforme V5.5

**Visionner la vidéo :** [Présentation orale du projet CR465 — Groupe C](https://polymtlca-my.sharepoint.com/:v:/r/personal/mohamed-al-mahdi_bouamor_etud_polymtl_ca/Documents/Enregistrements/cr465videofinalGroupeC.mp4?d=wd276e53c5fd04ec6b28789d9789c5fba&csf=1&web=1&nav=eyJyZWZlcnJhbEluZm8iOnsicmVmZXJyYWxBcHAiOiJPbmVEcml2ZUZvckJ1c2luZXNzIiwicmVmZXJyYWxBcHBQbGF0Zm9ybSI6IldlYiIsInJlZmVycmFsTW9kZSI6InZpZXciLCJyZWZlcnJhbFZpZXciOiJNeUZpbGVzTGlua0NvcHkifX0&e=A4zf9i)

Cette présentation porte sur l’architecture sécurisée d’une plateforme pédagogique conteneurisée, automatisée sur une machine virtuelle Ubuntu 24.04 LTS à l’aide de **cloud-init**, de **Docker Rootless** et de **Docker Compose**.

Elle présente :

1. Le mandat et l’architecture logique de la plateforme.
2. Le provisionnement automatisé de la VM et les mesures de sécurité (UFW, Docker Rootless, segmentation et Traefik HTTPS).
3. Le déploiement et l’exploitation des services, notamment avec Portainer.
4. Le SSO entre Keycloak et Moodle.
5. Le scénario fonctionnel validé : **Drupal Commerce → paiement fictif confirmé → workflow n8n → inscription au cours CYBER465 dans Moodle**.
6. L’observabilité avec **Alloy, Loki et Grafana**, ainsi que l’analyse de sécurité des images avec Trivy.
7. Les limites et risques résiduels du laboratoire.

**Plateforme :** Groupe C — V5.5  
**Hébergement de l’enregistrement :** Microsoft 365 / OneDrive (Polytechnique Montréal)  
**Dépôt du projet :** [CR465-A26-groupe-c](https://github.com/jires-c/CR465-A26-groupe-c)

> **Accès à vérifier avant la remise :** le partage du fichier vidéo doit autoriser l’enseignant et les correcteurs à visionner l’enregistrement. Le lien étant hébergé sur Microsoft 365, une connexion institutionnelle peut être demandée.
