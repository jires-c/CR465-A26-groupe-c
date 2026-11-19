# Guide d'intégration — Observabilité GroupeC

## 1. Objectif

Intégrer Grafana, Loki et Grafana Alloy dans
l'architecture Docker Rootless du projet CR465 GroupeC.

Rôles des composants :
- Grafana : consultation et interrogation des journaux.
- Loki : réception, stockage et recherche des journaux.
- Alloy : collecte et transmission des journaux Docker.

Ce guide décrit l'installation validée sur la VM
de développement et les adaptations nécessaires
pour son intégration dans l'environnement commun.

La reconstruction complète sur une nouvelle VM
n'a pas encore été validée.

## 2. Fichiers à intégrer dans GitHub

grafana/docker-compose.yml
grafana/provisioning/datasources/loki.yml

loki/docker-compose.yml
loki/loki-config.yaml

alloy/docker-compose.yml
alloy/config.alloy

Ne pas publier :
- Les fichiers de mots de passe.
- Les volumes Docker contenant les données.
- Les anciennes sauvegardes des fichiers Compose.
- Les certificats ou clés privées.

## 3. Images Docker

Grafana : grafana/grafana:12.3.0
Loki : grafana/loki:3.5.5
Alloy : grafana/alloy:v1.10.2

Ces versions correspondent aux fichiers
Compose examinés sur la VM de développement.

## 4. Réseaux Docker

Deux réseaux sont nécessaires :

GroupeC-applicatif
GroupeC-observabilite

Grafana utilise les deux réseaux.

Loki et Alloy utilisent :
GroupeC-observabilite

Les fichiers Compose déclarent ces réseaux
comme external: true.

Ils doivent donc exister avant le déploiement.

Vérification :
docker network ls

Si le réseau d'observabilité n'existe pas :
docker network create GroupeC-observabilite

Le réseau applicatif est commun au groupe.
Sa création doit être coordonnée avec l'intégrateur.

## 5. Volumes persistants

Grafana :
grafana_grafana_data -> /var/lib/grafana

Loki :
loki_loki_data -> /loki

Alloy :
alloy_alloy_data -> /var/lib/alloy/data

Les volumes sont créés par Docker Compose.

Leur contenu ne doit pas être publié dans GitHub.

La persistance Grafana après redémarrage
du conteneur a été testée.

Aucune restauration après perte de volume
n'a été réalisée.

## 6. Secret administrateur Grafana

Chemin attendu :
/opt/GroupeC/secrets/grafana_admin_password

Le fichier Compose utilise :
GF_SECURITY_ADMIN_PASSWORD__FILE

Le mot de passe est lu depuis :
/run/secrets/grafana_admin_password

Le script commun deploy.sh prévoit déjà
la génération automatique du secret.

Il utilise openssl pour produire
une valeur aléatoire lorsque le fichier
n'existe pas ou est vide.

Permissions vérifiées sur la VM :
640 ubuntu ubuntu

La lecture du secret par Grafana a été testée.

Attention :
deploy.sh applique actuellement 0644
aux secrets ordinaires.

Correction proposée à l'intégrateur :
chmod 0640 "$ROOT/secrets/grafana_admin_password"

Cette correction doit être coordonnée
avec le responsable du script commun.

Ne jamais publier le véritable secret
dans GitHub ou dans la documentation.

## 7. Déploiement Loki

Dossier :
/opt/GroupeC/loki

Fichiers :
docker-compose.yml
loki-config.yaml

Image :
grafana/loki:3.5.5

Port interne :
3100

Aucune publication directe du port 3100.

Réseau :
GroupeC-observabilite

Volume :
loki_loki_data

Rétention configurée :
168 heures, soit 7 jours.

La suppression effective après 7 jours
n'a pas été testée.

L'authentification interne Loki est désactivée.
Son accès doit rester limité aux composants
autorisés du réseau Docker interne.

## 8. Déploiement Alloy

Dossier :
/opt/GroupeC/alloy

Fichiers :
docker-compose.yml
config.alloy

Image :
grafana/alloy:v1.10.2

Réseau :
GroupeC-observabilite

Volume :
alloy_alloy_data

Alloy lit les journaux Docker Rootless
depuis le chemin suivant sur la VM testée :

/home/ubuntu/.local/share/docker/containers

Ce chemin est monté en lecture seule
dans le conteneur sous /journaux.

La configuration recherche :
/journaux/*/*-json.log

Elle utilise notamment :
local.file_match
loki.source.file
loki.process
loki.write

Destination Loki :
http://groupec-loki:3100/loki/api/v1/push

Avant le déploiement, vérifier :
docker info --format '{{.DockerRootDir}}'

Le chemin des journaux doit correspondre
à l'installation Docker Rootless cible.

Vérifier également que le pilote de
journalisation produit des fichiers
compatibles avec la collecte configurée.

## 9. Déploiement Grafana

Dossier :
/opt/GroupeC/grafana

Fichiers :
docker-compose.yml
provisioning/datasources/loki.yml

Image :
grafana/grafana:12.3.0

Port interne :
3000

Aucune publication directe du port 3000.

Réseaux :
GroupeC-applicatif
GroupeC-observabilite

Volume :
grafana_grafana_data

La source de données Loki est configurée
automatiquement par provisioning.

Nom : Loki
UID : loki
Adresse : http://groupec-loki:3100

## 10. Ordre de déploiement recommandé

1. Vérifier Docker Rootless.
2. Préparer les dossiers et les réseaux.
3. Générer les secrets nécessaires.
4. Démarrer Loki.
5. Vérifier que Loki est prêt.
6. Démarrer Alloy.
7. Vérifier la transmission des journaux.
8. Démarrer Grafana.
9. Vérifier la source Loki.
10. Configurer et tester le routage Traefik.

L'intégrateur peut automatiser cet ordre
dans le processus de déploiement commun.

## 11. Routage HTTPS avec Traefik

Nom prévu :
grafana.groupec.test

Adresse prévue :
https://grafana.groupec.test:4443

Service interne :
http://groupec-grafana:3000

Traefik assure le routage HTTPS.

Grafana gère sa propre authentification.

Le middleware dashboard-auth est réservé
au tableau de bord Traefik selon les
informations communiquées par l'intégrateur.

Le routage Grafana a été testé depuis
Windows et depuis Ubuntu.

La page de connexion Grafana est accessible.

Le certificat observé dans Firefox était :
TRAEFIK DEFAULT CERT

La configuration définitive du certificat
et de la résolution DNS relève de
l'intégration commune.

## 12. Vérifications techniques

Valider les fichiers Compose :

docker compose -f /opt/GroupeC/loki/docker-compose.yml config --quiet

docker compose -f /opt/GroupeC/alloy/docker-compose.yml config --quiet

docker compose -f /opt/GroupeC/grafana/docker-compose.yml config --quiet

Vérifier les conteneurs :
docker ps

Vérifier les réseaux :
docker network ls

Vérifier la disponibilité Loki depuis Grafana :
docker exec groupec-grafana \
  wget -S -O- http://groupec-loki:3100/ready 2>&1

Résultat attendu :
HTTP 200
ready

Vérifier les étiquettes Loki :
docker exec groupec-grafana \
  wget -qO- http://groupec-loki:3100/loki/api/v1/labels

Des étiquettes observées lors des tests :
filename
job
service_name
stream

## 13. Résultats déjà validés

Sur la VM de développement :
- Grafana, Loki et Alloy ont démarré.
- Les fichiers Compose Loki et Alloy sont valides.
- Les montages Docker ont été vérifiés.
- Grafana peut joindre Loki.
- Loki a répondu HTTP 200 avec ready.
- L'API Grafana a interrogé Loki avec succès.
- Cinq messages ont été récupérés dans deux flux.
- Des journaux d'Alloy et de Loki étaient présents.
- L'accès HTTPS à Grafana depuis Windows fonctionne.

## 14. Points restant à vérifier

- Reproduction complète sur une nouvelle VM.
- Intégration au processus deploy.sh commun.
- Création des réseaux et secrets dans le bon ordre.
- Collecte effective des journaux des autres applications.
- Configuration définitive du certificat HTTPS.
- Résolution DNS sur les postes du groupe.
- Consultation des journaux dans l'interface Grafana.
- Tests de sauvegarde et de restauration des volumes.
- Application effective de la rétention après 7 jours.

## 15. Conclusion

Les fichiers Grafana, Loki et Alloy sont présents
et ont été examinés sur la VM de développement.

Les principaux tests fonctionnels sont réussis.

L'intégrateur dispose des dépendances,
des configurations et des vérifications
nécessaires pour préparer le déploiement commun.

La reproductibilité complète reste à valider
sur une nouvelle installation.
