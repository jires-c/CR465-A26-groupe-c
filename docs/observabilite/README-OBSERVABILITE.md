# CR465 - Documentation de l'observabilité

## Objectif
Mettre en place Grafana, Loki et Grafana Alloy
sur la VM Ubuntu 24.04 sous QEMU avec Docker Rootless.

## Architecture prévue
- Grafana : visualisation et recherche des journaux.
- Loki : réception, stockage et interrogation des journaux.
- Alloy : collecte des journaux et transmission à Loki.
- Traefik : accès HTTPS à l'interface Grafana.

## Réseau Docker
Nom : GroupeC-observabilite
Pilote : bridge
Portée : local

Commande de création :
docker network create GroupeC-observabilite

Commande de vérification :
docker network ls

Résultat : réseau créé et présent dans la liste Docker.

## Connexions réseau prévues
- Grafana : GroupeC-applicatif et GroupeC-observabilite.
- Loki : GroupeC-observabilite.
- Alloy : GroupeC-observabilite.
- Traefik : réseaux existants conservés.

## Étapes restantes
- Examiner la configuration Loki existante.
- Déployer et tester Loki.
- Configurer Alloy et la collecte des journaux.
- Déployer Grafana et le connecter à Loki.
- Configurer l'accès à Grafana via Traefik.
- Tester la chaîne complète et la persistance.

## Installation et validation de Loki

### Configuration
- Image Docker : grafana/loki:3.5.5
- Fichier de configuration : /opt/GroupeC/loki/loki-config.yaml
- Fichier Compose : /opt/GroupeC/loki/docker-compose.yml
- Réseau : GroupeC-observabilite
- Port HTTP interne : 3100
- Volume persistant : loki_loki_data
- Limite mémoire configurée : 768 Mio
- Rétention configurée : 168 heures (7 jours)
- Aucun port Loki publié directement sur Windows.

### Préparation
Le fichier docker-compose.yml initial était un gabarit commenté.
Une sauvegarde a été créée sous docker-compose.yml.original.

Le premier contrôle Docker Compose a retourné :
empty compose file

Cause : aucune définition de service active dans le gabarit.
Correction : ajout de la configuration Loki sous les commentaires existants.

### Commandes de validation
docker compose -f /opt/GroupeC/loki/docker-compose.yml config --quiet
docker compose -f /opt/GroupeC/loki/docker-compose.yml up -d
docker ps --filter name=groupec-loki
docker logs --tail 20 groupec-loki
docker exec groupec-loki /usr/bin/loki -version
docker exec groupec-loki wget -qO- http://127.0.0.1:3100/ready

### Résultats observés
- Validation Docker Compose réussie.
- Image Loki 3.5.5 téléchargée.
- Volume loki_loki_data créé.
- Conteneur groupec-loki démarré.
- Journaux indiquant "Loki started".
- Premier test /ready : HTTP 503 Service Unavailable.
- Deuxième test /ready : ready.

### Conclusion
Le service Loki est démarré et disponible.
La collecte réelle des journaux, leur rétention effective
et leur consultation depuis Grafana restent à vérifier.

Prochaine étape : installation de Grafana Alloy.

## Préparation de Grafana Alloy - Source des journaux

### Objectif
Déterminer comment collecter les journaux des conteneurs
Docker Rootless sans monter le socket Docker dans Alloy.

### Dossier de travail
Le dossier /opt/GroupeC/alloy a été créé avec sudo mkdir -p.

### Vérifications réalisées
Commande : docker info --format '{{.LoggingDriver}}'
Résultat : json-file

Commande : docker info --format '{{.DockerRootDir}}'
Résultat : /home/ubuntu/.local/share/docker

Commande : id
Résultat : ubuntu, UID 1000, GID 1000, groupes ubuntu et sudo.

Les fichiers de journaux sont enregistrés sous :
/home/ubuntu/.local/share/docker/containers/

Le fichier de journaux Loki appartient à ubuntu:ubuntu
et possède les permissions -rw-r-----.

### Test de lecture depuis un conteneur
Un conteneur temporaire Alpine 3.20 a été utilisé
avec un montage en lecture seule du répertoire
des journaux Docker vers /journaux.

Le premier test a confirmé la présence des fichiers JSON.
Un deuxième test n'a affiché aucune ligne.
Un troisième test, ciblant un fichier non vide, a réussi.

Résultat : lecture d'un message JSON contenant les champs
log, stream et time.

### Conclusion
La lecture des journaux Docker Rootless depuis un conteneur
temporaire a été validée sans montage du socket Docker.

Méthode retenue pour Alloy :
- Lecture des fichiers JSON Docker.
- Montage du répertoire des journaux en lecture seule.
- Transmission des journaux à Loki sur GroupeC-observabilite.

Points restant à vérifier :
- Permissions effectives du processus Alloy.
- Configuration de la collecte et suivi des fichiers.
- Réception des journaux dans Loki.
- Comportement lors de la rotation des journaux.

## Installation et validation de Grafana Alloy

### Configuration
- Image : grafana/alloy:v1.10.2
- Conteneur : groupec-alloy
- Fichier : /opt/GroupeC/alloy/config.alloy
- Compose : /opt/GroupeC/alloy/docker-compose.yml
- Réseau : GroupeC-observabilite
- Volume persistant : alloy_alloy_data
- Limite mémoire configurée : 512 Mio
- Aucun port publié vers Windows.
- Aucun montage du socket Docker.

### Méthode de collecte
Alloy lit les fichiers JSON de Docker Rootless
depuis /journaux, monté en lecture seule.

Composants Alloy utilisés :
- local.file_match : repérage des fichiers.
- loki.source.file : lecture des journaux.
- loki.process : extraction des champs JSON.
- loki.write : transmission vers Loki.

Destination :
http://groupec-loki:3100/loki/api/v1/push

### Commandes de déploiement et de validation
docker compose -f /opt/GroupeC/alloy/docker-compose.yml config --quiet
docker compose -f /opt/GroupeC/alloy/docker-compose.yml up -d
docker ps -a --filter name=groupec-alloy
docker logs --tail 30 groupec-alloy

docker exec groupec-loki wget -qO- \
'http://127.0.0.1:3100/loki/api/v1/labels'

docker exec groupec-loki wget -qO- \
'http://127.0.0.1:3100/loki/api/v1/query_range?query=%7Bjob%3D%22docker-rootless%22%7D&limit=2'

### Résultats observés
- Validation Docker Compose réussie.
- Image Alloy téléchargée.
- Volume alloy_alloy_data créé.
- Conteneur groupec-alloy démarré et actif.
- Configuration Alloy chargée sans erreur visible.
- Messages "tail routine: started" observés.
- Loki retourne les labels filename, job,
  service_name et stream.
- Requête query_range réussie.
- Deux véritables messages de journaux récupérés.

### Conclusion
La chaîne Docker Rootless -> Alloy -> Loki est validée.
La collecte fonctionne sans montage du socket Docker.

### Vérifications restantes
- Consultation des journaux dans Grafana.
- Persistance et reprise après redémarrage.
- Rotation des fichiers de journaux.
- Application effective de la rétention de 7 jours.

## Préparation de Grafana - Gestion du secret administrateur

### Objectif
Configurer l'authentification administrateur de Grafana
sans inscrire le mot de passe directement dans Docker Compose.

### Configuration prévue
- Service : Grafana
- Port HTTP interne : 3000
- Réseaux : GroupeC-applicatif et GroupeC-observabilite
- Stockage : volume Docker persistant
- Secret : /opt/GroupeC/secrets/grafana_admin_password
- Accès externe prévu : HTTPS par Traefik

### Vérification initiale du secret
Commande :
sudo ls -l /opt/GroupeC/secrets/grafana_admin_password

Résultat :
- Le fichier existe.
- Propriétaire : ubuntu
- Groupe : ubuntu
- Permissions initiales : 644

Problème identifié :
Les permissions 644 autorisaient la lecture du fichier
par les autres utilisateurs de la VM.

### Première correction
Commande :
sudo chmod 600 /opt/GroupeC/secrets/grafana_admin_password

Résultat :
Permissions modifiées en 600 (-rw-------).

### Test de lecture depuis un conteneur
Un conteneur Alpine 3.20 a été exécuté avec :
- UID 472, correspondant à l'utilisateur habituel de Grafana.
- Montage du secret en lecture seule.
- Test de lisibilité sans affichage du mot de passe.

Résultat observé :
Secret non lisible.

Interprétation :
Le test a montré que l'utilisateur 472 ne pouvait pas
lire le fichier avec les permissions 600 dans cette
configuration Docker Rootless.

### Deuxième correction
Commande :
sudo chmod 640 /opt/GroupeC/secrets/grafana_admin_password

Résultat confirmé :
-rw-r----- 1 ubuntu ubuntu

Les permissions 640 permettent la lecture au propriétaire
et au groupe du fichier, mais pas aux autres utilisateurs.

### Configuration envisagée
Tester l'accès au secret depuis un conteneur exécuté
avec UID 472 et groupe supplémentaire 1000.

Cette méthode doit être vérifiée dans Docker Rootless
avant son utilisation dans le Compose Grafana.

### État actuel
- Secret existant : confirmé.
- Permissions 640 : confirmées.
- Lecture avec UID 472 et groupe supplémentaire : non testée.
- Conteneur Grafana : pas encore déployé.

### Précautions
- Ne pas afficher le contenu du secret dans les captures.
- Ne pas inscrire le mot de passe dans Docker Compose.
- Ne pas publier le secret sur GitHub.
- Éviter les permissions 644 ou 777 pour ce fichier.

### Validation du secret avec un groupe supplémentaire

Commande de test :
docker run --rm \
  --user 472:0 \
  --group-add 1000 \
  --mount type=bind,source=/opt/GroupeC/secrets/grafana_admin_password,target=/run/secrets/grafana_admin_password,readonly \
  alpine:3.20 \
  sh -c 'id; if test -r /run/secrets/grafana_admin_password; then echo "Secret lisible"; else echo "Secret non lisible"; fi'

Résultat observé :
uid=472 gid=0(root) groups=0(root),1000
Secret lisible

Conclusion :
Dans ce test Docker Rootless, l'ajout du groupe 1000
permet à l'utilisateur 472 de lire le secret avec les
permissions 640.

Le secret reste inaccessible aux autres utilisateurs
selon les permissions classiques du fichier.

Cette méthode sera reprise dans Docker Compose avec :
user: "472:0"
group_add:
  - "1000"

La lecture effective par Grafana reste à vérifier.

## Installation et validation initiale de Grafana

### Configuration
- Image : grafana/grafana:12.3.0
- Conteneur : groupec-grafana
- Compose : /opt/GroupeC/grafana/docker-compose.yml
- Volume persistant : grafana_grafana_data
- Réseaux : GroupeC-applicatif et GroupeC-observabilite
- Port HTTP interne : 3000
- Aucun port Grafana publié directement sur Windows.
- Secret administrateur monté en lecture seule.
- Utilisateur configuré : 472:0
- Groupe supplémentaire configuré : 1000

### Déploiement
Commande :
docker compose -f /opt/GroupeC/grafana/docker-compose.yml up -d

Résultats :
- Image Grafana 12.3.0 téléchargée.
- Volume grafana_grafana_data créé.
- Conteneur groupec-grafana démarré.

### Vérification du fonctionnement
Commandes :
docker ps -a --filter name=groupec-grafana
docker logs --tail 30 groupec-grafana
docker exec groupec-grafana wget -qO- http://127.0.0.1:3000/api/health

Résultats observés :
- Conteneur actif : Up 3 minutes lors du contrôle.
- Serveur HTTP démarré sur le port 3000.
- Message "All modules healthy" observé.
- Messages temporaires "database is locked" observés.
- API /api/health accessible.
- Réponse : database = ok, version = 12.3.0.

### Conclusion
Le démarrage de Grafana et l'accès à sa base SQLite
sont validés.

Points restant à vérifier :
- Authentification avec le secret administrateur.
- Connexion à Loki comme source de données.
- Consultation des journaux dans Grafana.
- Accès HTTPS via Traefik.
- Persistance des configurations après redémarrage.

## Configuration de la source de données Loki dans Grafana

### Objectif
Permettre à Grafana de consulter les journaux stockés dans Loki
en utilisant le réseau Docker GroupeC-observabilite.

### Configuration réalisée
Fichier créé :
/opt/GroupeC/grafana/provisioning/datasources/loki.yml

Paramètres :
- Nom : Loki
- UID : loki
- Type : loki
- URL : http://groupec-loki:3100
- Source par défaut : oui
- Modification depuis l'interface : désactivée

Le dossier provisioning/datasources a été monté en lecture seule
dans /etc/grafana/provisioning/datasources.

Une sauvegarde du Compose a été créée :
/opt/GroupeC/grafana/docker-compose.yml.avant-provisioning

### Déploiement
Commande :
docker compose -f /opt/GroupeC/grafana/docker-compose.yml up -d

Résultat :
Conteneur groupec-grafana démarré avec le nouveau montage.

### Validation du provisioning
Les journaux Grafana indiquent :
"in serting datasource from configuration"
name=Loki uid=loki

La source Loki a donc été chargée automatiquement.

### Vérification réseau
Commande :
docker exec groupec-grafana wget -qO- http://groupec-loki:3100/ready

Premier résultat :
HTTP 503 Service Unavailable.

Vérification locale dans Loki :
docker exec groupec-loki wget -qO- http://127.0.0.1:3100/ready

Résultat : ready.

Deuxième vérification depuis Grafana :
Résultat : ready.

### Conclusion
- Provisioning de Loki dans Grafana validé.
- Communication HTTP Grafana vers Loki validée.
- Aucun port Loki publié directement vers Windows.
- Aucun changement de configuration nécessaire après le HTTP 503.

### Vérifications restantes
- Exécuter une requête de journaux depuis Grafana.
- Renouveler le mot de passe administrateur exposé lors
  d'un test précédent, sans inscrire sa valeur dans le rapport.
- Configurer l'accès HTTPS par Traefik.
- Vérifier la persistance après redémarrage.

## Sécurisation du mot de passe administrateur Grafana

### Problème identifié
Lors d'une tentative de vérification de l'API Grafana,
une commande utilisant BusyBox wget a échoué parce que
l'option --password n'était pas reconnue.

Le message d'erreur a affiché accidentellement l'ancien
mot de passe administrateur dans le terminal.

Par précaution, son renouvellement a été décidé avant
la configuration de l'accès HTTPS par Traefik.

### Vérifications préalables
- Grafana CLI disponible : version 12.3.0.
- Utilisateur du conteneur : UID 472.
- Groupe supplémentaire : 1000.
- Base SQLite accessible en écriture.

### Correction effectuée
Un nouveau mot de passe aléatoire de 48 caractères
a été généré avec Python et le module secrets.

Le fichier suivant a été mis à jour :
/opt/GroupeC/secrets/grafana_admin_password

Permissions appliquées : 640.
Le mot de passe n'a pas été affiché.

Le nouveau secret a ensuite été utilisé pour réinitialiser
le compte administrateur avec Grafana CLI.

Commande utilisée :
docker exec groupec-grafana sh -c '
  password=$(cat /run/secrets/grafana_admin_password)
  grafana cli admin reset-admin-password "$password"
'

### Résultat observé
Admin password changed successfully

### Conclusion
Le renouvellement du mot de passe administrateur
dans la base Grafana a réussi.

Le fichier secret et la base Grafana utilisent
le nouveau mot de passe généré.

### Précautions
- Ne jamais inscrire le mot de passe dans ce rapport.
- Ne pas publier le fichier secret sur GitHub.
- Ne pas afficher le secret dans les captures.
- Éviter de transmettre les secrets comme arguments
  de commandes lorsque cela peut être évité.

### Vérification restante
Confirmer une authentification réussie avec le
nouveau mot de passe.

## Validation de l'authentification administrateur Grafana

### Objectif
Vérifier que le nouveau mot de passe administrateur
fonctionne réellement après sa réinitialisation.

### Premier test
Une requête authentifiée a été envoyée vers :
http://127.0.0.1:8080/api/user

Résultat : HTTP 404.

Explication :
Le port 8080 correspond à Traefik et aucune route
Grafana n'avait encore été configurée.
Ce résultat ne permettait pas de conclure à un échec
de l'authentification.

### Test corrigé
Un conteneur temporaire python:3.12-alpine a été utilisé.

Configuration du test :
- Réseau Docker : GroupeC-applicatif
- UID : 472
- Groupe supplémentaire : 1000
- Secret Grafana monté en lecture seule
- Authentification HTTP Basic
- Adresse : http://groupec-grafana:3000/api/user

Le mot de passe a été lu depuis le fichier secret,
sans être affiché dans le terminal.

Résultat observé :
Code HTTP : 200
Authentification administrateur réussie.

### Conclusion
Le renouvellement du mot de passe administrateur
est validé par une authentification réelle.

Le compte administrateur fonctionne avec le nouveau secret.
Aucune suppression du volume Grafana n'a été nécessaire.
Le conteneur temporaire de test a été supprimé automatiquement.

### Prochaines étapes
- Vérifier la consultation des journaux dans Grafana.
- Configurer l'accès HTTPS par Traefik.
- Vérifier la persistance après redémarrage.

## Étape 6.25 — Validation de la requête Loki depuis Grafana

### Objectif
Vérifier que Grafana peut interroger Loki à travers la source de données
Loki configurée automatiquement, sans accès HTTPS externe.

### Méthode
Un programme Python temporaire a été créé dans :
/tmp/test-grafana-loki.py

Le programme a été exécuté dans un conteneur temporaire
python:3.12-alpine connecté au réseau GroupeC-applicatif.

Le mot de passe administrateur Grafana a été lu depuis le fichier
de secret monté en lecture seule, sans afficher sa valeur.

La requête a été envoyée à l'API Grafana :
http://groupec-grafana:3000/api/datasources/proxy/uid/loki/loki/api/v1/labels

Grafana a transmis la requête à Loki.

### Résultats observés
Code HTTP : 200
Statut Loki : success
Étiquettes retournées : filename, job, service_name, stream

### Conclusion
La communication entre Grafana et Loki à travers l'API Grafana
et la source de données provisionnée est fonctionnelle.

Cette vérification confirme l'accès aux étiquettes Loki depuis Grafana.
Elle ne constitue pas encore une validation de l'affichage des lignes
de journaux dans l'interface graphique.

### Prochaine vérification
Consulter les journaux collectés par Alloy à travers Grafana.


## Étape 6.26 — Consultation des lignes de journaux depuis Grafana

### Objectif
Vérifier que Grafana peut récupérer les lignes de journaux
collectées par Alloy et stockées dans Loki.

### Méthode
Création du programme temporaire :
/tmp/test-grafana-journaux.py

Exécution dans un conteneur temporaire python:3.12-alpine,
connecté au réseau GroupeC-applicatif.

Le secret administrateur Grafana a été monté en lecture seule.
Sa valeur n'a pas été affichée.

La requête a utilisé l'API Grafana et la source Loki
provisionnée, avec le sélecteur :
{job="docker-rootless"}

La période interrogée correspondait à la dernière heure,
avec une limite de cinq lignes et un tri du plus récent
au plus ancien.

### Résultats observés
Code HTTP : 200
Statut Loki : success
Nombre de lignes récupérées : 5
Nombre de flux de journaux : 2

### Conclusion
Grafana a réussi à interroger Loki et à récupérer cinq
lignes de journaux réparties dans deux flux.

Le fonctionnement de la requête de consultation via
l'API Grafana est confirmé.

L'affichage dans l'interface graphique Grafana reste
à vérifier.

### Prochaine étape
Préparer l'accès HTTPS à Grafana par Traefik afin
de permettre la consultation dans l'interface graphique.


## Étape 6.27 — Intégration HTTPS de Grafana avec Traefik

### Objectif
Permettre l'accès à Grafana par HTTPS à travers Traefik,
sans publier directement le port 3000 de Grafana.

### Configuration existante vérifiée
Traefik version 3.5.6.
Point d'entrée HTTPS : websecure, port interne 443.
Port HTTPS publié sur la VM : 127.0.0.1:4443.
Réseau commun Traefik/Grafana : GroupeC-applicatif.
Certificat TLS local autosigné.
Authentification Basic Auth existante : dashboard-auth.

### Travaux réalisés
Examen des fichiers docker-compose.yml, routes.yaml et tls.yaml.
Vérification des réseaux Docker et du fichier users.htpasswd.

Une première tentative d'ajout dans routes.yaml a provoqué
des erreurs d'indentation YAML et de chargement Traefik.
La configuration initiale a été restaurée depuis sa sauvegarde.
Les routes demo et dashboard ont ensuite été retestées.

La sauvegarde a été déplacée hors du dossier dynamic.

Création d'un fichier indépendant :
/opt/GroupeC/traefik/dynamic/grafana.yaml

Ce fichier définit :
- Le routeur grafana pour grafana.localhost.
- Le point d'entrée HTTPS websecure.
- Le middleware dashboard-auth.
- Le service grafana-service vers http://groupec-grafana:3000.

Le fichier routes.yaml original a été conservé.

### Résultats des tests HTTPS
grafana.localhost/login : HTTP 401.
demo.localhost/ : HTTP 200.
dashboard.localhost/dashboard/ : HTTP 401.

### Interprétation
La route HTTPS Grafana est reconnue et exige une authentification.
Les deux routes existantes continuent de fonctionner.

### Limite
L'accès authentifié complet à Grafana par Traefik n'a pas
encore été testé, car les identifiants Basic Auth ne sont
pas disponibles.

Le mot de passe existant n'a pas été modifié.
La validation de l'accès au service Grafana après
authentification reste à effectuer avec le responsable
de l'intégration.

### Conclusion
L'intégration HTTPS de Grafana est partiellement validée.
Le routage et le contrôle d'accès répondent comme prévu,
mais l'accès authentifié au service final reste à confirmer.


## Étape 6.28 — Vérification après redémarrage de Grafana

### Objectif
Vérifier que Grafana redémarre correctement et conserve
sa capacité à interroger Loki, sans suppression du volume Docker.

### Configuration de stockage
Le conteneur groupec-grafana utilise le volume Docker
grafana_grafana_data, monté sur /var/lib/grafana.

Deux autres montages sont présents :
- Le fichier du mot de passe administrateur.
- Le dossier de provisioning des sources de données.

### Commandes exécutées

Vérification des montages :
docker inspect groupec-grafana --format '{{range .Mounts}}{{println .Type .Name .Destination}}{{end}}'

Redémarrage :
docker restart groupec-grafana

Contrôle de santé :
docker exec groupec-grafana wget -qO- http://127.0.0.1:3000/api/health

Test de communication Grafana vers Loki :
Exécution du script /tmp/test-grafana-loki.py
dans un conteneur Python temporaire, sur le réseau
GroupeC-applicatif, avec lecture du secret en montage
lecture seule et sans affichage du mot de passe.

### Résultats observés
- Redémarrage du conteneur réussi.
- Base de données Grafana : ok.
- Version Grafana : 12.3.0.
- Requête Loki via l'API Grafana : HTTP 200.
- Statut Loki : success.
- Étiquettes retournées :
  filename, job, service_name, stream.

### Interprétation
Grafana fonctionne après le redémarrage du conteneur.
La communication avec Loki reste opérationnelle.
Le volume de données n'a pas été supprimé.

### Limites
La conservation d'une donnée créée dans Grafana avant
le redémarrage n'a pas encore été vérifiée directement.

La source Loki étant configurée par provisioning,
sa disponibilité après redémarrage ne prouve pas,
à elle seule, la persistance de toutes les données Grafana.

Aucun test de restauration après perte du volume
Docker n'a été effectué.

### Conclusion
Le redémarrage de Grafana et le fonctionnement de
l'intégration Loki après redémarrage sont validés.
La persistance d'une donnée utilisateur reste à tester.


## Étape 6.29 — Validation de la persistance des données Grafana

### Objectif
Démontrer qu'une donnée créée dans Grafana est conservée
après le redémarrage de son conteneur Docker Rootless.

### Stockage utilisé
Volume Docker : grafana_grafana_data
Montage dans le conteneur : /var/lib/grafana

Emplacement du volume sur Ubuntu :
/home/ubuntu/.local/share/docker/volumes/grafana_grafana_data/_data

### Préparation du test
Vérification de la disponibilité de l'API Grafana.

Création de deux scripts temporaires :
- /tmp/creer-dossier-grafana.py
- /tmp/verifier-dossier-grafana.py

Les scripts utilisent l'API Grafana depuis un conteneur
Python temporaire sur le réseau GroupeC-applicatif.

Le mot de passe administrateur est lu depuis un fichier
monté en lecture seule, sans affichage de sa valeur.

### Donnée de test
Type : dossier Grafana
Identifiant : tp-persistance-629
Nom : TP CR465 - Test persistance

### Tests effectués

1. Vérification de l'absence du dossier.
Résultat : HTTP 404, dossier absent.

2. Création du dossier par l'API Grafana.
Résultat : HTTP 200.
Identifiant retourné : tp-persistance-629.

3. Vérification avant redémarrage.
Résultat : HTTP 200, dossier présent.

4. Redémarrage du conteneur :
docker restart groupec-grafana

Résultat : redémarrage réussi.

5. Vérification après redémarrage.
Résultat : HTTP 200.
Le dossier tp-persistance-629 est toujours présent.

### Interprétation
Une donnée créée dans Grafana a été retrouvée après
le redémarrage du conteneur.

Ce résultat valide la persistance de cette donnée
dans le scénario testé, avec conservation du volume Docker.

### Limites
Le volume Docker n'a pas été supprimé.
Aucun test de sauvegarde ou de restauration après perte
du volume n'a été effectué.

Le dossier de test est conservé pour le moment.

### Conclusion
La persistance d'une donnée Grafana après redémarrage
du conteneur est validée.


## Étape 6.30 — Vérification des journaux récents dans Loki

### Objectif
Vérifier que les journaux récents produits par Grafana
sont collectés et disponibles dans Loki.

### Configuration Alloy vérifiée
Fichier : /etc/alloy/config.alloy

La configuration comprend :
- local.file_match : recherche des fichiers /journaux/*/*-json.log.
- loki.source.file : lecture des journaux Docker.
- loki.process : extraction du message, de la date et du flux.
- loki.write : transmission vers groupec-loki:3100.

### Vérification de la source
Commande :
docker logs --tail 10 groupec-grafana 2>&1

Résultat :
Présence de messages récents du 18 novembre 2026,
notamment "Completed cleanup jobs" à 17:54:41 UTC.

### Vérification dans Loki
Requête Loki query_range avec :
- job = docker-rootless
- Filtre de contenu = Completed cleanup jobs
- Limite = 10 lignes

Résultats :
- Statut : success.
- Type : streams.
- Nombre de lignes retournées : 5.
- Message récent de Grafana retrouvé dans Loki.
- Présence des étiquettes job, filename et stream.

### Interprétation
Un message récent observé directement dans les journaux
du conteneur Grafana a également été retrouvé dans Loki.

Ce résultat confirme que la chaîne de collecte
Docker Rootless vers Alloy puis Loki fonctionne
pour les journaux Grafana dans le scénario testé.

### Limites
Ce test ne démontre pas que tous les conteneurs
sont collectés sans exception.

La consultation des journaux dans l'interface graphique
Grafana reste à vérifier après authentification Traefik.

### Conclusion
La présence de journaux récents de Grafana dans Loki
est confirmée.


## Étape 6.31 — Adaptation de la route Grafana à la configuration HTTPS du groupe

### Objectif
Adapter la route Traefik de Grafana aux décisions
prises avec le responsable de l'intégration.

### Décision du groupe
Traefik assure le routage HTTPS des applications.
Chaque application gère sa propre authentification.
Le middleware dashboard-auth est réservé au tableau
de bord d'administration Traefik pour le moment.

### Modifications effectuées
Fichier :
/opt/GroupeC/traefik/dynamic/grafana.yaml

- Remplacement de grafana.localhost par grafana.groupec.test.
- Suppression du middleware dashboard-auth sur Grafana.
- Conservation de l'entryPoint websecure.
- Conservation du service grafana-service.
- Conservation de l'adresse interne :
  http://groupec-grafana:3000

### Test effectué
Commande :
curl -sk -o /dev/null -w 'Code HTTP : %{http_code}\n' \
  --resolve grafana.groupec.test:4443:127.0.0.1 \
  https://grafana.groupec.test:4443/login

Résultat observé :
Code HTTP : 200

### Interprétation
La route HTTPS Grafana répond correctement depuis
la VM, sans authentification BasicAuth préalable.

### Limites
Le test utilise -k pour ignorer la validation du
certificat TLS. Il ne prouve donc pas que le
certificat sera reconnu par le navigateur.

L'accès depuis Windows et depuis les autres postes
du groupe reste à vérifier.

La publication du port 4443, les règles UFW,
la configuration réseau QEMU et la résolution du
nom grafana.groupec.test doivent être vérifiées
selon l'environnement final.

L'authentification utilisateur dans Grafana reste
également à tester depuis le navigateur.

### Conclusion
Le routage HTTPS local de Grafana est validé
avec la nouvelle adresse prévue par le groupe.

## Étape 6.32 — Modification de la publication HTTPS de Traefik

### Objectif
Adapter la publication du port HTTPS de Traefik
à la configuration prévue pour l'intégration du groupe.

### Configuration initiale
Dans /opt/GroupeC/traefik/docker-compose.yml :
127.0.0.1:4443:443

Cette configuration limitait la publication du port
HTTPS à l'interface locale de la VM Ubuntu.

### Modification effectuée
Une sauvegarde du fichier Compose a été créée :
docker-compose.yml.bak-avant-https

La publication du port HTTPS a été remplacée par :
4443:443

Les publications des ports 8080 et 8081
ont été conservées sans modification.

### Vérifications
La commande docker compose config --quiet
s'est terminée sans erreur.

Le conteneur Traefik a été recréé avec :
docker compose -f /opt/GroupeC/traefik/docker-compose.yml up -d --no-deps --force-recreate traefik

La commande :
docker port groupec-traefik-traefik-1 443

a retourné :
0.0.0.0:4443
[::]:4443

### Test HTTPS après recréation
Commande :
curl -sk -o /dev/null -w 'Code HTTP : %{http_code}\n' \
  --resolve grafana.groupec.test:4443:127.0.0.1 \
  https://grafana.groupec.test:4443/login

Résultat :
Code HTTP : 200

### Interprétation
La publication HTTPS de Traefik est maintenant
active sur les interfaces IPv4 et IPv6 de la VM.

Le routage HTTPS vers Grafana fonctionne toujours
après la recréation du conteneur Traefik.

### Limites
Le test a été réalisé depuis Ubuntu avec
validation du certificat TLS désactivée (-k).

L'accès depuis Windows n'est pas encore validé.
Le script de démarrage QEMU ne contient actuellement
qu'une redirection pour SSH (port 2224).

Une redirection du port HTTPS 4443 devra être
ajoutée dans QEMU pour permettre l'accès depuis
le navigateur Windows.

La publication sur toutes les interfaces de la VM
nécessite un contrôle des accès réseau et du pare-feu
avant tout accès depuis d'autres postes.

### Conclusion
La modification de la publication HTTPS de Traefik
est appliquée et validée localement.

## Étape 6.33 — Accès HTTPS à Grafana depuis Windows via QEMU

### Objectif
Permettre l'accès HTTPS à Grafana depuis Windows
en utilisant la redirection de ports QEMU.

### Configuration QEMU
Disque de travail :
groupec-qemu-v2.img

Redirections :
- Windows 127.0.0.1:2224 vers Ubuntu 22 (SSH)
- Windows 127.0.0.1:4443 vers Ubuntu 4443 (HTTPS)

La redirection HTTPS a été ajoutée à la commande
de démarrage QEMU utilisée avec le disque v2.

### Incident rencontré
Un ancien script demarrer-groupec.ps1 pointait vers
groupec-qemu.img au lieu de groupec-qemu-v2.img.

Son utilisation a provoqué un avertissement SSH
concernant un changement d'empreinte du serveur.

La VM de travail a été retrouvée en redémarrant
QEMU avec le disque groupec-qemu-v2.img.

Les six conteneurs ont été retrouvés en fonctionnement.

### Diagnostic HTTPS
Test TCP Windows vers 127.0.0.1:4443 :
TcpTestSucceeded : True

Premiers tests HTTPS Windows :
connexion réinitialisée ou expiration.

Tests HTTPS depuis Ubuntu :
- 127.0.0.1:4443 : HTTP 200
- 10.0.2.15:4443 : HTTP 200

### Pare-feu UFW
UFW était actif avec refus des connexions
entrantes par défaut.

Seul le port SSH 22 était initialement autorisé.

Règle ajoutée :
sudo ufw allow in on enp0s2 from 10.0.2.2 \
  to 10.0.2.15 port 4443 proto tcp \
  comment 'Test HTTPS QEMU Grafana'

### Validation depuis Windows
Commande :
curl.exe -sk --noproxy "*" --resolve \
  "grafana.groupec.test:4443:127.0.0.1" \
  -o NUL -w "Code HTTP : %{http_code}\n" \
  "https://grafana.groupec.test:4443/login"

Résultat observé :
Code HTTP : 200

### Conclusion
L'accès HTTPS à la page de connexion Grafana
fonctionne depuis Windows à travers QEMU,
UFW et Traefik.

### Limites et précautions
Le test utilise -k : le certificat TLS n'a
pas été validé.

La résolution du nom grafana.groupec.test
a été forcée temporairement avec --resolve.

L'accès depuis Firefox reste à tester.

La règle UFW autorise la passerelle virtuelle
QEMU 10.0.2.2, et non une liste de postes
Windows individuels.

Le déploiement final devra adapter les règles
réseau et les certificats à son environnement.

Le script demarrer-groupec.ps1 doit être corrigé
pour utiliser le disque v2 avant toute réutilisation.

## Étape 6.34 — Vérification de Grafana et Loki après redémarrage de la VM

### Objectif
Vérifier que Grafana et Loki restent accessibles et
fonctionnels après le redémarrage de la VM QEMU.

### Vérification de Grafana
La VM a été redémarrée avec le disque groupec-qemu-v2.img.

Les six conteneurs Docker ont été retrouvés en fonctionnement :
- Traefik
- Grafana
- Alloy
- Loki
- Nginx (webtest)
- PostgreSQL (healthy)

L'accès HTTPS à Grafana a été testé depuis Ubuntu
à travers Traefik.

Résultat : HTTP 200.

La page de connexion Grafana a également été
affichée dans Firefox depuis Windows.

### Configuration de la source Loki
Le fichier suivant a été consulté :
/etc/grafana/provisioning/datasources/loki.yml

Paramètres confirmés :
- Nom : Loki
- UID : loki
- Type : loki
- Adresse : http://groupec-loki:3100
- Source par défaut : true
- Modification dans Grafana : désactivée

### Premier test de disponibilité Loki
Commande :
docker exec groupec-grafana wget -qO- http://groupec-loki:3100/ready

Résultat :
HTTP/1.1 503 Service Unavailable

Grafana pouvait contacter Loki, mais Loki
ne se déclarait pas prêt à ce moment.

### Examen des journaux Loki
Commande :
docker logs --tail 40 groupec-loki 2>&1

Les journaux montraient notamment :
- Des opérations sur les tables d'index
- Des traitements de flux terminés
- Des checkpoints terminés
- Des opérations de compactage

Aucune erreur explicite n'a été observée
dans les 40 dernières lignes examinées.

### Nouveau test de disponibilité
Commande :
docker exec groupec-grafana \
  wget -S -O- http://groupec-loki:3100/ready 2>&1

Résultat :
HTTP/1.1 200 OK
ready

### Conclusion
La communication réseau entre Grafana et Loki
est fonctionnelle après redémarrage de la VM.

Loki a initialement retourné HTTP 503, puis
HTTP 200 avec le message ready.

Aucune modification de configuration n'a
été nécessaire entre les deux vérifications.

### Limites
La cause exacte du premier HTTP 503
n'a pas été déterminée.

Le test /ready confirme la disponibilité
de Loki, mais ne démontre pas à lui seul
que les journaux sont consultables dans Grafana.

La consultation des journaux dans l'interface
graphique Grafana reste à vérifier.

### Certificat HTTPS
Firefox a affiché le certificat
TRAEFIK DEFAULT CERT.

La connexion HTTPS fonctionne, mais
la confiance dans le certificat n'est pas établie.

La configuration définitive du certificat
relève de l'intégration commune avec Traefik.

## Étape 6.35 — Accès à Grafana depuis Windows et Firefox

### Objectif
Permettre l'accès à l'interface graphique Grafana
depuis le navigateur Firefox du poste Windows,
en passant par QEMU, Ubuntu et Traefik.

### 1. Architecture de l'accès
Le chemin utilisé est le suivant :

Firefox sur Windows
  -> grafana.groupec.test:4443
  -> 127.0.0.1:4443 sur Windows
  -> Redirection réseau QEMU
  -> Ubuntu, port 4443
  -> Traefik, port HTTPS interne 443
  -> Conteneur groupec-grafana, port 3000

Grafana n'est pas publié directement sur Windows.
Traefik constitue le point d'entrée HTTPS.

### 2. Configuration réseau QEMU
Le disque de travail utilisé est :
groupec-qemu-v2.img

Les redirections configurées dans QEMU sont :
- 127.0.0.1:2224 vers Ubuntu 22 (SSH)
- 127.0.0.1:4443 vers Ubuntu 4443 (HTTPS)

Le port 4443 du conteneur Traefik est publié
sur le port 4443 de la VM Ubuntu.

### 3. Pare-feu Ubuntu
UFW était actif et refusait par défaut
les connexions entrantes non autorisées.

La passerelle QEMU a été identifiée :
10.0.2.2

La VM utilisait :
10.0.2.15

Règle UFW ajoutée pour le test :
sudo ufw allow in on enp0s2 from 10.0.2.2 \
  to 10.0.2.15 port 4443 proto tcp \
  comment 'Test HTTPS QEMU Grafana'

Après cet ajout, l'accès HTTPS depuis Windows
a fonctionné.

### 4. Validation HTTPS depuis Windows
Test du port TCP dans PowerShell :
Test-NetConnection 127.0.0.1 -Port 4443

Résultat :
TcpTestSucceeded : True

Test HTTPS avec résolution temporaire :
curl.exe -sk --noproxy "*" \
  --resolve "grafana.groupec.test:4443:127.0.0.1" \
  -o NUL -w "Code HTTP : %{http_code}\n" \
  "https://grafana.groupec.test:4443/login"

Résultat :
Code HTTP : 200

Ce test a confirmé que Windows pouvait
atteindre la page de connexion Grafana
par QEMU et Traefik.

### 5. Résolution du nom sous Windows
Le nom grafana.groupec.test ne pouvait pas
initialement être résolu par Windows.

Commande de vérification :
Resolve-DnsName grafana.groupec.test \
  -ErrorAction SilentlyContinue

Aucune adresse IP n'a été retournée.

Le fichier hosts a été vérifié :
C:\Windows\System32\drivers\etc\hosts

Aucune entrée grafana.groupec.test
n'y était présente.

Une fenêtre PowerShell administrateur
a été utilisée pour ajouter la ligne :

127.0.0.1 grafana.groupec.test

Cette association est propre au poste Windows
utilisé pour les tests.

### 6. Vérification de la résolution
Commande PowerShell :
[System.Net.Dns]::GetHostAddresses(
  "grafana.groupec.test"
) | Select-Object IPAddressToString

Résultat :
127.0.0.1

La résolution locale du nom a été confirmée.

### 7. Test dans Firefox
Adresse utilisée :
https://grafana.groupec.test:4443/login

Résultats observés :
- La page de connexion Grafana s'affiche.
- Le formulaire demande un nom d'utilisateur
  et un mot de passe Grafana.
- Aucune authentification BasicAuth Traefik
  supplémentaire n'a été demandée.

Un essai avec http:// au lieu de https://
a affiché une erreur 404.
L'adresse HTTPS correcte a ensuite été utilisée.

### 8. Vérification du certificat TLS
Firefox a initialement affiché
un avertissement de sécurité.

Après acceptation d'une exception,
la page Grafana s'est ouverte directement.

Les informations de sécurité Firefox
indiquaient :
- Site : grafana.groupec.test
- Certificat présenté : TRAEFIK DEFAULT CERT
- Protocole : TLS 1.3
- Suite : TLS_AES_128_GCM_SHA256

La connexion était chiffrée, mais Firefox
affichait encore la mention Non sécurisé.

Le certificat présenté n'établissait donc
pas une identité de serveur reconnue.

### 9. Résultat général
L'accès à la page de connexion Grafana
depuis Firefox sur Windows est validé.

Le chemin Windows -> QEMU -> Ubuntu ->
Traefik -> Grafana fonctionne.

### 10. Limites
La connexion avec les identifiants Grafana
n'a pas encore été testée dans Firefox.

La consultation des journaux Loki dans
l'interface graphique reste à vérifier.

La confiance dans le certificat TLS
n'est pas établie.

La résolution du nom repose actuellement
sur le fichier hosts du poste Windows.

L'accès depuis les autres postes du groupe
n'a pas été testé.

La configuration finale des domaines,
du certificat TLS et des règles réseau
devra être coordonnée avec l'intégrateur.


## Étape 6.36 — Consultation des journaux Loki par l'API Grafana

### Objectif
Vérifier que Grafana peut interroger Loki et récupérer
des messages de journaux après redémarrage de la VM.

### Méthode
Un conteneur Python temporaire a été lancé sur
le réseau Docker GroupeC-applicatif.

Le mot de passe administrateur Grafana a été lu
depuis un fichier secret monté en lecture seule.

L'authentification auprès de l'API Grafana
a été réalisée sans afficher le mot de passe.

La requête a utilisé la source de données
Loki configurée dans Grafana.

Endpoint utilisé :
/api/datasources/proxy/uid/loki/loki/api/v1/query_range

Paramètres :
- Sélecteur LogQL : {job="docker-rootless"}
- Période : dernières 24 heures
- Limite : 5 messages
- Ordre : du plus récent au plus ancien

### Résultats observés
Code HTTP Grafana : 200
Statut Loki : success
Nombre de flux : 2
Nombre de messages récupérés : 5

Les messages provenaient notamment :
- Du conteneur Alloy
- Du conteneur Loki

Exemples de messages observés :
- tail routine: started
- checkpoint done
- completed recalculate owned streams job

### Conclusion
Grafana peut interroger Loki par son API
et récupérer des journaux Docker Rootless.

La récupération des journaux fonctionne
après redémarrage de la VM.

La chaîne de collecte Alloy -> Loki
et la consultation Loki -> Grafana
sont validées pour les flux testés.

### Limites
Le test ne démontre pas que tous les conteneurs
du groupe sont collectés sans exception.

La consultation dans l'interface graphique
Grafana n'a pas été réalisée.

Les requêtes ont été effectuées par API,
sans connexion dans Firefox, en raison
de la confiance non établie dans le certificat TLS.

Aucun test de restauration après perte
des volumes Docker n'a été réalisé.

## Étape 7.1 — Préparation de Grafana pour l'intégration GitHub

### Objectif
Vérifier que les fichiers Grafana permettent
de reproduire son installation dans le projet GroupeC.

### Fichiers nécessaires
- grafana/docker-compose.yml
- grafana/provisioning/datasources/loki.yml

Les anciennes versions du fichier Compose
ne sont pas nécessaires à la contribution GitHub.

### Correction effectuée
Le commentaire de routage Traefik a été actualisé.

Ancienne adresse :
grafana.localhost

Nouvelle adresse :
grafana.groupec.test

Service interne :
http://groupec-grafana:3000

### Réseaux Docker
Le conteneur Grafana est connecté à :
- GroupeC-applicatif
- GroupeC-observabilite

Ces réseaux sont déclarés external: true.
Ils doivent donc exister avant le démarrage de Grafana.

### Secret administrateur
Fichier :
/opt/GroupeC/secrets/grafana_admin_password

Permissions observées :
640

Propriétaire :
ubuntu

Groupe :
ubuntu

Le conteneur Grafana peut lire ce secret
avec sa configuration actuelle.

Le fichier Compose utilise :
GF_SECURITY_ADMIN_PASSWORD__FILE

Le mot de passe n'est pas inscrit en clair
dans le fichier Compose.

### Reproductibilité
Le script commun /opt/GroupeC/deploy.sh
prévoit déjà la génération automatique
du secret grafana_admin_password.

Cependant, il applique actuellement
les permissions 0644 aux secrets ordinaires.

Correction proposée à l'intégrateur :
chmod 0640 "$ROOT/secrets/grafana_admin_password"

Cette correction n'a pas encore été appliquée
au script commun.

### Limites
La reconstruction complète de Grafana
sur une nouvelle VM n'a pas encore été testée.

Les réseaux externes doivent être créés
par le processus de déploiement.

La configuration du routage HTTPS et
des certificats relève de l'intégration commune.

### Conclusion
Les fichiers principaux de Grafana sont présents.

Les réseaux et la lecture du secret
ont été vérifiés sur l'installation actuelle.

Une adaptation des permissions dans deploy.sh
reste à coordonner avec l'intégrateur.

## Étape 7.1 — Préparation de Grafana pour l'intégration GitHub

### Objectif
Vérifier que les fichiers Grafana permettent
de reproduire son installation dans le projet GroupeC.

### Fichiers nécessaires
- grafana/docker-compose.yml
- grafana/provisioning/datasources/loki.yml

Les anciennes versions du fichier Compose
ne sont pas nécessaires à la contribution GitHub.

### Correction effectuée
Le commentaire de routage Traefik a été actualisé.

Ancienne adresse :
grafana.localhost

Nouvelle adresse :
grafana.groupec.test

Service interne :
http://groupec-grafana:3000

### Réseaux Docker
Le conteneur Grafana est connecté à :
- GroupeC-applicatif
- GroupeC-observabilite

Ces réseaux sont déclarés external: true.
Ils doivent donc exister avant le démarrage de Grafana.

### Secret administrateur
Fichier :
/opt/GroupeC/secrets/grafana_admin_password

Permissions observées :
640

Propriétaire :
ubuntu

Groupe :
ubuntu

Le conteneur Grafana peut lire ce secret
avec sa configuration actuelle.

Le fichier Compose utilise :
GF_SECURITY_ADMIN_PASSWORD__FILE

Le mot de passe n'est pas inscrit en clair
dans le fichier Compose.

### Reproductibilité
Le script commun /opt/GroupeC/deploy.sh
prévoit déjà la génération automatique
du secret grafana_admin_password.

Cependant, il applique actuellement
les permissions 0644 aux secrets ordinaires.

Correction proposée à l'intégrateur :
chmod 0640 "$ROOT/secrets/grafana_admin_password"

Cette correction n'a pas encore été appliquée
au script commun.

### Limites
La reconstruction complète de Grafana
sur une nouvelle VM n'a pas encore été testée.

Les réseaux externes doivent être créés
par le processus de déploiement.

La configuration du routage HTTPS et
des certificats relève de l'intégration commune.

### Conclusion
Les fichiers principaux de Grafana sont présents.

Les réseaux et la lecture du secret
ont été vérifiés sur l'installation actuelle.

Une adaptation des permissions dans deploy.sh
reste à coordonner avec l'intégrateur.

## Étape 7.2 — Préparation de Loki pour l'intégration GitHub

### Objectif
Vérifier les fichiers et les dépendances nécessaires
pour reproduire l'installation de Loki dans GroupeC.

### Fichiers nécessaires
- loki/docker-compose.yml
- loki/loki-config.yaml

Le fichier docker-compose.yml.original est une
sauvegarde locale qui n'est pas nécessaire
à la contribution GitHub.

### Corrections effectuées
Les anciens commentaires du fichier Compose
ont été actualisés.

Le commentaire indiquant un emplacement réservé
a été remplacé par une description de Loki installé.

L'adresse interne utilisée par Grafana est :
http://groupec-loki:3100

### Configuration Docker
Image : grafana/loki:3.5.5
Conteneur : groupec-loki
Redémarrage : unless-stopped
Limite mémoire : 768m
Port HTTP interne : 3100

Le port 3100 n'est pas publié sur l'hôte.

### Réseau
Loki utilise le réseau externe :
GroupeC-observabilite

Ce réseau doit exister avant le démarrage
du conteneur.

### Stockage
Volume Docker :
loki_loki_data

Point de montage :
/loki

Le fichier loki-config.yaml est monté
dans /etc/loki/loki-config.yaml en lecture seule.

Le stockage utilise le système de fichiers local
avec le schéma TSDB v13.

### Rétention
Durée configurée : 168 heures, soit 7 jours.

Le compacteur est configuré pour appliquer
la politique de rétention.

La suppression effective des journaux
après 7 jours n'a pas été vérifiée.

### Sécurité
L'authentification interne Loki est désactivée.

Loki reste accessible sur le réseau Docker
d'observabilité, sans publication directe
de son port HTTP sur l'hôte.

### Tests réalisés
La disponibilité de Loki a été confirmée
par HTTP 200 avec le message ready.

Grafana a réussi à interroger Loki par API
et à récupérer des journaux.

Le volume persistant a été confirmé
par docker inspect.

Validation du fichier Compose :
docker compose -f /opt/GroupeC/loki/docker-compose.yml config --quiet

Résultat :
Configuration Compose Loki valide

### Limites
La reconstruction sur une nouvelle VM
n'a pas encore été testée.

La restauration des données après perte
du volume Docker n'a pas été testée.

La rétention effective après 7 jours
reste à vérifier.

### Conclusion
Les deux fichiers principaux de Loki sont présents.

La configuration Compose est valide et les
dépendances réseau et stockage sont identifiées.

Loki est prêt à être préparé pour la contribution
GitHub, sous réserve de l'intégration commune
et des tests de reproductibilité.

## Étape 7.3 — Préparation d'Alloy pour l'intégration GitHub

### Objectif
Vérifier les fichiers, les montages et les dépendances
nécessaires pour reproduire Alloy dans le projet GroupeC.

### Fichiers nécessaires
- alloy/docker-compose.yml
- alloy/config.alloy

### Configuration Docker
Image : grafana/alloy:v1.10.2
Conteneur : groupec-alloy
Redémarrage : unless-stopped
Limite mémoire : 512m

Le port d'administration Alloy n'est pas publié.

### Docker Rootless
Commande de vérification :
docker info --format '{{.DockerRootDir}}'

Résultat :
/home/ubuntu/.local/share/docker

Le chemin des journaux utilisé par Alloy correspond
à l'emplacement Docker Rootless de la VM actuelle.

### Montages vérifiés
1. Journaux Docker :
/home/ubuntu/.local/share/docker/containers
vers /journaux
Lecture seule : oui

2. Configuration Alloy :
./config.alloy
vers /etc/alloy/config.alloy
Lecture seule : oui

3. Volume persistant :
alloy_alloy_data
vers /var/lib/alloy/data
Lecture et écriture : oui

### Réseau
Alloy utilise le réseau Docker externe :
GroupeC-observabilite

Ce réseau doit exister avant son démarrage.

### Configuration de collecte
Le fichier config.alloy utilise :
- local.file_match pour identifier les fichiers
- loki.source.file pour lire les journaux
- loki.process pour traiter les messages JSON
- loki.write pour transmettre les journaux à Loki

Chemin recherché :
/journaux/*/*-json.log

Étiquette :
job = docker-rootless

Informations extraites :
- log : contenu du message
- stream : stdout ou stderr
- time : horodatage

Format temporel :
RFC3339Nano

### Destination Loki
Adresse configurée :
http://groupec-loki:3100/loki/api/v1/push

La transmission utilise le réseau Docker interne.

### Tests réalisés
Des journaux provenant notamment d'Alloy
et de Loki ont été récupérés par l'API Grafana.

Le fichier Compose a été validé avec :
docker compose -f /opt/GroupeC/alloy/docker-compose.yml config --quiet

Résultat :
Configuration Compose Alloy valide

### Dépendances pour l'intégration
- Docker Rootless doit être fonctionnel.
- Le chemin réel des journaux doit être vérifié.
- Le format de journalisation Docker doit être compatible.
- Le réseau GroupeC-observabilite doit exister.
- Loki doit être disponible pour recevoir les journaux.

### Limites
La collecte de tous les conteneurs du groupe
n'a pas été vérifiée individuellement.

La reconstruction d'Alloy sur une nouvelle VM
n'a pas encore été testée.

Le comportement après perte du volume Alloy
n'a pas été testé.

### Conclusion
Les fichiers nécessaires à Alloy sont présents.

Les montages et le chemin Docker Rootless
correspondent à l'installation actuelle.

La configuration Compose est valide.

Alloy peut être préparé pour l'intégration GitHub,
sous réserve des vérifications sur la VM commune.
