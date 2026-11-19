# GUIDE D'INTÉGRATION — OBSERVABILITÉ

**Projet :** CR465 — Architecture sécurisée d'une plateforme pédagogique conteneurisée sur VM

**Composants :** Grafana 12.3.0, Loki 3.5.5 et Alloy 1.10.2

**Branche source :** rolande

**Environnement cible :** Ubuntu 24.04, Docker Rootless, Docker Compose et Traefik

## 1. Objectif du guide

Ce document présente les étapes nécessaires pour intégrer les trois services d'observabilité à l'infrastructure commune du Groupe C.

L'intégration doit permettre :

- La collecte des journaux Docker par Alloy.
- Leur transmission et leur conservation dans Loki.
- Leur consultation dans Grafana.
- L'accès à Grafana en HTTPS par Traefik.

Les configurations ont déjà fait l'objet de tests sur une VM QEMU. Certaines vérifications devront être répétées sur la nouvelle VM commune, car son environnement peut être différent.

## 2. Prérequis

Avant de déployer l'observabilité, vérifier que :

1. La VM commune est opérationnelle.
2. Docker Rootless et Docker Compose fonctionnent avec l'utilisateur ubuntu.
3. Le répertoire /opt/GroupeC contient les fichiers du projet.
4. Le réseau GroupeC-applicatif existe.
5. Traefik est démarré.
6. Le script de déploiement commun a généré les secrets nécessaires.

Les commandes Docker ci-dessous doivent être exécutées dans le contexte Docker Rootless de l'utilisateur ubuntu, sauf indication contraire.

Les commandes supposent également que les fichiers de la branche rolande ont été intégrés dans /opt/GroupeC, sans écraser les modifications de la base commune.

## 3. Création du réseau d'observabilité

Les fichiers Compose utilisent un réseau externe nommé :

GroupeC-observabilite

Ce réseau doit exister avant le démarrage des services.

Vérifier sa présence :

```bash
docker network inspect GroupeC-observabilite
```

S'il n'existe pas, le créer :

```bash
docker network create GroupeC-observabilite
```

Cette commande crée uniquement le réseau d'observabilité. Elle ne modifie pas les réseaux communs.

Le réseau doit permettre les communications entre Grafana, Loki et Alloy.

## 4. Vérification du secret Grafana

La base commune prévoit le fichier :

/opt/GroupeC/secrets/grafana_admin_password

### 4.1 Vérifier son existence et ses permissions

```bash
stat -c '%a %U:%G %n' /opt/GroupeC/secrets/grafana_admin_password
```

Sur notre VM QEMU, le résultat observé était :

```text
640 ubuntu:ubuntu /opt/GroupeC/secrets/grafana_admin_password
```

Ces permissions autorisent la lecture par le propriétaire et le groupe, sans autoriser la lecture par les autres utilisateurs.

Sur la nouvelle VM, le résultat peut être différent.

Ne pas appliquer automatiquement chmod 640 sans vérifier les propriétaires, groupes et droits nécessaires au fonctionnement de Grafana.

### 4.2 Vérifier le montage du secret

Notre Compose utilise :

```yaml
environment:
  GF_SECURITY_ADMIN_USER: admin
  GF_SECURITY_ADMIN_PASSWORD__FILE: /run/secrets/grafana_admin_password

volumes:
  - /opt/GroupeC/secrets/grafana_admin_password:/run/secrets/grafana_admin_password:ro
```

Le fichier secret doit être présent avant le premier démarrage de Grafana.

Après le démarrage, vérifier sa lisibilité :

```bash
docker exec groupec-grafana sh -c 'test -r /run/secrets/grafana_admin_password && echo "LECTURE OK" || echo "LECTURE REFUSÉE"'
```

Résultat attendu : LECTURE OK.

Cette vérification a déjà réussi sur notre VM QEMU avec les permissions 640.

Elle ne vérifie pas à elle seule que le mot de passe enregistré dans Grafana correspond au contenu du secret.

### 4.3 Vérifier l'authentification

Lors d'une nouvelle installation, avec un volume Grafana vide, le mot de passe initial doit provenir du fichier secret.

Il faudra confirmer qu'il permet de se connecter avec l'utilisateur admin.

Attention : si Grafana a déjà été initialisé, modifier le secret ne change pas automatiquement le mot de passe administrateur enregistré.

Ne pas supprimer le volume grafana_data pour résoudre un problème d'authentification sans examiner les conséquences sur les données existantes.

### 4.4 Test effectué sur notre VM QEMU — permissions et lecture du secret

Objectif : vérifier que le fichier contenant le mot de passe administrateur Grafana est protégé et reste accessible au conteneur.

Environnement : VM Ubuntu sous QEMU, Docker Rootless.

Étape 1 — Vérification des permissions

Commande exécutée :

```bash
stat -c '%a %U:%G %n' /opt/GroupeC/secrets/grafana_admin_password
```

Résultat :

```text
640 ubuntu:ubuntu /opt/GroupeC/secrets/grafana_admin_password
```

Le propriétaire possède les droits de lecture et d'écriture, le groupe possède le droit de lecture et les autres utilisateurs n'ont aucun droit.

Étape 2 — Vérification de la lecture depuis Grafana

Commande exécutée :

```bash
docker exec groupec-grafana sh -c 'test -r /run/secrets/grafana_admin_password && echo "LECTURE OK" || echo "LECTURE REFUSÉE"'
```

Résultat :

```text
LECTURE OK
```

Le processus lancé par docker exec peut lire le fichier secret monté dans le conteneur Grafana.

Conclusion :

Les permissions 640 sont compatibles avec la configuration actuelle du conteneur Grafana sur notre VM QEMU.

Aucune modification des permissions ni du fichier Docker Compose n'a été nécessaire.

Vérification restante pour l'intégration finale : confirmer que le même mécanisme fonctionne sur la nouvelle VM commune, où les propriétaires, groupes et permissions des fichiers peuvent différer.

## 5. Déploiement des trois services

L'ordre proposé est :

1. Loki.
2. Alloy.
3. Grafana.

Cet ordre facilite les vérifications, mais les services pourront ensuite fonctionner simultanément.

### 5.1 Démarrer Loki

Depuis /opt/GroupeC :

```bash
docker compose -f observabilite/loki/docker-compose.yml up -d
```

Vérifier :

```bash
docker ps --filter name=groupec-loki
```

Loki doit utiliser le réseau GroupeC-observabilite et le volume loki_data.

Son port 3100 ne doit pas être publié directement sur l'hôte.

### 5.2 Démarrer Alloy

```bash
docker compose -f observabilite/alloy/docker-compose.yml up -d
```

Vérifier :

```bash
docker ps --filter name=groupec-alloy
```

Alloy doit utiliser le réseau GroupeC-observabilite.

Il doit également pouvoir lire les journaux Docker montés sous /journaux.

### 5.3 Démarrer Grafana

```bash
docker compose -f observabilite/grafana/docker-compose.yml up -d
```

Vérifier :

```bash
docker ps --filter name=groupec-grafana
```

Grafana doit appartenir aux deux réseaux :

- GroupeC-applicatif
- GroupeC-observabilite

Il doit également pouvoir lire son secret administrateur.

Précaution : ces commandes supposent que les trois Compose sont déployés séparément, comme prévu par notre organisation actuelle. Si l'intégrateur les ajoute à un script commun, il devra préserver les mêmes paramètres et vérifier les noms des projets Compose et des volumes.

## 6. Vérification des journaux Docker Rootless

Alloy utilise le montage suivant :

```yaml
- /home/ubuntu/.local/share/docker/containers:/journaux:ro
```

Sa configuration recherche :

```text
/journaux/*/*-json.log
```

### 6.1 Vérifier le pilote par défaut

```bash
docker info --format '{{.LoggingDriver}}'
```

Résultat attendu pour notre méthode de collecte :

```text
json-file
```

Cette commande indique le pilote par défaut. Un conteneur peut toutefois utiliser un pilote différent.

### 6.2 Vérifier un conteneur

Remplacer NOM_DU_CONTENEUR par un conteneur réel :

```bash
docker inspect --format '{{.HostConfig.LogConfig.Type}}' NOM_DU_CONTENEUR
```

Puis vérifier le chemin de son journal :

```bash
docker inspect --format '{{.LogPath}}' NOM_DU_CONTENEUR
```

Le fichier doit correspondre au chemin attendu par le montage d'Alloy et par le motif défini dans config.alloy.

Si nécessaire, le pilote peut être défini dans le Compose de l'application :

```yaml
logging:
  driver: "json-file"
  options:
    max-size: "10m"
    max-file: "3"
```

Cette configuration est prise en compte lors de la création ou recréation du conteneur.

### 6.3 Vérifier les permissions

Il faut confirmer qu'Alloy peut lire les journaux.

Si le chemin réel diffère, vérifier le montage Docker et le motif de recherche avant de modifier la configuration.

Ne pas déplacer manuellement les fichiers de journaux gérés par Docker.

Ne pas modifier globalement leurs permissions sans examiner les conséquences sur la sécurité et le fonctionnement de Docker Rootless.

## 7. Vérification de la communication Alloy–Loki

Notre configuration Alloy transmet les journaux à :

```text
http://groupec-loki:3100/loki/api/v1/push
```

Les deux services doivent partager le réseau GroupeC-observabilite.

Vérifier leur présence sur ce réseau :

```bash
docker network inspect GroupeC-observabilite
```

Vérifier ensuite les journaux de fonctionnement :

```bash
docker logs --tail 50 groupec-alloy
docker logs --tail 50 groupec-loki
```

Ces commandes permettent de rechercher des erreurs de lecture, de connexion ou d'envoi.

La validation complète doit confirmer que les journaux d'un conteneur applicatif apparaissent effectivement dans Loki.

## 8. Intégration de Grafana avec Traefik

### 8.1 Configuration retenue

La nouvelle infrastructure commune utilise la destination :

```text
http://grafana:3000
```

Notre configuration définit :

- Service Docker Compose : grafana
- Nom du conteneur : groupec-grafana
- Port interne : 3000
- Réseau partagé avec Traefik : GroupeC-applicatif

Aucune modification de la route Traefik n'est prévue à ce stade.

Le nom grafana correspond au nom du service Docker Compose.

### 8.2 Vérification restante

Il faudra confirmer que Traefik peut résoudre grafana sur le réseau GroupeC-applicatif et communiquer avec le service sur le port 3000.

Cette vérification doit être effectuée dans l'environnement intégré.

Important : la compatibilité de configuration a été examinée, mais la communication réelle avec la nouvelle base commune n'a pas encore été validée.

## 9. Accès HTTPS à Grafana

L'adresse prévue est :

```text
https://grafana.groupec.test:4443
```

Le navigateur communique avec Traefik en HTTPS. Traefik transmet ensuite la requête à Grafana par HTTP sur le réseau Docker interne.

Sur notre VM QEMU, la page de connexion Grafana a déjà été atteinte à cette adresse.

Lors de l'intégration finale, vérifier :

1. Que le nom grafana.groupec.test pointe vers l'adresse appropriée.
2. Que le port HTTPS est accessible depuis le poste de test.
3. Que Traefik transmet les requêtes à Grafana.
4. Que la page de connexion apparaît.
5. Que les identifiants administrateur permettent une connexion réussie.

Le certificat utilisé lors de nos essais était autosigné. Le navigateur peut donc afficher un avertissement de confiance.

L'accès à la page de connexion ne prouve pas, à lui seul, que l'authentification fonctionne.

### 9.1 Test d'accès HTTPS depuis Firefox sur Windows

Un test d'accès à Grafana a été effectué depuis le navigateur Firefox sur le poste Windows, en utilisant l'adresse :

```text
https://grafana.groupec.test:4443/login
```

Lors de ce test, Firefox a affiché un avertissement de sécurité concernant le certificat HTTPS, car celui-ci était autosigné et n'était pas reconnu comme fiable par le navigateur.

La page de connexion Grafana a néanmoins pu être affichée.

Résultat : l'accès à la page de connexion Grafana par HTTPS via Traefik a été confirmé sur notre VM QEMU.

Limites du test :

- Le certificat HTTPS n'était pas reconnu comme fiable par Firefox.
- La connexion réussie au compte administrateur Grafana depuis Firefox n'a pas été confirmée.
- L'accès HTTPS devra être vérifié de nouveau sur la VM commune lors de l'intégration finale.

Ce test confirme l'accessibilité de la page Grafana via Traefik dans notre environnement QEMU, mais ne constitue pas une validation complète de l'authentification ni de la confiance du certificat HTTPS.

## 10. Stockage et conservation des journaux

Loki utilise le volume persistant :

```text
loki_data
```

Les données sont stockées sous /loki dans le conteneur.

La conservation est configurée à :

```yaml
limits_config:
  retention_period: 168h
```

Le compactor est activé pour appliquer cette rétention.

La durée configurée est donc de sept jours.

Il faudra confirmer son application effective lors de la validation finale.

Le volume persistant ne remplace pas une sauvegarde indépendante.

## 11. Vérifications finales

| Contrôle | Résultat attendu | État |
| --- | --- | --- |
| Réseau d'observabilité | Réseau présent | À vérifier sur la VM commune |
| Loki | Conteneur actif | Déjà testé sur QEMU |
| Alloy | Conteneur actif | Déjà testé sur QEMU |
| Grafana | Conteneur actif | Déjà testé sur QEMU |
| Secret Grafana | Fichier présent et lisible | Lecture validée sur QEMU |
| Permissions du secret | Accès limité et fonctionnel | 640 validé sur QEMU |
| Authentification Grafana | Connexion administrateur réussie | À confirmer |
| Pilote Docker | json-file pour les conteneurs collectés | À confirmer |
| Chemin des journaux | Compatible avec /journaux | À confirmer |
| Permissions des journaux | Lecture possible par Alloy | À confirmer |
| Alloy vers Loki | Journaux reçus | Déjà testé sur QEMU |
| Grafana vers Loki | Consultation possible | Déjà testé sur QEMU |
| Traefik vers Grafana | Résolution DNS et connexion au port 3000 | À confirmer sur la base commune |
| HTTPS | Page accessible via Traefik | Déjà observé sur QEMU |
| Rétention Loki | Conservation de sept jours appliquée | À confirmer |

## 12. Conclusion

La contribution d'observabilité est constituée de trois services Docker indépendants : Grafana, Loki et Alloy.

Les fichiers de configuration sont publiés sur la branche rolande.

L'intégration doit conserver le réseau spécialisé GroupeC-observabilite, utiliser le secret Grafana généré par la base commune et préserver la route Traefik existante vers http://grafana:3000.

Les essais effectués sur notre VM QEMU ont permis de valider plusieurs fonctions essentielles, notamment la collecte des journaux et leur consultation dans Grafana.

Les vérifications restantes concernent principalement les différences possibles entre notre VM et la nouvelle base commune.

Elles devront être réalisées par l'intégrateur avant de considérer l'observabilité comme entièrement opérationnelle sur la plateforme commune.
