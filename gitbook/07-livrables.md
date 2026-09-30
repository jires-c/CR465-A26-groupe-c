# 7. Livrables

> ⚠️ **Ne confondez pas les deux GitBook.** Ce GitBook-ci est un document de présentation produit par l'enseignant pour vous aider à comprendre le mandat. **Votre équipe doit produire son propre GitBook** comme livrable, décrit ci-dessous — ce n'est pas le même document.

La remise finale comprend **quatre livrables obligatoires**.

## 1. Vidéo de démonstration

- Durée maximale : **15 min 59 s**, sans durée minimale exigée.
- Hébergement obligatoire sur **Microsoft 365 de l'université**, par exemple **Microsoft Stream** ou un service M365 équivalent.
- La vidéo doit être accessible à l'enseignant et, si requis, à l'équipe de correction.

La vidéo doit au minimum contenir :

- une brève introduction de l'équipe;
- la présentation du mandat et du périmètre;
- la démonstration de la VM as Code via cloud-init;
- la présentation du schéma d'architecture;
- la démonstration fonctionnelle de la plateforme, y compris le parcours de vente et d'attribution d'accès;
- la présentation des mécanismes de sécurité;
- la démonstration minimale du SSO;
- la démonstration d'un volet d'observabilité ou de diagnostic;
- les limites, difficultés et améliorations futures.

### Contenu suggéré de la vidéo

La structure suivante est fortement recommandée :

1. Présentation de l'équipe et du mandat.
2. Présentation de la VM as Code et du fichier cloud-init.
3. Présentation de l'architecture logique et du schéma réseau.
4. Démonstration du déploiement ou de l'environnement opérationnel.
5. Démonstration de Drupal Commerce, du flux n8n, de Moodle et du SSO Keycloak.
6. Présentation du reverse proxy et des protections choisies, et démonstration du WAF bonus si votre équipe l'a implémenté.
7. Démonstration de Portainer CE et, si disponible, de la journalisation centralisée.
8. Conclusion courte : limites, risques résiduels, apprentissages.

## 2. GitBook (le vôtre, à produire par votre équipe)

Votre GitBook doit contenir une documentation structurée et lisible comprenant au minimum :

- la présentation du projet;
- l'architecture;
- la description de la VM et du fichier cloud-init;
- le déploiement Docker Compose;
- la segmentation réseau;
- les mesures de sécurité;
- le SSO;
- le workflow n8n et le flux d'attribution des accès après commande;
- l'observabilité;
- les limites et les risques résiduels;
- la contribution des membres de l'équipe.

## 3. Dépôt GitHub

Le dépôt GitHub doit contenir l'ensemble du code source et de la documentation technique utile. Il doit inclure, au minimum :

- le ou les fichiers cloud-init;
- les fichiers Docker Compose;
- les configurations associées aux services;
- un `README.md` clair;
- un fichier `.env.example` sans secrets réels;
- les scripts utiles au démarrage ou à la validation;
- des captures ou traces utiles si nécessaire.

Une structure de dépôt claire sera valorisée. Structure suggérée :

```text
repo/
├── cloud-init/
│   ├── user-data.yaml
│   └── README.md
├── compose/
│   ├── docker-compose.yml
│   ├── .env.example
│   ├── traefik/
│   ├── openappsec/          # bonus, optionnel
│   ├── drupal-commerce/
│   ├── moodle/
│   ├── keycloak/
│   ├── n8n/
│   ├── opencast/
│   ├── portainer/
│   └── observability/
├── docs/
│   ├── architecture/
│   ├── security/
│   ├── operations/
│   └── testing/
├── media/
└── README.md
```

Vous pouvez proposer une autre structure si elle demeure cohérente et facile à évaluer.

## 4. Document de synthèse

Vous devez remettre **au choix** :

- un document **Word** de **10 pages maximum**, page de garde incluse; **ou**
- un document **PowerPoint** de **10 pages maximum**, page de garde incluse.

Ce document doit être une synthèse décisionnelle, et non une duplication complète du GitBook. Il doit permettre à un évaluateur de comprendre rapidement votre architecture, vos choix, vos protections, vos limites et votre répartition du travail.

## Remise

Sauf indication contraire, la remise devra comprendre :

- le lien de la vidéo hébergée sur Microsoft 365;
- le lien du GitBook (le vôtre);
- le lien du dépôt GitHub;
- le document Word ou PowerPoint.
