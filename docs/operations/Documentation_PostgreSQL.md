# Documentation technique — PostgreSQL

> Cours CR465 — Technologies et sécurité des conteneurs

> Documentation du déploiement, des vérifications et des limites observées.

## 1. Objectif et choix d'architecture PostgreSQL

### 1.1. Rôle de PostgreSQL dans notre plateforme

Dans le cadre de notre travail de session, nous devons déployer une plateforme composée de plusieurs applications conteneurisées, notamment Moodle, Drupal Commerce, Keycloak et n8n.

Ces applications ont besoin de conserver certaines informations de manière durable, par exemple les comptes utilisateurs, les paramètres de configuration, les données applicatives et les informations nécessaires à leur fonctionnement.

Nous avons choisi PostgreSQL 17 comme système de gestion de bases de données relationnelles (SGBDR) pour les applications compatibles avec ce moteur.

PostgreSQL sera déployé dans un conteneur Docker et ses données seront conservées dans un volume persistant.

L'objectif est de séparer le fonctionnement du conteneur du stockage des données, afin de pouvoir recréer le conteneur sans perdre les bases de données.

### 1.2. Pourquoi une seule instance PostgreSQL ?

Nous avons choisi de commencer avec une instance PostgreSQL unique, plutôt que de déployer un serveur PostgreSQL pour chaque application.

Ce choix présente plusieurs avantages dans le contexte de notre travail :

Simplicité de déploiement : nous avons un seul service PostgreSQL à installer, configurer et administrer.

Utilisation des ressources : notre plateforme fonctionne dans une machine virtuelle dont les ressources sont limitées. Une seule instance évite de multiplier inutilement les serveurs de bases de données.

Administration centralisée : les vérifications, la maintenance et la surveillance du service peuvent être regroupées.

Architecture plus facile à comprendre : cette organisation simplifie la présentation de notre solution et de ses communications réseau.

Ce choix ne signifie toutefois pas que toutes les applications partageront la même base de données.

### 1.3. Séparation des bases de données et des utilisateurs

Notre architecture prévoit de créer une base de données distincte pour chaque application qui utilisera PostgreSQL.

Nous prévoyons également de créer des comptes PostgreSQL distincts, avec les permissions nécessaires à chaque application.

Par exemple, une application ne devrait pas pouvoir consulter ou modifier les données d'une autre application simplement parce qu'elles utilisent le même serveur PostgreSQL.

Cette organisation applique le principe du moindre privilège : chaque compte doit disposer uniquement des droits nécessaires à son fonctionnement.

À ce stade, les bases et comptes applicatifs distincts sont prévus, mais n'ont pas encore été créés ni testés.

### 1.4. Limite : le point de défaillance unique

Notre choix d'une seule instance PostgreSQL comporte une limite importante : le point de défaillance unique, également appelé Single Point of Failure (SPOF).

Toutes les applications qui utilisent cette instance dépendent du même serveur de bases de données.

Si PostgreSQL devient indisponible, par exemple à cause d'une panne du service, d'un problème de stockage ou d'un incident sur la machine virtuelle, plusieurs applications pourraient perdre simultanément l'accès à leurs données.

Même si les bases de données sont séparées, elles restent hébergées par la même instance PostgreSQL.

Notre architecture ne constitue donc pas une solution de haute disponibilité.

Nous avons néanmoins retenu ce choix pour notre environnement pédagogique, en raison de sa simplicité et des ressources disponibles. Nous devons toutefois reconnaître cette limite et prévoir des mesures pour réduire les conséquences d'une panne.

### 1.5. Mesures retenues ou prévues pour réduire les risques

Notre architecture repose sur une seule instance PostgreSQL. Cela simplifie l'installation, mais signifie également que plusieurs applications pourraient être touchées si ce serveur rencontre un problème.

Pour réduire certains risques, nous avons configuré plusieurs mécanismes de protection et prévu des mesures supplémentaires.

Ces mécanismes n'ont cependant pas tous le même objectif. Certains servent à conserver les données, d'autres à protéger les accès ou à faciliter le redémarrage du service.

#### 1.5.1. Conserver les données grâce à un volume persistant

Le risque : lorsqu'un conteneur Docker est supprimé, les données enregistrées uniquement dans son système de fichiers peuvent disparaître avec lui.

Pour éviter que les bases de données dépendent de la durée de vie du conteneur, nous avons configuré un volume Docker nommé postgres-data, qui apparaît sous le nom compose_postgres-data dans notre environnement.

Ce volume est monté dans le conteneur PostgreSQL au chemin /var/lib/postgresql/data.

Ainsi, lorsque PostgreSQL écrit des données dans ce répertoire, elles sont conservées dans le volume Docker plutôt que seulement dans le système de fichiers propre au conteneur.

Exemple tiré de notre TP : nous avons créé une base appelée test_persistance, puis arrêté et supprimé le conteneur PostgreSQL. Après avoir vérifié que le volume existait toujours, nous avons recréé le conteneur. PostgreSQL a retrouvé la base test_persistance.

Ce test confirme que nos données peuvent survivre à la suppression et à la recréation du conteneur, à condition de conserver le volume.

Limite : si le volume lui-même est supprimé, perdu ou endommagé, cette protection ne suffit plus. Le volume persistant ne remplace donc pas une sauvegarde.

État : configuré et testé avec succès.

#### 1.5.2. Faciliter le redémarrage avec restart: unless-stopped

Le risque : un conteneur PostgreSQL peut s'arrêter à la suite d'une erreur, d'un incident logiciel ou d'un redémarrage de Docker ou de la machine virtuelle.

Sans politique de redémarrage adaptée, une intervention manuelle pourrait être nécessaire pour relancer le service.

Dans notre fichier compose.yaml, nous avons ajouté :

```text
restart: unless-stopped
```

Cette directive demande à Docker de redémarrer automatiquement le conteneur dans certaines circonstances, notamment après un arrêt inattendu, tant que le conteneur n'a pas été volontairement arrêté d'une manière qui désactive ce redémarrage automatique.

Exemple : si le processus principal du conteneur PostgreSQL s'arrête de manière inattendue alors que Docker fonctionne normalement, Docker peut tenter de redémarrer le conteneur.

Cela peut réduire la durée de certaines interruptions de service.

Limite : cette politique ne répare pas une base de données corrompue, un disque défectueux ou une VM inaccessible. Si PostgreSQL redémarre continuellement à cause d'une erreur persistante, la politique de redémarrage ne résout pas la cause du problème.

Elle ne garantit donc pas la haute disponibilité.

État : configuré, mais comportement de redémarrage automatique non encore testé spécifiquement.

#### 1.5.3. Limiter l'accès réseau grâce à data-net

Le risque : si PostgreSQL est accessible inutilement depuis l'extérieur, sa surface d'attaque augmente. Un acteur non autorisé pourrait tenter de se connecter au serveur de bases de données.

Pour limiter cette exposition, nous avons connecté PostgreSQL uniquement au réseau Docker data-net, déclaré avec l'option internal: true.

Nous n'avons pas publié son port 5432 sur la machine virtuelle Ubuntu.

L'objectif est de permettre aux applications autorisées de communiquer avec PostgreSQL à travers le réseau interne, sans exposer directement le service sur Internet.

Exemple tiré de notre TP : nous avons exécuté la commande docker port compose-postgres-1. Aucun port publié n'a été affiché.

Nous avons également réussi à joindre PostgreSQL depuis un conteneur temporaire connecté à compose_data-net, ce qui confirme que ce réseau permet les communications internes nécessaires.

Limite : un conteneur connecté à data-net peut tenter de joindre PostgreSQL. Le réseau interne ne remplace donc pas l'authentification, les permissions et la surveillance.

État : configuré et partiellement vérifié.

#### 1.5.4. Protéger les identifiants avec Docker Compose Secrets

Le risque : inscrire directement un mot de passe dans compose.yaml pourrait entraîner sa divulgation, notamment si ce fichier est publié dans notre dépôt GitHub.

Nous avons donc généré un mot de passe aléatoire dans un fichier distinct :

```text
/opt/ac-platform/secrets/postgres_password
```

Ce fichier appartient à root et possède les permissions 600, ce qui limite sa lecture et sa modification au propriétaire.

Docker Compose rend ce fichier accessible au conteneur PostgreSQL sous le chemin /run/secrets/postgres_password.

La variable POSTGRES_PASSWORD_FILE indique à PostgreSQL de lire le mot de passe dans ce fichier.

Cette méthode permet d'éviter de placer le mot de passe directement dans notre configuration versionnée.

Limite : le fichier secret n'est pas automatiquement chiffré par Docker Compose. Une personne disposant de privilèges suffisants sur la VM ou sur Docker pourrait accéder au secret. Il faut donc continuer à protéger les accès administratifs et ne jamais publier ce fichier dans GitHub.

État : configuré. Le montage du secret dans le conteneur a été vérifié.

#### 1.5.5. Séparer les bases et les comptes des applications

Le risque : si toutes les applications utilisent le compte administrateur PostgreSQL, une compromission d'une application pourrait donner accès à davantage de données et de privilèges que nécessaire.

Pour réduire ce risque, nous prévoyons de créer une base de données et un compte distincts pour chaque application compatible avec PostgreSQL.

Par exemple, Moodle pourrait disposer de sa propre base et d'un utilisateur autorisé à travailler uniquement avec les données nécessaires à Moodle.

Nous appliquerons ainsi le principe du moindre privilège, qui consiste à donner à chaque utilisateur ou service uniquement les permissions nécessaires à son fonctionnement.

Limite : cette séparation ne protège pas contre l'indisponibilité de l'instance PostgreSQL commune. Elle réduit certains risques d'accès non autorisé, mais ne supprime pas le point de défaillance unique.

État : à réaliser et à tester.

#### 1.5.6. Prévoir des sauvegardes et une procédure de restauration

Le risque : même avec un volume persistant, nous pourrions perdre des données à la suite d'une suppression accidentelle, d'une corruption, d'une panne du disque ou de la perte de la machine virtuelle.

Nous devons donc prévoir des sauvegardes indépendantes des données utilisées par le conteneur PostgreSQL.

L'objectif sera de pouvoir récupérer les bases de données à partir d'une copie conservée dans un emplacement approprié.

Mais effectuer une sauvegarde ne suffit pas. Il faudra aussi vérifier que nous pouvons réellement restaurer les données.

Nous devrons donc documenter et tester une procédure comprenant la création d'une sauvegarde, sa conservation, la restauration et la vérification des données récupérées.

Limite : une sauvegarde ne maintient pas automatiquement le service disponible pendant une panne. Elle sert à récupérer les données, ce qui peut nécessiter du temps.

État : à réaliser et à tester.

#### 1.5.7. Reconnaître la limite de notre architecture : l'absence de haute disponibilité

Les mécanismes précédents réduisent certains risques, mais ils ne suppriment pas notre point de défaillance unique.

Si notre instance PostgreSQL devient indisponible et ne peut pas redémarrer, les applications qui en dépendent risquent de rester perturbées jusqu'à sa remise en service.

Une véritable architecture de haute disponibilité nécessiterait des mécanismes supplémentaires, par exemple une instance PostgreSQL de secours, une réplication correctement configurée et un mécanisme de basculement.

Nous n'avons pas mis en place ces mécanismes dans notre environnement actuel.

Nous avons privilégié une architecture plus simple, adaptée aux objectifs pédagogiques et aux ressources disponibles, tout en reconnaissant ses limites.

État : limite architecturale identifiée et documentée.

### 1.6. Conclusion de notre choix architectural

Nous avons privilégié une instance PostgreSQL unique afin de simplifier le déploiement et l'administration de notre plateforme conteneurisée.

Nous prévoyons de séparer les bases de données et les comptes des applications, tout en protégeant les données avec un volume persistant, une gestion des secrets et un réseau interne.

Nous reconnaissons cependant que cette organisation présente un point de défaillance unique. Les sauvegardes, la restauration et les procédures d'exploitation devront donc être documentées et vérifiées pour mieux gérer les incidents.

Ce choix constitue un compromis adapté à notre environnement de travail actuel, sans prétendre offrir une haute disponibilité.

## 2. Configuration de PostgreSQL avec Docker Compose

### 2.1. Pourquoi utiliser Docker Compose ?

Notre plateforme doit faire fonctionner plusieurs conteneurs : Traefik, socket-proxy, PostgreSQL et, par la suite, les applications demandées dans le travail de session.

Nous utilisons Docker Compose pour décrire et gérer ces services à partir d'un fichier de configuration YAML nommé compose.yaml.

Plutôt que de créer chaque conteneur manuellement avec une longue commande docker run, nous définissons dans ce fichier les caractéristiques de chaque service : image Docker, politique de redémarrage, réseaux, volumes et secrets.

Cette méthode présente plusieurs avantages pour notre travail de groupe :

Reproductibilité : les membres du groupe peuvent consulter la configuration et reproduire le déploiement dans un environnement compatible.

Centralisation : les paramètres des différents services sont regroupés dans un même fichier.

Maintenance : nous pouvons modifier un service sans devoir reconstruire manuellement toute sa commande de lancement.

Documentation : le fichier YAML permet de comprendre comment les conteneurs sont configurés et reliés entre eux.

Notre fichier se trouve dans la machine virtuelle Ubuntu au chemin suivant :

```text
/opt/ac-platform/compose/compose.yaml
```

### 2.2. Choix de l'image PostgreSQL

Pour déployer PostgreSQL, nous avons ajouté un service utilisant l'image :

```text
image: postgres:17
```

Cette directive demande à Docker d'utiliser l'image officielle PostgreSQL de la version majeure 17.

Le nombre 17 identifie la version majeure de PostgreSQL. Il ne désigne pas le nom du conteneur.

Lorsque nous avons démarré PostgreSQL pour la première fois, Docker a téléchargé cette image, car elle n'était pas encore disponible localement dans notre VM.

Justification : nous avons choisi une version majeure explicite plutôt que latest, afin d'éviter un changement automatique vers une autre version majeure lors d'un futur téléchargement.

Limite : l'étiquette postgres:17 ne fixe pas une version mineure précise. L'image peut évoluer avec les mises à jour de PostgreSQL 17. Pour une reproductibilité plus stricte, il serait possible de fixer une version d'image plus précise ou son empreinte.

### 2.3. Configuration du service PostgreSQL

Voici le bloc PostgreSQL que nous avons ajouté à notre fichier compose.yaml :

```text
postgres:
image: postgres:17
restart: unless-stopped
environment:
POSTGRES_PASSWORD_FILE: /run/secrets/postgres_password
POSTGRES_USER: postgres
secrets:
- postgres_password
networks:
- data-net
volumes:
- postgres-data:/var/lib/postgresql/data
```

Nous allons expliquer chaque partie de cette configuration.

#### 2.3.1. Déclaration du service

```text
postgres:
```

Cette ligne définit un service Docker Compose nommé postgres.

Le nom du service sert notamment à identifier PostgreSQL dans les commandes Compose et à permettre aux autres conteneurs connectés au même réseau Docker de le joindre par son nom.

Dans notre environnement, Docker Compose a créé un conteneur nommé :

```text
compose-postgres-1
```

Il faut distinguer le nom du service, postgres, du nom du conteneur, compose-postgres-1.

#### 2.3.2. Politique de redémarrage

```text
restart: unless-stopped
```

Cette directive configure la politique de redémarrage du conteneur.

Elle permet à Docker de tenter de relancer PostgreSQL après certains arrêts, notamment lorsque le processus du conteneur s'interrompt de manière inattendue.

Si nous arrêtons volontairement le conteneur, Docker respecte cet arrêt et ne le redémarre pas automatiquement dans les mêmes conditions.

Cette mesure vise à réduire certaines interruptions de service.

Cependant, elle ne corrige pas les problèmes qui empêchent PostgreSQL de fonctionner, comme une erreur persistante de configuration ou une panne du stockage.

État : configuré. Le comportement de redémarrage automatique n'a pas encore fait l'objet d'un test spécifique.

#### 2.3.3. Paramètres d'environnement

```text
environment:
POSTGRES_PASSWORD_FILE: /run/secrets/postgres_password
POSTGRES_USER: postgres
```

La section environment définit les paramètres transmis au conteneur.

```text
POSTGRES_USER: postgres
```

Ce paramètre définit le compte administrateur PostgreSQL initial.

Nous utilisons ce compte pour l'initialisation et certaines opérations d'administration.

Nous ne prévoyons pas de donner ce compte administrateur aux applications. Des comptes distincts, disposant de permissions limitées, devront être créés pour les services applicatifs.

```text
POSTGRES_PASSWORD_FILE: /run/secrets/postgres_password
```

Ce paramètre indique à l'image PostgreSQL de lire le mot de passe initial dans un fichier.

Il permet d'éviter d'écrire directement le mot de passe dans compose.yaml.

Le chemin /run/secrets/postgres_password désigne l'emplacement du fichier à l'intérieur du conteneur PostgreSQL, et non son emplacement initial sur la VM Ubuntu.

Ces paramètres servent notamment à l'initialisation de PostgreSQL lorsque le répertoire de données est vide. Modifier le mot de passe dans le fichier secret après cette initialisation ne modifie pas automatiquement le mot de passe déjà enregistré dans PostgreSQL.

La gestion détaillée du secret sera expliquée dans la section consacrée à la sécurité.

#### 2.3.4. Association du secret

```text
secrets:
- postgres_password
```

Cette section indique que le service PostgreSQL doit avoir accès au secret nommé postgres_password.

Le secret est déclaré séparément dans la configuration générale de Docker Compose :

```text
secrets:
postgres_password:
```

file: /opt/ac-platform/secrets/postgres_password

Le fichier source se trouve donc sur notre VM Ubuntu, tandis que PostgreSQL le consulte dans le conteneur au chemin /run/secrets/postgres_password.

Nous avons vérifié la présence de ce montage avec docker inspect.

Précision de sécurité : Docker Compose fournit ici le secret à partir d'un fichier local. Ce mécanisme ne signifie pas que le fichier est automatiquement chiffré.

#### 2.3.5. Connexion au réseau de données

```text
networks:
- data-net
```

Cette directive connecte PostgreSQL au réseau Docker nommé data-net.

Nous avons réservé ce réseau aux communications nécessaires entre PostgreSQL et les applications autorisées à accéder aux données.

Le réseau est déclaré dans la configuration générale :

```text
networks:
data-net:
internal: true
```

L'option internal: true permet de créer un réseau Docker interne, sans connectivité externe directe fournie par ce réseau.

PostgreSQL n'est pas connecté à notre réseau d'exposition frontend-net.

De plus, nous n'avons ajouté aucune directive ports: au service PostgreSQL. Son port 5432 n'est donc pas publié sur notre VM Ubuntu.

Nous avons confirmé cette absence de publication avec la commande :

```text
sudo docker port compose-postgres-1
```

La commande n'a affiché aucun port publié.

Limite : les conteneurs connectés au réseau data-net peuvent tenter de joindre PostgreSQL. L'isolation réseau doit donc être complétée par l'authentification et la gestion des permissions.

Nous expliquerons plus précisément les réseaux Docker dans docs/architecture/segmentation-reseau.md.

#### 2.3.6. Configuration du volume persistant

```text
volumes:
- postgres-data:/var/lib/postgresql/data
```

Cette directive relie un volume Docker nommé postgres-data au répertoire de données de PostgreSQL.

Pour l'image PostgreSQL 17 que nous utilisons, /var/lib/postgresql/data correspond au répertoire dans lequel PostgreSQL conserve ses fichiers de données.

Le volume est également déclaré dans la configuration générale :

```text
volumes:
postgres-data:
```

Docker Compose a créé ce volume sous le nom :

```text
compose_postgres-data
```

Ce mécanisme permet de conserver les fichiers des bases de données indépendamment de la durée de vie du conteneur.

Nous avons confirmé son montage avec docker inspect, puis effectué un test complet de persistance : création d'une base, suppression du conteneur, recréation et vérification de la présence de la base.

Ce test sera présenté en détail dans la section consacrée à la persistance des données.

### 2.4. Importance de l'indentation YAML

Pendant la configuration, nous avons rencontré des difficultés liées à l'emplacement et à l'indentation de certaines directives.

Le format YAML utilise les espaces pour représenter la structure du fichier.

Par exemple, dans notre service PostgreSQL :

```text
postgres:
networks:
- data-net
```

La directive networks: appartient au service postgres parce qu'elle est placée sous celui-ci avec une indentation appropriée.

En revanche, la section générale suivante :

```text
networks:
data-net:
internal: true
```

se trouve au niveau principal du fichier Compose.

Il faut donc distinguer :

La section networks: d'un service, qui indique à quels réseaux ce service est connecté.

La section générale networks:, qui définit les réseaux du projet.

Le même principe s'applique aux volumes et aux secrets.

Une mauvaise indentation peut provoquer une erreur de validation ou placer une directive au mauvais endroit.

C'est pourquoi nous avons vérifié les modifications progressivement avant de démarrer PostgreSQL.

### 2.5. Validation de la configuration Docker Compose

Après avoir ajouté PostgreSQL, son volume et son secret, nous avons enregistré le fichier compose.yaml.

Nous avons ensuite exécuté :

```text
sudo docker compose -f /opt/ac-platform/compose/compose.yaml config --quiet
```

Cette commande demande à Docker Compose de lire et de valider la configuration.

L'option -f précise le chemin du fichier utilisé.

L'option config permet de vérifier la structure et la cohérence de la configuration Compose.

L'option --quiet supprime l'affichage de la configuration lorsqu'aucune erreur n'est détectée.

Résultat observé : la commande s'est terminée sans afficher de message d'erreur.

Nous avons donc confirmé que Docker Compose acceptait notre configuration.

Cette validation ne garantit cependant pas que PostgreSQL pourra fonctionner correctement. Elle ne remplace pas les tests de démarrage, de disponibilité, de stockage et de sécurité.

### 2.6. Premier démarrage de PostgreSQL

Après la validation, nous avons démarré uniquement le service PostgreSQL avec la commande :

```text
sudo docker compose -f /opt/ac-platform/compose/compose.yaml up -d --no-deps postgres
```

Cette commande demande à Docker Compose de créer et de démarrer le service PostgreSQL.

L'option -d permet de laisser le conteneur fonctionner en arrière-plan.

L'option --no-deps évite de démarrer automatiquement d'éventuelles dépendances du service.

Le nom postgres à la fin de la commande précise le service ciblé.

Lors du premier démarrage, nous avons observé les opérations suivantes :

Image postgres:17 Pulled

Network compose_data-net Created

Volume compose_postgres-data Created

Container compose-postgres-1 Started

Ces messages confirment que Docker Compose a téléchargé l'image, créé le réseau de données, créé le volume persistant et démarré le conteneur.

Nous avons également constaté que le réseau compose_data-net, auparavant absent de la liste des réseaux Docker, a été créé lorsque PostgreSQL en a eu besoin.

Attention : le message Started signifie que Docker a démarré le conteneur. Il ne garantit pas à lui seul que PostgreSQL est prêt à accepter des connexions.

Nous avons donc effectué un test de disponibilité supplémentaire avec pg_isready, dont le résultat sera présenté dans la section consacrée aux vérifications de fonctionnement.

### 2.7. Bilan de la configuration

À la fin de cette étape, nous avions obtenu :

Un service PostgreSQL 17 défini dans Docker Compose.

Une politique de redémarrage configurée.

Un mot de passe initial fourni par un fichier secret.

Une connexion au réseau interne data-net.

Un volume Docker persistant.

Une configuration Compose validée.

Un conteneur PostgreSQL démarré.

Nous avons ensuite entrepris les tests nécessaires pour vérifier que PostgreSQL fonctionnait réellement et que ses données étaient conservées correctement.

Ces vérifications feront l'objet des sections suivantes.

## 3. Préparation de l'environnement et création du secret PostgreSQL

### 3.1. Objectif de cette étape

Avant de démarrer PostgreSQL, nous devions préparer notre environnement Ubuntu pour accueillir le conteneur et protéger son mot de passe administrateur.

Cette préparation répondait à trois besoins :

Vérifier que notre machine virtuelle disposait d'un espace de stockage suffisant.

Vérifier l'état des volumes Docker avant le déploiement.

Créer un fichier protégé contenant le mot de passe initial du compte administrateur PostgreSQL.

Nous avons effectué ces vérifications avant le premier démarrage du service.

### 3.2. Vérification de l'espace disque

Nous avons commencé par exécuter :

```text
df -h /
```

La commande df affiche l'utilisation de l'espace disque. L'option -h présente les tailles dans des unités faciles à lire, tandis que / désigne le système de fichiers contenant la racine de notre installation Ubuntu.

Résultat observé :

Capacité totale : environ 19 Go.

Espace utilisé : environ 3 Go.

Espace disponible : environ 16 Go.

Nous avons donc constaté que notre VM disposait encore d'espace pour télécharger l'image PostgreSQL et créer son volume de données.

Cette vérification ne garantit toutefois pas que cet espace sera suffisant pour toute la durée du projet. Les bases de données, les images Docker et les journaux peuvent grossir avec le temps.

Il faudra donc continuer à surveiller l'espace disque pendant le déploiement des autres applications.

### 3.3. Vérification des volumes Docker existants

Nous avons ensuite exécuté :

```text
sudo docker volume ls
```

Cette commande affiche les volumes Docker présents sur la machine.

Résultat observé : aucun volume n'était répertorié à ce moment-là.

Cette vérification nous a permis de connaître l'état initial du stockage Docker avant de créer le volume PostgreSQL.

Après le démarrage du service, nous avons pu constater la création du volume compose_postgres-data.

Il faut noter que cette commande liste les volumes Docker, mais ne permet pas à elle seule de vérifier leur contenu ou leur état de santé.

### 3.4. Création du répertoire destiné aux secrets

Nous avons choisi de ne pas placer le mot de passe administrateur PostgreSQL directement dans compose.yaml.

Nous avons donc créé un répertoire distinct sur notre VM Ubuntu :

```text
sudo install -d -m 700 /opt/ac-platform/secrets
```

Voici ce que signifie cette commande :

```text
sudo : exécute la commande avec les privilèges administratifs nécessaires.
```

install -d : crée le répertoire demandé.

-m 700 : attribue des permissions restrictives au répertoire.

```text
/opt/ac-platform/secrets : indique l'emplacement du répertoire.
```

Pourquoi les permissions 700 ?

Sous Linux, les permissions 700 signifient que le propriétaire possède les droits de lecture, d'écriture et d'accès au répertoire, tandis que les autres utilisateurs ne reçoivent aucun droit.

Dans notre environnement, le répertoire appartient à root.

Cela limite l'accès aux fichiers secrets qu'il contient.

Nous avons vérifié ses permissions et obtenu :

drwx------ root root

Ce résultat confirme que le répertoire appartient à root et qu'il possède les permissions prévues.

Limite : cette protection ne bloque pas les utilisateurs disposant de privilèges administratifs suffisants sur la VM.

### 3.5. Génération du mot de passe PostgreSQL

Nous avons ensuite généré le mot de passe initial du compte administrateur PostgreSQL avec OpenSSL :

```text
sudo sh -c 'umask 077; openssl rand -hex 32 > /opt/ac-platform/secrets/postgres_password'
```

Cette commande comporte plusieurs éléments importants.

```text
openssl rand -hex 32
```

OpenSSL génère ici 32 octets aléatoires et les représente sous forme hexadécimale.

Chaque octet est représenté par deux caractères hexadécimaux. Le résultat contient donc 64 caractères.

Nous avons choisi cette méthode pour obtenir un mot de passe difficile à deviner, plutôt que d'utiliser un mot de passe simple ou réutilisé.

> /opt/ac-platform/secrets/postgres_password

Le symbole > redirige le résultat vers un fichier.

Au lieu d'afficher le mot de passe dans le terminal, la commande l'enregistre dans :

```text
/opt/ac-platform/secrets/postgres_password
```

Ce fichier contient donc la valeur réelle du mot de passe administrateur initial.

Le nom postgres_password désigne le fichier : ce n'est pas le mot de passe lui-même.

```text
umask 077
```

La commande umask détermine les permissions qui seront retirées lors de la création de nouveaux fichiers.

Avec umask 077, le fichier créé reçoit des permissions restrictives, empêchant les autres utilisateurs ordinaires de le lire.

Pourquoi utiliser sudo sh -c ?

Nous voulions que la génération du mot de passe et son écriture dans le répertoire protégé soient effectuées avec les privilèges administratifs.

La commande sudo sh -c exécute l'ensemble de l'instruction dans un interpréteur de commandes lancé avec ces privilèges.

C'est important parce que la redirection > est elle aussi exécutée dans ce contexte.

#### 3.5.1. Précisions complémentaires sur la génération du mot de passe

Le mot de passe du compte administrateur PostgreSQL postgres n’a pas été choisi ni saisi manuellement : il a été généré automatiquement par OpenSSL. La valeur produite constitue le mot de passe initial et elle est enregistrée directement dans le fichier protégé, sans être affichée à l’écran.

Cette génération concerne l’initialisation d’un nouveau répertoire de données. Si PostgreSQL utilise déjà un volume initialisé, modifier le fichier postgres_password ne modifie pas automatiquement le mot de passe du rôle postgres existant. Cette distinction est essentielle lorsqu’on recrée le conteneur en conservant le volume.

### 3.6. Vérification des permissions du fichier secret

Après la génération, nous avons exécuté :

```text
sudo ls -l /opt/ac-platform/secrets/postgres_password
```

Résultat observé :

-rw------- 1 root root 65

Ce résultat signifie :

-rw------- : le propriétaire peut lire et modifier le fichier; les autres utilisateurs ordinaires n'ont aucun droit.

root root : le propriétaire et le groupe du fichier sont root.

65 : le fichier occupe 65 octets.

Les 65 octets correspondent aux 64 caractères hexadécimaux du mot de passe et au caractère de fin de ligne ajouté lors de son écriture.

Nous avons ainsi confirmé que le fichier existait et possédait les permissions attendues.

Nous n'avons pas affiché son contenu dans notre documentation afin de ne pas divulguer le mot de passe.

### 3.7. Comment Docker Compose utilise ce fichier

Notre fichier compose.yaml contient une déclaration générale du secret :

```text
secrets:
postgres_password:
```

file: /opt/ac-platform/secrets/postgres_password

Cette déclaration indique à Docker Compose où trouver le fichier contenant le mot de passe.

Le service PostgreSQL contient ensuite :

```text
secrets:
- postgres_password
```

Cette deuxième déclaration autorise le service PostgreSQL à recevoir le secret.

Enfin, nous avons configuré :

```text
environment:
POSTGRES_PASSWORD_FILE: /run/secrets/postgres_password
POSTGRES_USER: postgres
```

Lors de l'initialisation d'un nouveau répertoire de données, l'image PostgreSQL lit le contenu du fichier secret pour définir le mot de passe initial du compte administrateur postgres.

Ce mot de passe sert à authentifier ce compte lorsque les règles de connexion PostgreSQL exigent un mot de passe.

Il ne sert pas à ouvrir une session Ubuntu, à se connecter en SSH ou à démarrer Docker.

Il ne doit pas non plus devenir le mot de passe partagé par toutes les applications. Nous prévoyons de créer des comptes distincts pour les applications qui utiliseront PostgreSQL.

Précision : le fichier secret sert à définir le mot de passe initial du serveur, mais ne transmet pas automatiquement ce mot de passe aux applications clientes. Chaque application devra recevoir ses propres paramètres de connexion et, lorsque prévu, un compte PostgreSQL aux privilèges limités.

### 3.8. Précautions pour GitHub

Notre groupe utilise GitHub pour partager le code, la configuration et la documentation du projet.

Nous devons donc distinguer deux éléments :

Ce que nous pouvons publier : le fichier compose.yaml, qui indique comment PostgreSQL reçoit son secret, ainsi que les explications de notre documentation.

Ce que nous ne devons pas publier : le fichier /opt/ac-platform/secrets/postgres_password, puisqu'il contient le véritable mot de passe.

Nous devons également vérifier les fichiers préparés pour Git avant chaque publication, afin de ne pas ajouter accidentellement un secret.

Il faut enfin comprendre que notre méthode utilise un fichier local monté dans le conteneur. Docker Compose ne chiffre pas automatiquement ce fichier.

### 3.9. Résultat de la préparation

À la fin de cette étape, nous avions :

Vérifié l'espace disque disponible.

Constaté l'absence de volumes Docker avant le déploiement.

Créé le répertoire protégé /opt/ac-platform/secrets.

Généré un mot de passe administrateur PostgreSQL aléatoire.

Vérifié les permissions du répertoire et du fichier.

Préparé le mécanisme permettant à Docker Compose de transmettre ce secret au conteneur.

Ces opérations ont permis de préparer le démarrage de PostgreSQL sans inscrire son mot de passe directement dans notre fichier de configuration partagé.

## 4. Vérification du fonctionnement de PostgreSQL

### 4.1. Objectif des vérifications

Après avoir ajouté PostgreSQL dans notre fichier compose.yaml et démarré le conteneur, nous devions vérifier que le service fonctionnait correctement.

Le simple fait que Docker affiche un message indiquant que le conteneur a démarré ne garantit pas que le serveur de bases de données est opérationnel.

Nous avons donc effectué plusieurs vérifications pour répondre aux questions suivantes :

Le conteneur PostgreSQL a-t-il bien été démarré ?

Le serveur PostgreSQL est-il prêt à accepter des connexions ?

Le volume destiné aux données est-il correctement monté ?

Le fichier contenant le mot de passe administrateur est-il accessible dans le conteneur ?

Ces vérifications nous permettent de distinguer le fonctionnement du conteneur Docker de celui du serveur PostgreSQL qu'il héberge.

### 4.2. Vérification du démarrage du conteneur

Nous avons démarré PostgreSQL avec la commande :

```text
sudo docker compose -f /opt/ac-platform/compose/compose.yaml up -d --no-deps postgres
```

Docker Compose a notamment affiché :

Image postgres:17 Pulled

Network compose_data-net Created

Volume compose_postgres-data Created

Container compose-postgres-1 Started

Ces messages indiquent que Docker a effectué les opérations nécessaires pour créer et démarrer le conteneur.

Nous pouvons notamment constater que :

L'image PostgreSQL 17 a été téléchargée.

Le réseau Docker compose_data-net a été créé.

Le volume persistant compose_postgres-data a été créé.

Le conteneur compose-postgres-1 a démarré.

Que signifie exactement « Started » ?

Le message Started indique que Docker a lancé le conteneur.

Cependant, un conteneur peut être démarré alors que l'application qu'il contient n'est pas encore prête.

Par exemple, PostgreSQL peut avoir besoin de quelques instants pour initialiser ses fichiers de données et préparer son fonctionnement.

Il est également possible qu'un conteneur démarre, puis s'arrête à cause d'une erreur.

Nous ne pouvions donc pas conclure que PostgreSQL était opérationnel uniquement à partir du message Started.

Nous avons poursuivi avec une vérification directement auprès du serveur PostgreSQL.

### 4.3. Vérification de la disponibilité avec pg_isready

Nous avons exécuté :

```text
sudo docker exec compose-postgres-1 pg_isready -U postgres
```

Explication de la commande

```text
sudo docker exec
```

Cette instruction permet d'exécuter une commande dans un conteneur Docker déjà en fonctionnement.

Contrairement à docker run, elle ne crée pas un nouveau conteneur.

```text
compose-postgres-1
```

Il s'agit du nom du conteneur dans lequel nous voulons exécuter la commande.

pg_isready

C'est un outil fourni avec PostgreSQL. Il permet de vérifier si un serveur PostgreSQL répond et accepte les tentatives de connexion.

-U postgres

Cette option indique le nom d'utilisateur PostgreSQL utilisé pour la vérification.

Résultat obtenu

Notre terminal a affiché :

```text
/var/run/postgresql:5432 - accepting connections
```

Ce résultat signifie que PostgreSQL répondait et était prêt à accepter des tentatives de connexion.

Le chemin /var/run/postgresql correspond ici à l'emplacement du socket Unix utilisé pour joindre PostgreSQL localement dans le conteneur.

Le nombre 5432 est le port habituellement utilisé par PostgreSQL.

Que prouve ce résultat ?

Il confirme que le serveur PostgreSQL était disponible au moment de notre vérification.

C'est une preuve plus précise que le simple message de démarrage du conteneur.

Que ne prouve-t-il pas ?

Cette vérification ne garantit pas :

Qu'un utilisateur peut s'authentifier avec son mot de passe.

Que les applications pourront accéder à leurs bases de données.

Que les données sont correctement conservées après la suppression du conteneur.

Que PostgreSQL restera disponible en cas de panne.

En particulier, pg_isready ne constitue pas un test complet d'authentification.

Nous avons donc prévu des vérifications complémentaires pour ces différents aspects.

État : test effectué avec succès.

### 4.4. Vérification des montages avec docker inspect

Après avoir vérifié que PostgreSQL acceptait les connexions, nous avons voulu confirmer que Docker avait bien associé le volume persistant et le fichier secret au conteneur.

Nous avons exécuté :

```text
sudo docker inspect -f '{{range .Mounts}}{{println .Type .Name .Destination}}{{end}}' compose-postgres-1
```

Cette commande paraît complexe, mais son objectif est assez simple : afficher les ressources montées dans le conteneur.

Explication de la commande

```text
docker inspect
```

Cette commande affiche des informations détaillées sur un objet Docker, notamment un conteneur.

Elle permet de consulter sa configuration réelle.

-f

Cette option permet de choisir un format d'affichage particulier.

Nous l'avons utilisée pour ne montrer que les informations concernant les montages.

.Mounts

Cette propriété contient la liste des montages associés au conteneur.

Pour chaque montage, nous avons demandé d'afficher :

Type : le type de montage.

Name : le nom du volume, lorsqu'il s'agit d'un volume Docker nommé.

Destination : l'emplacement où la ressource apparaît à l'intérieur du conteneur.

La structure range ... end parcourt les montages et affiche les informations demandées pour chacun.

Résultat observé

Nous avons obtenu deux montages importants :

bind ... /run/secrets/postgres_password

```text
volume compose_postgres-data /var/lib/postgresql/data
```

Le premier correspond au fichier secret. Le second correspond au volume contenant les données PostgreSQL.

Nous allons les examiner séparément.

### 4.5. Vérification du montage du secret

Le premier montage était de type :

bind ... /run/secrets/postgres_password

Un montage de type bind permet de rendre une ressource du système hôte accessible à l'intérieur d'un conteneur.

Dans notre cas, Docker Compose utilise le fichier situé sur Ubuntu :

```text
/opt/ac-platform/secrets/postgres_password
```

Il le rend accessible à PostgreSQL sous le chemin :

```text
/run/secrets/postgres_password
```

C'est précisément ce chemin que nous avons indiqué dans notre configuration :

```text
POSTGRES_PASSWORD_FILE: /run/secrets/postgres_password
```

Nous avons ainsi vérifié que le secret était bien monté dans le conteneur.

Limite de cette vérification

La présence du montage ne prouve pas à elle seule que le mot de passe permet de réussir une authentification.

Elle confirme que Docker a mis le fichier à disposition du conteneur.

Pour vérifier l'authentification, il faut effectuer un test de connexion avec les identifiants PostgreSQL.

Ce test sera présenté dans la section consacrée à la sécurité et à l'authentification.

État : montage du secret vérifié.

### 4.6. Vérification du montage du volume persistant

Le deuxième montage affiché était :

```text
volume compose_postgres-data /var/lib/postgresql/data
```

Cela signifie que Docker a monté le volume compose_postgres-data dans le répertoire où PostgreSQL conserve ses données.

Il faut distinguer trois éléments :

Le conteneur compose-postgres-1

Il héberge le processus PostgreSQL.

Le volume compose_postgres-data

Il conserve les fichiers de données indépendamment de la durée de vie du conteneur.

Le chemin /var/lib/postgresql/data

C'est le répertoire dans lequel PostgreSQL 17 écrit ses fichiers de données à l'intérieur du conteneur.

Lorsque PostgreSQL écrit dans ce répertoire, les données sont enregistrées dans le volume Docker associé.

Pourquoi cette vérification était-elle importante ?

Nous voulions éviter que les bases de données soient conservées uniquement dans le système de fichiers du conteneur.

Si nous supprimions ce conteneur sans avoir configuré de stockage persistant, nous risquerions de perdre les données qui y étaient enregistrées.

Le résultat de docker inspect confirme que le volume prévu dans compose.yaml est effectivement monté.

Limite de cette vérification

La présence du volume ne suffit pas à prouver que les données survivront à la suppression et à la recréation du conteneur.

Pour obtenir cette preuve, nous devions créer des données, supprimer le conteneur, le recréer et vérifier que les données étaient toujours présentes.

C'est exactement le test de persistance que nous avons effectué par la suite.

État : montage du volume vérifié. La persistance a également été confirmée par un test distinct.

### 4.7. Différence entre les vérifications effectuées

Nos commandes ne vérifient pas toutes la même chose.

```text
docker compose up -d --no-deps postgres
```

Cette commande sert à créer et à démarrer le service. Le message Started confirme que Docker a lancé le conteneur.

pg_isready -U postgres

Cette commande vérifie que PostgreSQL répond et accepte les tentatives de connexion au moment du test.

```text
docker inspect
```

Cette commande permet de consulter la configuration réelle du conteneur, notamment les montages du secret et du volume.

Ces vérifications sont complémentaires.

Un conteneur peut démarrer sans que PostgreSQL soit prêt. PostgreSQL peut répondre alors que son stockage n'est pas configuré comme prévu. Et un volume peut être monté sans que nous ayons encore vérifié la conservation des données après recréation du conteneur.

C'est pourquoi nous n'avons pas considéré qu'une seule commande suffisait pour valider notre installation.

### 4.8. Bilan de nos vérifications

À la fin de cette étape, nous avions confirmé les éléments suivants :

Docker Compose avait créé et démarré le conteneur PostgreSQL.

PostgreSQL répondait et acceptait les tentatives de connexion.

Le fichier secret était monté à l'emplacement prévu.

Le volume persistant était monté dans le répertoire de données de PostgreSQL.

Ces résultats nous ont permis de poursuivre les tests dans de bonnes conditions.

Cependant, nous n'avions pas encore démontré, à cette étape, que les données survivraient à la suppression du conteneur ni que les règles d'authentification fonctionnaient comme prévu.

Nous avons donc réalisé des tests supplémentaires, qui seront documentés dans les sections suivantes.

## 5. Test complet de persistance des données PostgreSQL

### 5.1. Objectif du test

Nous avons configuré un volume Docker nommé postgres-data pour conserver les fichiers de PostgreSQL indépendamment du conteneur.

Cependant, le simple fait de déclarer ce volume dans compose.yaml ne suffisait pas à démontrer que les données seraient effectivement conservées.

Nous avons donc décidé de réaliser un test de persistance.

Notre question de départ était la suivante :

Si nous supprimons le conteneur PostgreSQL et que nous le recréons, est-ce que les bases de données précédemment enregistrées seront toujours présentes ?

Pour répondre à cette question, nous avons créé une base de données temporaire, supprimé le conteneur PostgreSQL, puis recréé celui-ci en conservant le volume Docker.

Nous avons ensuite vérifié si la base de données existait toujours.

Ce test permet de vérifier concrètement que les données ne dépendent pas uniquement de la durée de vie du conteneur.

### 5.2. Comprendre la différence entre un conteneur et un volume

Avant de présenter les commandes, il est important de comprendre les deux éléments que nous avons manipulés.

Le conteneur PostgreSQL

Notre conteneur s'appelle compose-postgres-1.

Il exécute le serveur PostgreSQL. Nous pouvons l'arrêter, le supprimer et le recréer à partir de notre configuration Docker Compose.

Le volume PostgreSQL

Notre volume s'appelle compose_postgres-data.

Il contient les fichiers de données PostgreSQL et possède un cycle de vie distinct de celui du conteneur.

Dans notre configuration, le volume est monté au chemin :

```text
/var/lib/postgresql/data
```

Lorsque PostgreSQL enregistre une base de données, ses fichiers sont conservés dans ce volume.

L'objectif du test était donc de vérifier qu'un nouveau conteneur pouvait retrouver les données conservées dans le volume utilisé par l'ancien conteneur.

### 5.3. Étape 1 — Création d'une base de données de test

Nous avons commencé par créer une base de données temporaire appelée test_persistance.

Commande exécutée :

```text
sudo docker exec -u postgres compose-postgres-1 createdb test_persistance
```

Explication de la commande

```text
sudo docker exec : exécute une commande dans un conteneur déjà en fonctionnement.
```

-u postgres : demande à Docker d'exécuter la commande avec l'utilisateur Linux postgres à l'intérieur du conteneur.

```text
compose-postgres-1 : désigne notre conteneur PostgreSQL.
```

createdb : outil PostgreSQL permettant de créer une base de données.

test_persistance : nom de la nouvelle base.

Nous avons utilisé l'utilisateur Linux postgres parce qu'il dispose, dans notre conteneur, des accès nécessaires pour effectuer cette opération d'administration locale.

Il faut distinguer l'utilisateur Linux postgres, utilisé pour exécuter la commande, du rôle PostgreSQL postgres, utilisé par défaut par l'outil dans ce contexte.

Résultat observé

La commande s'est terminée sans afficher d'erreur.

Cela suggérait que la création avait réussi, mais nous avons effectué une vérification supplémentaire pour le confirmer.

### 5.4. Étape 2 — Vérification de l'existence de la base

Nous avons exécuté :

```text
sudo docker exec -u postgres compose-postgres-1 psql -l
```

Explication de la commande

psql est l'outil en ligne de commande qui permet d'interagir avec PostgreSQL.

L'option -l demande d'afficher la liste des bases de données.

Résultat observé

Nous avons retrouvé les bases suivantes :

postgres

template0

template1

test_persistance

Les trois premières sont des bases présentes dans une installation PostgreSQL initialisée.

La base test_persistance était celle que nous venions de créer.

Sa présence confirmait que la première étape avait réussi.

Pourquoi cette vérification était-elle importante ?

Nous devions nous assurer que la base existait réellement avant de supprimer le conteneur.

Sans cette vérification initiale, nous n'aurions pas disposé d'un point de comparaison fiable après sa recréation.

### 5.5. Étape 3 — Arrêt du conteneur PostgreSQL

Nous avons ensuite arrêté le service PostgreSQL :

```text
sudo docker compose -f /opt/ac-platform/compose/compose.yaml stop postgres
```

Explication de la commande

La commande docker compose stop arrête le conteneur associé au service demandé, sans le supprimer.

Nous avons précisé postgres afin de ne pas arrêter volontairement les autres services de notre plateforme.

À cette étape, le volume de données devait rester présent.

Incident observé pendant la manipulation

Lors de cette opération, l'affichage de notre terminal a été perturbé. Certaines anciennes lignes se sont mélangées aux nouvelles, et nous avons observé un affichage inhabituel incluant ^C.

Nous ne pouvions donc pas nous fier uniquement à ce qui apparaissait à l'écran pour déterminer l'état du conteneur.

Nous avons décidé de vérifier directement son état auprès de Docker.

Commande exécutée :

```text
sudo docker ps -a --filter name=compose-postgres-1
```

Cette commande affiche les conteneurs correspondant au filtre, y compris ceux qui sont arrêtés.

Résultat observé

Docker indiquait :

Exited (0)

Cela confirmait que le conteneur PostgreSQL était arrêté.

Le code de sortie 0 indiquait que son processus principal s'était terminé sans signaler d'erreur à Docker.

Leçon retenue : lorsqu'un affichage de terminal semble incohérent, il est préférable de vérifier l'état réel du service avec une commande de diagnostic plutôt que de tirer une conclusion à partir de l'affichage.

### 5.6. Étape 4 — Suppression du conteneur

Après avoir confirmé son arrêt, nous avons supprimé le conteneur PostgreSQL :

```text
sudo docker compose -f /opt/ac-platform/compose/compose.yaml rm -f postgres
```

Explication de la commande

```text
docker compose rm supprime les conteneurs de services arrêtés.
```

L'option -f évite la demande de confirmation interactive.

Le nom postgres indique que nous souhaitons supprimer uniquement le conteneur de ce service.

Attention : cette commande est différente de docker compose down -v.

Nous n'avons pas utilisé l'option -v, car nous voulions précisément conserver le volume PostgreSQL pour vérifier la persistance des données.

Résultat observé

Docker Compose a confirmé :

Container compose-postgres-1 Removed

À ce moment, le conteneur qui avait servi à créer notre base de test n'existait plus.

C'était une étape essentielle : nous voulions démontrer que les données pouvaient être retrouvées par un autre conteneur, et pas seulement après un simple arrêt et redémarrage du même conteneur.

### 5.7. Étape 5 — Vérification de la conservation du volume

Après la suppression du conteneur, nous avons exécuté :

```text
sudo docker volume ls
```

Résultat observé

Docker affichait toujours :

DRIVER    VOLUME NAME

local     compose_postgres-data

Cela confirmait que le volume existait encore malgré la suppression du conteneur.

Cette observation illustre la différence entre le cycle de vie du conteneur et celui du volume.

Que prouve cette vérification ?

Elle confirme que Docker n'avait pas supprimé le volume nommé compose_postgres-data.

Que ne prouve-t-elle pas ?

Elle ne garantit pas encore que notre base de test est intacte.

Pour vérifier les données, nous devions recréer le conteneur et interroger PostgreSQL.

### 5.8. Étape 6 — Recréation du conteneur PostgreSQL

Nous avons relancé le service avec la même configuration Docker Compose :

```text
sudo docker compose -f /opt/ac-platform/compose/compose.yaml up -d --no-deps postgres
```

Explication

Docker Compose a recréé le conteneur PostgreSQL.

Comme le volume compose_postgres-data existait déjà, Docker l'a réutilisé au lieu de créer un nouveau volume vide.

PostgreSQL a donc retrouvé son répertoire de données existant.

Résultat observé

Docker Compose a confirmé le démarrage du conteneur.

Cependant, nous devions encore vérifier que la base test_persistance était toujours présente.

### 5.9. Étape 7 — Vérification de la persistance de la base

Nous avons exécuté :

```text
sudo docker exec -u postgres compose-postgres-1 psql -tAc "SELECT datname FROM pg_database WHERE datname='test_persistance';"
```

Cette commande interroge le catalogue interne de PostgreSQL pour rechercher une base portant exactement le nom test_persistance.

Explication des éléments importants

psql

Permet d'envoyer une requête SQL au serveur PostgreSQL.

-t

Supprime les en-têtes et les informations supplémentaires du tableau de résultats.

-A

Affiche les résultats dans un format non aligné, plus simple à exploiter.

-c

Permet de transmettre directement une commande SQL.

```text
SELECT datname FROM pg_database
```

Demande à PostgreSQL de rechercher des noms de bases de données dans son catalogue système.

```text
WHERE datname='test_persistance'
```

Limite le résultat à notre base de test.

Résultat observé

PostgreSQL a retourné :

test_persistance

Ce résultat constitue la preuve principale de notre test.

Nous avions créé cette base avant de supprimer le conteneur.

Après suppression et recréation du conteneur, PostgreSQL retrouvait toujours la base.

Nous avons donc confirmé que notre configuration permettait de conserver cette base de données dans le volume Docker persistant.

Limite du résultat

Ce test vérifie la persistance d'une base de données après recréation du conteneur.

Il ne vérifie pas la récupération après perte du volume, panne du disque ou destruction de la machine virtuelle.

Il ne démontre pas non plus qu'une sauvegarde a été réalisée.

### 5.10. Étape 8 — Suppression de la base temporaire

Après avoir confirmé le résultat, nous avons supprimé la base utilisée pour le test :

```text
sudo docker exec -u postgres compose-postgres-1 dropdb test_persistance
```

Explication

dropdb est un outil PostgreSQL permettant de supprimer une base de données.

Nous avons utilisé cette commande pour nettoyer notre environnement après l'expérience.

Résultat observé

La commande s'est terminée sans afficher d'erreur.

Nous avons donc terminé notre test sans conserver inutilement la base temporaire.

Précaution importante : dropdb supprime la base ciblée. Cette commande doit être utilisée uniquement lorsque nous sommes certains de vouloir supprimer cette base.

Dans notre cas, elle concernait une base créée spécialement pour le test.

### 5.11. Ce que notre expérience démontre

Notre test a permis de vérifier trois éléments importants.

Premièrement, les données sont conservées indépendamment du conteneur.

Nous avons supprimé le conteneur PostgreSQL, mais le volume Docker est resté présent.

Deuxièmement, un nouveau conteneur peut réutiliser le volume existant.

Lors de la recréation du service, PostgreSQL a retrouvé son répertoire de données.

Troisièmement, la base créée avant la suppression était toujours présente après la recréation.

La requête SQL a confirmé l'existence de test_persistance.

Nous pouvons donc conclure que la persistance des données PostgreSQL a été vérifiée avec succès dans le scénario testé.

Cette conclusion reste limitée au fonctionnement observé dans notre environnement.

### 5.12. Pourquoi la persistance ne remplace pas une sauvegarde

Il est essentiel de ne pas confondre ces deux mécanismes.

Le volume persistant

Il permet aux données de survivre à certaines opérations sur le conteneur, notamment sa suppression et sa recréation lorsque le volume est conservé.

Mais si les données contenues dans le volume sont supprimées ou corrompues, le volume ne fournit pas automatiquement une ancienne version permettant de les récupérer.

La sauvegarde

Une sauvegarde consiste à créer une copie récupérable des données, idéalement conservée indépendamment du stockage principal.

Elle peut servir à restaurer les informations après certains incidents, à condition que la sauvegarde soit valide et que la procédure de restauration fonctionne.

Exemple concret

Supposons que nous supprimions accidentellement une base de données importante dans PostgreSQL.

Le volume persistant conserverait l'état actuel du serveur, y compris les conséquences de cette suppression.

Il ne permettrait pas, à lui seul, de retrouver la base supprimée.

En revanche, une sauvegarde antérieure contenant cette base pourrait permettre sa récupération.

C'est pourquoi nous devons encore prévoir et tester une procédure de sauvegarde et de restauration.

État actuel : persistance testée; sauvegarde et restauration non encore réalisées.

### 5.13. Pourquoi ce test ne démontre pas la haute disponibilité

La haute disponibilité vise à réduire les interruptions d'un service lorsqu'un composant rencontre une panne.

Notre expérience n'avait pas cet objectif.

Pendant l'arrêt et la suppression du conteneur PostgreSQL, le serveur de bases de données était indisponible.

Nous avons ensuite dû recréer le conteneur pour retrouver le service.

Même si les données ont été conservées, cela ne signifie pas que PostgreSQL est resté accessible pendant l'opération.

De plus, notre architecture repose toujours sur une seule instance PostgreSQL et une seule VM.

Si cette VM devient inaccessible, les applications qui dépendent de PostgreSQL risquent également d'être perturbées.

Notre test confirme donc la persistance des données, et non la haute disponibilité du service.

### 5.14. Bilan du test de persistance

Nous avons effectué l'ensemble des opérations prévues :

Création de la base test_persistance.

Vérification de sa présence.

Arrêt du conteneur PostgreSQL.

Suppression du conteneur.

Vérification de la conservation du volume.

Recréation du conteneur.

Vérification de la présence de la base.

Suppression de la base temporaire.

Résultat : test réussi.

La base test_persistance était toujours présente après la suppression et la recréation du conteneur PostgreSQL.

Cette expérience confirme que notre volume Docker persistant fonctionne dans le scénario testé.

Elle ne remplace cependant ni une sauvegarde, ni un test de restauration, ni une architecture de haute disponibilité.

## 6. Sécurité et authentification PostgreSQL

### 6.1. Objectif de nos vérifications de sécurité

Après avoir vérifié le fonctionnement de PostgreSQL et la persistance des données, nous avons voulu examiner la manière dont les connexions au serveur étaient contrôlées.

Nous devions notamment répondre aux questions suivantes :

PostgreSQL est-il directement accessible depuis l'extérieur de notre machine virtuelle ?

Un autre conteneur peut-il communiquer avec PostgreSQL ?

PostgreSQL exige-t-il un mot de passe pour toutes les connexions ?

Quelles règles déterminent si une connexion est autorisée ?

Quelles protections avons-nous réellement vérifiées et quelles améliorations restent nécessaires ?

Ces vérifications sont importantes, car le fait de placer PostgreSQL dans un réseau Docker interne ne suffit pas à garantir que seuls les utilisateurs autorisés pourront consulter ou modifier les données.

Nous devons distinguer deux mécanismes :

La sécurité réseau détermine quels systèmes peuvent atteindre PostgreSQL.

L'authentification détermine comment PostgreSQL vérifie l'identité d'un client qui tente de se connecter.

Ces mécanismes se complètent, mais ne remplissent pas la même fonction.

### 6.2. Première vérification : connexion locale sans mot de passe

Nous avons exécuté la commande suivante :

```text
sudo docker exec -u postgres compose-postgres-1 psql -h 127.0.0.1 -U postgres -d postgres -w -c "SELECT 1;"
```

Comprendre la commande

```text
docker exec permet d'exécuter une commande à l'intérieur du conteneur PostgreSQL.
```

L'option -u postgres demande à Docker d'utiliser le compte Linux postgres dans le conteneur.

La commande psql permet de se connecter au serveur PostgreSQL et d'exécuter des requêtes SQL.

Les options suivantes précisent les paramètres de connexion :

-h 127.0.0.1 : se connecter au serveur PostgreSQL par TCP/IP sur l'adresse locale du conteneur.

-U postgres : utiliser le rôle PostgreSQL nommé postgres.

-d postgres : se connecter à la base de données postgres.

-w : ne pas demander de mot de passe.

-c "SELECT 1;" : exécuter une requête SQL simple.

La requête SELECT 1; demande seulement à PostgreSQL de retourner la valeur 1. Elle ne modifie aucune donnée.

Résultat observé

La connexion a réussi et PostgreSQL a retourné :

?column?

----------

1

(1 row)

Ce résultat était surprenant, puisque nous avions généré un mot de passe pour le compte administrateur postgres.

Nous venions pourtant de réussir une connexion sans le fournir.

Que pouvions-nous conclure ?

Nous pouvions confirmer que PostgreSQL acceptait cette connexion locale sans exiger de mot de passe.

Mais nous ne pouvions pas en conclure que le compte postgres n'avait pas de mot de passe, ni que toutes les connexions au serveur étaient autorisées sans authentification.

Il fallait examiner les règles utilisées par PostgreSQL.

### 6.3. Le rôle du fichier pg_hba.conf

PostgreSQL utilise un fichier de configuration appelé pg_hba.conf pour déterminer comment authentifier les clients qui tentent de se connecter.

Le nom HBA signifie Host-Based Authentication.

Ce fichier contient des règles qui tiennent notamment compte :

Du type de connexion : locale ou réseau.

De la base de données demandée.

Du rôle PostgreSQL utilisé.

De l'adresse réseau du client.

De la méthode d'authentification à appliquer.

Pour une connexion donnée, PostgreSQL recherche la première règle applicable et utilise la méthode d'authentification indiquée par cette règle.

Il ne faut donc pas supposer que toutes les connexions utilisent automatiquement la même méthode.

Recherche de l'emplacement du fichier

Nous avons exécuté :

```text
sudo docker exec -u postgres compose-postgres-1 psql -tAc "SHOW hba_file;"
```

La commande SQL SHOW hba_file; demande à PostgreSQL d'afficher l'emplacement du fichier d'authentification qu'il utilise.

Résultat observé :

```text
/var/lib/postgresql/data/pg_hba.conf
```

Ce fichier se trouve donc dans le répertoire de données PostgreSQL de notre conteneur.

Comme ce répertoire est associé à notre volume persistant, le fichier fait partie des éléments conservés dans ce volume.

### 6.4. Consultation des règles d'authentification

Nous avons ensuite demandé à PostgreSQL d'afficher les règles d'authentification reconnues dans son fichier de configuration.

Commande exécutée :

```text
sudo docker exec -u postgres compose-postgres-1 psql -P pager=off -c "SELECT line_number, type, database, user_name, address, auth_method FROM pg_hba_file_rules ORDER BY line_number;"
```

Explication de la commande

La vue système pg_hba_file_rules permet de consulter les règles contenues dans le fichier pg_hba.conf.

Nous avons demandé d'afficher :

line_number : numéro de ligne dans le fichier.

type : type de connexion.

database : base ou ensemble de bases concernées.

user_name : rôles PostgreSQL concernés.

address : adresse ou plage réseau concernée.

auth_method : méthode d'authentification.

L'instruction ORDER BY line_number affiche les règles dans leur ordre d'apparition.

Cet ordre est important, puisque PostgreSQL applique la première règle correspondant à la connexion.

L'option -P pager=off évite que le résultat soit ouvert dans un affichage paginé.

Résultats observés

Nous avons notamment relevé les règles suivantes :

Ligne 117 : local  all          all  → trust

Ligne 119 : host   all          all  127.0.0.1 → trust

Ligne 121 : host   all          all  ::1       → trust

Ligne 124 : local  replication  all  → trust

Ligne 125 : host   replication  all  127.0.0.1 → trust

Ligne 126 : host   replication  all  ::1       → trust

Ligne 128 : host   all          all  all       → scram-sha-256

Ces règles expliquent pourquoi nos connexions n'ont pas toutes donné le même résultat.

### 6.5. Comprendre la méthode trust

La méthode trust signifie que PostgreSQL accepte une connexion correspondant à la règle sans demander de mot de passe.

Il ne faut pas interpréter ce terme comme une vérification réussie du mot de passe.

Au contraire, PostgreSQL ne vérifie pas de mot de passe pour une connexion autorisée par une règle trust.

Dans notre configuration, la ligne 119 concernait les connexions TCP provenant de l'adresse :

127.0.0.1

Cette adresse représente l'interface de boucle locale (localhost).

Notre première commande utilisait précisément :

-h 127.0.0.1

Elle correspondait donc à une règle autorisant l'authentification trust.

C'est pour cette raison que notre connexion a réussi malgré l'option -w, qui empêchait psql de demander un mot de passe.

Est-ce que cela signifie que PostgreSQL n'est pas sécurisé ?

Pas nécessairement, mais cela représente un choix de configuration important.

Les règles trust observées concernent les connexions locales indiquées dans le fichier, et non toutes les connexions provenant du réseau Docker.

Cependant, elles comportent un risque : un processus capable d'établir une connexion locale correspondant à ces règles peut accéder à PostgreSQL sous un rôle autorisé par la règle sans présenter de mot de passe.

Par exemple, si un attaquant parvenait à exécuter du code dans le conteneur PostgreSQL, ces règles pourraient faciliter certains accès au serveur de bases de données.

Nous devons donc considérer la configuration locale trust comme une limite de sécurité à examiner, plutôt que comme une protection supplémentaire.

État : présence des règles trust vérifiée. Aucun durcissement de ces règles n'a encore été effectué.

### 6.6. Comprendre la méthode scram-sha-256

La dernière règle observée indiquait :

host all all all scram-sha-256

Cette règle concerne les connexions TCP correspondant à ses critères et qui n'ont pas déjà été prises en charge par une règle précédente.

La méthode scram-sha-256 utilise un mécanisme d'authentification par mot de passe fondé sur SCRAM et SHA-256.

Concrètement, le client doit prouver qu'il connaît le mot de passe du rôle PostgreSQL utilisé.

Le mot de passe n'est pas simplement envoyé en clair comme une chaîne ordinaire dans le mécanisme d'authentification SCRAM.

Il faut toutefois distinguer l'authentification du client du chiffrement de toute la communication réseau. L'utilisation de scram-sha-256 ne prouve pas, à elle seule, qu'une connexion utilise TLS.

Pourquoi cette règle est-elle importante ?

Lorsqu'une application située dans un autre conteneur souhaite se connecter à PostgreSQL par le réseau Docker, elle ne provient généralement pas de l'adresse locale 127.0.0.1 du conteneur PostgreSQL.

Sa connexion peut donc correspondre à la règle scram-sha-256.

Dans ce cas, elle doit fournir les identifiants nécessaires à l'authentification.

C'est précisément ce que nous avons voulu vérifier avec notre deuxième test.

### 6.7. Deuxième vérification : connexion depuis un autre conteneur sans mot de passe

Nous avons lancé un conteneur PostgreSQL temporaire sur notre réseau Docker de données.

Ce conteneur servait uniquement de client pour tenter de se connecter au serveur PostgreSQL déjà déployé.

Commande exécutée :

```text
sudo docker run --rm --network compose_data-net postgres:17 psql -h postgres -U postgres -d postgres -w -c "SELECT 1;"
```

Explication de la commande

```text
docker run
```

Crée et exécute un nouveau conteneur.

Il faut le distinguer de docker exec, qui exécute une commande dans un conteneur existant.

--rm

Demande à Docker de supprimer automatiquement le conteneur temporaire lorsqu'il se termine.

--network compose_data-net

Connecte ce conteneur temporaire au réseau Docker utilisé par notre serveur PostgreSQL.

```text
postgres:17
```

Utilise l'image PostgreSQL 17, qui contient notamment l'outil client psql.

Dans ce test, nous ne voulions pas créer un deuxième serveur PostgreSQL permanent. Nous voulions simplement utiliser le client psql fourni par cette image.

-h postgres

Demande au client de joindre le service PostgreSQL par son nom réseau postgres.

-U postgres -d postgres

Indique le rôle et la base de données ciblés.

-w

Interdit au client de demander un mot de passe.

Résultat observé

PostgreSQL a refusé la connexion avec le message :

fe_sendauth: no password supplied

Le message indiquait également une connexion vers PostgreSQL à l'adresse 172.20.0.2, sur le port 5432.

Comment interpréter ce résultat ?

Le client temporaire a pu atteindre le serveur PostgreSQL par le réseau Docker.

Nous savons donc que la communication réseau entre les deux conteneurs était possible.

Cependant, la connexion n'a pas été authentifiée, parce que le client n'avait fourni aucun mot de passe.

Ce comportement est cohérent avec la règle scram-sha-256 observée dans pg_hba.conf.

Notre test confirme donc que PostgreSQL exigeait un mot de passe pour cette connexion provenant d'un autre conteneur.

Que ne prouve pas ce test ?

Il ne prouve pas encore que le bon mot de passe permet effectivement de se connecter.

Il ne prouve pas non plus que les futurs comptes applicatifs auront des permissions correctement limitées.

Ces points nécessitent des tests supplémentaires.

État : connexion sans mot de passe refusée comme attendu.

### 6.8. Vérification de l'absence de publication du port PostgreSQL

Nous avons également exécuté :

```text
sudo docker port compose-postgres-1
```

Cette commande affiche les ports du conteneur publiés sur l'hôte Docker.

Résultat observé : aucun port affiché.

Cela signifie que notre conteneur PostgreSQL ne possédait aucun port publié sur la VM Ubuntu au moment du test.

Nous n'avons pas configuré de correspondance comme :

ports:

- "5432:5432"

Une telle directive publierait le port PostgreSQL sur l'hôte, ce qui n'est pas nécessaire pour les communications internes prévues entre nos applications.

Notre choix est donc de laisser les applications communiquer avec PostgreSQL à travers data-net, sans publier directement son port sur la VM.

Limite de cette vérification

L'absence de port publié réduit l'exposition directe du service par les interfaces réseau de la VM.

Elle ne signifie pas que PostgreSQL est inaccessible à tous les autres conteneurs.

Nous avons justement démontré qu'un conteneur connecté à compose_data-net pouvait atteindre PostgreSQL.

C'est pourquoi nous devons conserver des mécanismes d'authentification et des permissions appropriées.

### 6.9. Le réseau interne ne remplace pas l'authentification

Notre réseau data-net est déclaré avec :

```text
networks:
data-net:
internal: true
```

Ce réseau sert à isoler les communications liées aux données de notre plateforme.

Cependant, il faut éviter une confusion importante.

Être connecté à data-net ne signifie pas automatiquement être autorisé à utiliser toutes les bases de données PostgreSQL.

Le réseau permet la communication entre les conteneurs qui y sont connectés.

PostgreSQL doit ensuite vérifier l'identité du client selon les règles d'authentification applicables.

Enfin, les permissions associées au rôle PostgreSQL déterminent les opérations que ce client peut effectuer.

Nous pouvons donc distinguer trois niveaux de contrôle :

Accès réseau : le client peut-il joindre le serveur PostgreSQL ?

Authentification : le client peut-il prouver son identité selon la méthode exigée ?

Autorisation : une fois connecté, quelles bases et quelles opérations lui sont permises ?

Nos tests ont commencé à vérifier les deux premiers niveaux.

Le troisième devra être examiné lorsque nous créerons les comptes et les bases des applications.

### 6.10. Améliorations de sécurité prévues

Nos vérifications ont confirmé plusieurs protections, mais elles ont également montré que notre configuration n'était pas encore entièrement durcie.

Tester une connexion avec le bon mot de passe

Nous avons vérifié qu'une connexion réseau sans mot de passe était refusée.

Il reste à confirmer qu'une connexion utilisant le mot de passe correct est acceptée.

Ce test devra être réalisé sans afficher le secret dans les captures d'écran ni l'inscrire dans une commande susceptible d'être enregistrée dans l'historique du terminal.

État : à réaliser.

Examiner les règles locales trust

Nous avons constaté que certaines connexions locales ne nécessitaient pas de mot de passe.

Nous devrons déterminer si ces règles sont nécessaires dans notre environnement et, si nous décidons de les modifier, vérifier les conséquences sur l'administration et le fonctionnement de PostgreSQL.

Une modification du fichier pg_hba.conf devra être effectuée avec précaution et suivie de nouveaux tests.

État : risque identifié; modification non réalisée.

Créer des comptes applicatifs distincts

Nous prévoyons de créer des comptes PostgreSQL spécifiques aux applications compatibles.

Chaque compte devra disposer uniquement des permissions nécessaires à son fonctionnement.

Nous éviterons ainsi de donner le compte administrateur postgres à toutes les applications.

État : à réaliser.

Protéger les secrets applicatifs

Les futurs comptes applicatifs auront leurs propres mots de passe.

Ces identifiants ne devront pas être inscrits en clair dans les fichiers publiés sur GitHub.

Nous devrons définir une méthode adaptée pour les fournir aux applications.

État : à réaliser.

### 6.11. Bilan des vérifications de sécurité

Nos tests ont permis de confirmer les éléments suivants :

Le port PostgreSQL 5432 n'était pas publié sur la VM Ubuntu.

Un autre conteneur connecté à compose_data-net pouvait joindre le serveur PostgreSQL.

Une connexion locale vers 127.0.0.1 réussissait sans mot de passe, conformément à une règle trust.

Une connexion depuis un autre conteneur sans mot de passe était refusée.

Le fichier pg_hba.conf contenait une règle scram-sha-256 applicable aux connexions réseau correspondantes.

Ces résultats montrent que notre serveur applique des méthodes d'authentification différentes selon l'origine des connexions.

Ils ne démontrent cependant pas que toutes les mesures de sécurité sont terminées.

Il reste notamment à vérifier une connexion avec le bon mot de passe, à examiner les règles locales trust et à créer des comptes applicatifs disposant de permissions limitées.

Notre démarche consiste donc à documenter les protections réellement vérifiées, reconnaître les limites observées et identifier les mesures qui restent à mettre en place.

## 7. Limites du déploiement PostgreSQL et améliorations à prévoir

### 7.1. Objectif de cette section

Nous avons déployé PostgreSQL 17 dans un conteneur Docker, configuré un volume persistant, créé un secret pour le mot de passe administrateur et effectué plusieurs vérifications de fonctionnement et de sécurité.

Ces opérations nous ont permis de confirmer que notre serveur PostgreSQL fonctionne dans l'environnement actuel.

Cependant, un service qui démarre correctement et conserve ses données après la recréation d'un conteneur n'est pas nécessairement prêt à fonctionner dans toutes les situations.

Nous devons notamment considérer les pannes de la machine virtuelle, les erreurs humaines, les pertes de données, les accès non autorisés et les besoins futurs des applications.

L'objectif de cette section est donc de présenter les limites connues de notre déploiement, leurs conséquences possibles et les améliorations que nous pourrions apporter.

Nous distinguons les mesures déjà vérifiées des améliorations qui restent à réaliser.

### 7.2. Première limite : une seule instance PostgreSQL

Notre architecture actuelle repose sur une seule instance PostgreSQL exécutée dans le conteneur compose-postgres-1.

Cette instance constitue le serveur de bases de données de notre plateforme.

Pourquoi est-ce une limite ?

Si le conteneur PostgreSQL devient indisponible, les applications qui ont besoin de ses bases de données risquent de ne plus pouvoir effectuer certaines opérations.

Par exemple, une application pourrait ne plus être capable d'enregistrer de nouvelles informations ou de consulter les données nécessaires à son fonctionnement.

Cette dépendance représente un point de défaillance unique, appelé Single Point of Failure (SPOF).

Cela signifie qu'une panne d'un seul composant peut interrompre une partie importante du service.

Qu'avons-nous mis en place ?

Dans notre configuration Docker Compose, nous avons utilisé :

```text
restart: unless-stopped
```

Cette directive permet à Docker de redémarrer automatiquement le conteneur dans certaines situations, notamment lorsque son processus s'arrête de manière inattendue, sauf si le conteneur a été volontairement arrêté.

Il s'agit d'un mécanisme utile pour améliorer la capacité du service à redémarrer.

Cependant, cette directive ne crée pas un deuxième serveur PostgreSQL et ne permet pas de maintenir le service disponible si la VM elle-même tombe en panne.

Amélioration possible

Pour améliorer la disponibilité, nous pourrions envisager une architecture PostgreSQL avec réplication, plusieurs instances et un mécanisme de basculement.

Une telle solution nécessiterait une configuration supplémentaire et des tests adaptés.

État actuel : une seule instance PostgreSQL configurée. Politique de redémarrage présente, mais aucun test de reprise automatique après panne n'a été documenté. Aucune architecture de haute disponibilité mise en place.

### 7.3. Deuxième limite : le volume persistant se trouve sur la même VM

Nous avons créé un volume Docker nommé :

```text
compose_postgres-data
```

Ce volume est utilisé par PostgreSQL pour conserver ses données dans le répertoire :

```text
/var/lib/postgresql/data
```

Notre test de persistance a confirmé qu'une base de données créée avant la suppression du conteneur restait présente après sa recréation.

Il s'agit d'un résultat important, mais il faut bien comprendre ce qu'il démontre.

Ce que le volume protège

Le volume permet de conserver les données lorsque nous supprimons et recréons le conteneur, à condition de préserver ce volume.

Les données ne sont donc pas limitées au système de fichiers temporaire du conteneur.

Ce que le volume ne protège pas

Le volume reste associé au stockage Docker de notre VM.

Si nous perdons la VM ou si son stockage est détruit, les données du volume peuvent également être perdues.

De plus, certaines commandes Docker peuvent supprimer volontairement les volumes.

Par exemple :

```text
sudo docker compose -f /opt/ac-platform/compose/compose.yaml down -v
```

L'option -v demande à Docker Compose de supprimer les volumes nommés déclarés dans le projet, ainsi que les volumes anonymes attachés aux conteneurs supprimés.

Dans notre environnement, cela pourrait entraîner la suppression du volume contenant les données PostgreSQL.

Cette commande est présentée uniquement pour expliquer le risque. Nous ne l'avons pas exécutée dans notre test de persistance.

Amélioration possible

Nous devrons mettre en place une stratégie de sauvegarde permettant de conserver une copie récupérable des données indépendamment du volume principal.

État actuel : persistance vérifiée après suppression et recréation du conteneur. Protection contre la perte de la VM ou du volume non démontrée.

### 7.4. Troisième limite : absence de sauvegarde et de restauration testées

Nous avons démontré que les données PostgreSQL survivent à la recréation du conteneur.

Cependant, nous n'avons pas encore réalisé de sauvegarde complète de notre base de données ni effectué de restauration.

Il faut distinguer ces opérations de la persistance.

La persistance

Elle permet de conserver les données dans le volume Docker malgré certaines opérations sur le conteneur.

La sauvegarde

Elle consiste à produire une copie des données pouvant servir à leur récupération.

Pour PostgreSQL, nous pourrions notamment utiliser l'outil pg_dump pour effectuer une sauvegarde logique d'une base de données.

La restauration

Elle consiste à réintroduire les données sauvegardées dans un environnement PostgreSQL approprié.

La restauration est aussi importante que la sauvegarde : un fichier de sauvegarde dont nous n'avons jamais vérifié la récupération ne constitue pas une garantie suffisante.

Pourquoi cette amélioration est-elle prioritaire ?

Supposons qu'une personne supprime accidentellement une base de données importante.

Le volume persistant ne permettrait pas automatiquement de retrouver son ancien contenu.

Une sauvegarde valide pourrait permettre de récupérer les informations, selon son ancienneté et son contenu.

Nous devrons donc prévoir :

Une méthode de sauvegarde adaptée aux bases utilisées.

Un emplacement de stockage protégé, distinct du volume principal.

Une fréquence de sauvegarde correspondant aux besoins.

Une procédure de restauration documentée.

Un test permettant de confirmer que les données peuvent réellement être récupérées.

État actuel : aucune sauvegarde ni restauration vérifiée dans le cadre de notre déploiement PostgreSQL.

### 7.5. Quatrième limite : utilisation du compte administrateur postgres

Notre configuration initialise PostgreSQL avec le rôle administrateur postgres.

Nous avons utilisé ce rôle pour effectuer les premières vérifications et créer notre base temporaire de test.

Cette utilisation était pratique pendant l'installation, mais elle ne doit pas devenir notre méthode habituelle pour toutes les applications.

Pourquoi éviter d'utiliser le même compte administrateur partout ?

Le rôle postgres possède des privilèges très élevés dans le serveur.

Si une application utilisait ce compte et que ses identifiants étaient compromis, les conséquences pourraient toucher l'ensemble du serveur PostgreSQL.

De plus, lorsque plusieurs applications utilisent le même compte, il devient plus difficile de limiter leurs permissions et de distinguer leurs activités.

Amélioration prévue

Nous devrons créer des bases et des rôles adaptés aux besoins réels des applications qui utiliseront PostgreSQL.

Par exemple, une application pourrait disposer d'un compte lui permettant d'accéder uniquement à sa propre base, sans avoir les privilèges d'administration générale du serveur.

Cette approche correspond au principe du moindre privilège : chaque compte reçoit uniquement les permissions nécessaires à son fonctionnement.

Il faudra toutefois adapter les permissions aux exigences techniques de chaque application.

État actuel : compte administrateur postgres utilisé pour les opérations initiales. Comptes applicatifs distincts et permissions limitées non encore configurés.

### 7.6. Cinquième limite : les règles d'authentification locales trust

Lors de nos vérifications de sécurité, nous avons consulté les règles du fichier pg_hba.conf.

Nous avons observé que certaines connexions locales utilisaient la méthode :

trust

Cette méthode permet à une connexion correspondant à la règle d'être acceptée sans vérification de mot de passe.

Nous l'avons confirmé lorsqu'une connexion locale vers 127.0.0.1 a réussi sans fournir de mot de passe.

En revanche, une tentative provenant d'un autre conteneur, sans mot de passe, a été refusée.

Quel est le risque ?

Les règles trust reposent sur la confiance accordée à certaines connexions locales.

Si un processus non autorisé pouvait s'exécuter dans le conteneur PostgreSQL et établir une connexion correspondant à ces règles, il pourrait bénéficier de cette absence de vérification du mot de passe.

Amélioration possible

Nous pourrions examiner les règles locales et envisager une méthode d'authentification plus restrictive lorsque cela est compatible avec notre environnement.

Cette modification demanderait des précautions, car nous devrions nous assurer de conserver un accès administratif fonctionnel.

Il ne serait pas prudent de modifier les règles sans prévoir comment vérifier ensuite les connexions.

État actuel : règles trust identifiées et comportement observé. Aucun changement de ces règles réalisé.

### 7.7. Sixième limite : la gestion des secrets reste à compléter

Nous avons créé un fichier contenant le mot de passe administrateur PostgreSQL :

```text
/opt/ac-platform/secrets/postgres_password
```

Le mot de passe a été généré automatiquement avec OpenSSL.

Le fichier a été créé avec des permissions restrictives et rendu accessible au conteneur par Docker Compose.

Cette méthode nous permet d'éviter d'inscrire directement le mot de passe dans compose.yaml.

Quelles sont les limites ?

Dans notre déploiement, le secret reste un fichier présent sur la VM.

Une personne disposant de privilèges suffisants sur l'hôte peut accéder à son contenu.

De plus, Docker Compose rend le fichier accessible au conteneur concerné.

Nous devons donc protéger les accès à la VM, au moteur Docker et au conteneur PostgreSQL.

La présence d'un secret Docker Compose ne signifie pas que le mot de passe est automatiquement chiffré dans un coffre-fort spécialisé.

Améliorations possibles

Nous devrons notamment prévoir la protection des futurs mots de passe applicatifs et déterminer comment renouveler les identifiants si nécessaire.

Il faudra aussi vérifier que les fichiers secrets ne sont jamais publiés dans le dépôt GitHub.

État actuel : secret administrateur généré et fichier protégé par des permissions restrictives. Gestion centralisée, rotation des secrets et secrets applicatifs non mis en place.

### 7.8. Septième limite : l'authentification réseau a été partiellement testée

Nous avons réalisé une tentative de connexion depuis un conteneur temporaire connecté au réseau compose_data-net.

La connexion sans mot de passe a été refusée avec le message :

fe_sendauth: no password supplied

Ce résultat a confirmé que la connexion réseau concernée exigeait un mot de passe.

Cependant, nous n'avons pas encore effectué le test complémentaire avec le mot de passe correct.

Pourquoi faut-il effectuer ce deuxième test ?

Le refus d'une connexion sans mot de passe démontre qu'une authentification est exigée.

Il ne démontre pas que notre secret est correctement utilisable par un client distant.

Pour compléter la vérification, nous devrons établir une connexion authentifiée depuis un conteneur client, sans exposer le mot de passe dans l'historique des commandes ou dans la documentation.

Nous devrons ensuite vérifier que les futurs comptes applicatifs peuvent accéder uniquement aux ressources autorisées.

État actuel : connexion réseau sans mot de passe refusée. Connexion réseau avec le mot de passe correct et contrôle des permissions applicatives non encore testés.

### 7.9. Huitième limite : le chiffrement des communications n'a pas été vérifié

Nous avons constaté que PostgreSQL utilisait la méthode d'authentification scram-sha-256 pour certaines connexions réseau.

Cette méthode permet de vérifier la connaissance du mot de passe.

Cependant, elle ne signifie pas automatiquement que l'ensemble des échanges entre le client et PostgreSQL est chiffré avec TLS.

Pourquoi cette distinction est-elle importante ?

L'authentification répond à la question :

Le client possède-t-il les identifiants nécessaires pour se connecter ?

Le chiffrement des communications répond à une autre question :

Les informations échangées entre le client et le serveur sont-elles protégées contre leur lecture pendant le transport ?

Ces deux protections sont complémentaires.

Amélioration possible

Nous devrons évaluer les besoins de chiffrement des communications entre PostgreSQL et les applications, puis vérifier la configuration TLS si cette protection est mise en place.

État actuel : méthode d'authentification scram-sha-256 observée. Chiffrement TLS des connexions PostgreSQL non vérifié.

### 7.10. Neuvième limite : les ressources de la VM doivent être surveillées

Avant le déploiement, nous avons vérifié l'espace disponible sur le système de fichiers principal de notre VM avec :

```text
df -h /
```

Nous avions observé environ 19 Go d'espace total, dont 3 Go utilisés et 16 Go disponibles.

Cette vérification était utile pour confirmer que l'environnement disposait d'espace au moment de l'installation.

Cependant, l'utilisation du stockage peut augmenter avec les bases de données, les images Docker, les journaux et les autres services.

Quel est le risque ?

Si l'espace disque devient insuffisant, PostgreSQL peut rencontrer des erreurs lors de l'écriture des données.

Cela pourrait perturber le fonctionnement des applications.

De plus, nous n'avons pas encore documenté de tests de charge ou de mesures de performance permettant de déterminer combien de connexions ou d'opérations notre installation peut supporter.

Amélioration possible

Nous devrons prévoir une surveillance adaptée de l'espace disque, de la mémoire, du processeur et de l'état du service PostgreSQL.

Nous pourrons aussi établir des seuils d'alerte en fonction des ressources disponibles et de l'utilisation réelle.

État actuel : espace disque vérifié avant le déploiement. Surveillance continue, alertes et tests de performance non encore mis en place.

### 7.11. Améliorations possibles et distinction avec les exigences du travail de session

Notre déploiement PostgreSQL présente certaines limites techniques que nous avons identifiées au cours de nos vérifications.

Cependant, il est important de distinguer les améliorations que nous pourrions apporter à PostgreSQL des exigences réellement imposées par le professeur.

Dans l'énoncé du travail de session, le professeur demande de privilégier une seule instance PostgreSQL lorsque cela est techniquement réaliste pour les services retenus.

Il précise également que le projet doit être réalisable sur une seule machine virtuelle et qu'il n'a pas pour objectif de reproduire une architecture d'entreprise complète.

Notre choix d'une instance PostgreSQL commune, associée à un volume persistant et à un réseau de données interne, s'inscrit dans cette approche.

Améliorations possibles concernant PostgreSQL

Sauvegarde et restauration des données

Nous avons vérifié la persistance des données après suppression et recréation du conteneur.

Cependant, nous n'avons pas encore réalisé de sauvegarde ni de test de restauration.

Ces opérations constitueraient des améliorations importantes pour permettre la récupération des données après certains incidents.

Elles ne figurent toutefois pas comme une procédure PostgreSQL explicitement imposée dans le cahier des charges.

Haute disponibilité et réplication

Notre architecture repose sur une seule instance PostgreSQL.

Une architecture avec réplication et basculement pourrait améliorer la disponibilité du service, mais elle introduirait une complexité supplémentaire.

Le professeur demande au contraire de privilégier une architecture simple, fonctionnelle et explicable sur une seule VM.

Nous considérons donc la haute disponibilité PostgreSQL comme une possibilité d'évolution, et non comme une obligation du projet.

Renforcement de l'authentification

Nous avons constaté que certaines connexions locales utilisent la méthode trust, tandis que les connexions réseau correspondantes exigent une authentification par mot de passe.

Nous pourrions examiner les règles locales et réaliser des vérifications supplémentaires.

Ces améliorations doivent toutefois être distinguées des exigences générales de sécurité du professeur, qui restent obligatoires.

Travaux nécessaires pour poursuivre la plateforme

Même si certaines améliorations PostgreSQL peuvent être reportées, notre travail sur la plateforme n'est pas terminé.

Nous devons notamment poursuivre le déploiement des services imposés par le cahier des charges, dont Drupal Commerce, Moodle, Keycloak, n8n et Portainer CE.

Nous devons également assurer leur intégration, notamment le parcours fonctionnel entre Drupal Commerce, n8n et Moodle, ainsi que le SSO entre Keycloak et Moodle.

Les services qui utilisent PostgreSQL devront disposer d'une configuration de connexion adaptée, avec des bases de données et des permissions correspondant à leurs besoins.

La segmentation réseau, la réduction des privilèges, la sécurité des images et la limitation de l'exposition des services font également partie des exigences du professeur.

Notre orientation pour la suite

Nous privilégions donc la poursuite du déploiement de la plateforme et la démonstration des mécanismes de sécurité demandés.

Les améliorations supplémentaires de PostgreSQL seront documentées comme des possibilités d'évolution, sans les présenter comme des obligations ni comme des fonctionnalités déjà réalisées.

Cette approche correspond à l'objectif pédagogique du travail : réaliser une plateforme fonctionnelle, cohérente, suffisamment sécurisée, reproductible et dont nous sommes capables d'expliquer les choix techniques et les limites.

### 7.12. Conclusion de la section

Notre déploiement PostgreSQL constitue une première base fonctionnelle pour la plateforme.

Nous avons configuré le service avec Docker Compose, créé un secret administrateur, utilisé un réseau Docker interne et confirmé la persistance des données après suppression et recréation du conteneur.

Nous avons également vérifié certains comportements d'authentification et confirmé que le port PostgreSQL n'était pas publié sur la VM.

Cependant, notre architecture présente encore plusieurs limites.

Elle repose sur une seule instance PostgreSQL et sur le stockage de notre VM. Nous n'avons pas encore testé de sauvegarde et de restauration, créé les comptes applicatifs définitifs ni terminé les vérifications d'authentification et de chiffrement.

Ces limites ne remettent pas en cause les tests réussis. Elles précisent simplement ce que notre déploiement permet actuellement de garantir et ce qui reste à améliorer.

Notre objectif est de présenter une architecture dont nous connaissons le fonctionnement, les protections vérifiées et les limites, plutôt que de considérer qu'un service est entièrement sécurisé simplement parce qu'il démarre correctement.
