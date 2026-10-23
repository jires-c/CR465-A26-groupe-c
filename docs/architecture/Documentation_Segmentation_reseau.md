# **1. Objectif et principes de la segmentation réseau**

## **1.1. Contexte de notre projet**

Dans le cadre du travail de session du cours CR465 — Technologies et sécurité des conteneurs, nous devons mettre en place une plateforme composée de plusieurs services exécutés dans des conteneurs Docker.

Ces services n'ont pas tous le même rôle. Certains doivent être accessibles aux utilisateurs par leur navigateur, d'autres assurent les communications entre applications, tandis que d'autres encore conservent des données ou participent à l'administration technique de la plateforme.

Il serait donc peu judicieux de connecter tous ces services à un seul réseau Docker sans distinction.

Nous avons choisi de mettre en place une segmentation réseau afin de séparer les différents types de communications et de limiter les accès inutiles.

Cette organisation répond à l'objectif du professeur de distinguer trois zones principales : une zone d'exposition, une zone applicative et une zone de données.

Nous avons ajouté un quatrième réseau pour les communications techniques entre Traefik et le socket-proxy Docker.

## **1.2. Qu'est-ce que la segmentation réseau ?**

La segmentation réseau consiste à diviser un environnement informatique en plusieurs réseaux ou zones de communication.

L'objectif est de ne pas permettre systématiquement à tous les équipements ou services de communiquer directement entre eux.

Dans notre cas, les équipements à organiser sont principalement des conteneurs Docker.

Prenons un exemple.

Un utilisateur doit pouvoir accéder à Moodle depuis son navigateur. Cependant, cet utilisateur n'a pas besoin d'accéder directement au serveur PostgreSQL qui conserve les données de l'application.

De même, Traefik doit pouvoir transmettre les requêtes web vers Moodle, mais il n'a pas besoin d'administrer directement les bases de données.

Nous cherchons donc à autoriser les communications nécessaires au fonctionnement de la plateforme et à limiter celles qui ne sont pas justifiées.

**Précision — Exigences du professeur et justification de notre choix**

Dans la section « Segmentation réseau » de l’énoncé du travail, le professeur demande de distinguer trois zones : le réseau d’exposition, le réseau applicatif et le réseau de données.

Il demande également de justifier les communications nécessaires entre services et de préciser les accès volontairement évités. Les services de données, notamment PostgreSQL, ne doivent pas être directement exposés aux utilisateurs par Internet ou par Traefik.

Notre quatrième réseau, socket-net, répond à un besoin technique supplémentaire : isoler les échanges entre Traefik et le socket-proxy. Il complète les trois zones demandées sans les remplacer.

Cette organisation s’inscrit dans une démarche de défense en profondeur : la séparation réseau doit être complétée par l’authentification, la limitation des privilèges, la protection des secrets et les contrôles sur l’hôte.

## **1.3. Les quatre réseaux retenus**

Nous avons retenu les réseaux suivants :

### **1.3.1. frontend-net — Réseau d'exposition**

Ce réseau est destiné aux communications entre Traefik et les applications web qui doivent être accessibles aux utilisateurs.

Traefik constitue le point d'entrée de la plateforme.

Les applications comme Drupal Commerce, Moodle et Keycloak pourront être connectées à ce réseau pour recevoir les requêtes transmises par Traefik.

Cela ne signifie pas que chaque application devra publier directement un port sur la VM.

Au contraire, notre objectif est de centraliser l'accès web par Traefik lorsque cela est approprié.

### **1.3.2. app-net — Réseau applicatif**

Ce réseau est destiné aux communications internes entre les applications.

Par exemple, dans le scénario demandé par le professeur, Drupal Commerce devra déclencher un traitement dans n8n, qui devra ensuite communiquer avec Moodle.

Ces échanges pourront utiliser app-net, selon les méthodes d'intégration retenues.

Le réseau applicatif permet ainsi d'organiser les communications entre services sans les faire systématiquement passer par le réseau d'exposition.

### **1.3.3. data-net — Réseau de données**

Ce réseau est destiné aux communications avec les services de données, notamment PostgreSQL.

Une application qui utilise PostgreSQL doit pouvoir communiquer avec ce serveur.

En revanche, une application qui n'en a pas besoin ne devrait pas être connectée automatiquement au réseau de données.

L'objectif est de limiter la connectivité vers les services qui conservent des informations importantes.

La séparation réseau doit toutefois être complétée par des contrôles d'authentification et des permissions appropriées.

### **1.3.4. socket-net — Réseau technique Docker**

Nous avons ajouté ce réseau pour permettre les communications entre Traefik et le socket-proxy.

Traefik doit obtenir certaines informations du moteur Docker pour découvrir les services et appliquer ses règles de routage.

Plutôt que de lui donner directement accès au socket Docker, nous utilisons un socket-proxy comme intermédiaire.

socket-net permet de réserver cette communication à une zone technique distincte.

Il s'agit d'un réseau supplémentaire par rapport aux trois zones fonctionnelles demandées par le professeur.

## **1.4. Pourquoi ne pas utiliser un seul réseau ?**

Un réseau unique serait plus simple à configurer, mais tous les conteneurs qui y seraient connectés pourraient potentiellement communiquer directement entre eux.

Par exemple, si Traefik, Moodle, PostgreSQL et le socket-proxy appartenaient tous au même réseau, la séparation entre les fonctions web, applicatives, données et administration Docker serait moins claire.

En utilisant plusieurs réseaux, nous pouvons choisir plus précisément quels services partagent une connectivité.

Cette séparation permet également de mieux documenter les communications attendues.

Elle ne garantit cependant pas, à elle seule, qu'un service compromis ne pourra jamais atteindre d'autres ressources.

## **1.5. Principe du moindre accès réseau**

Notre approche repose sur une règle simple :

**Un conteneur doit être connecté uniquement aux réseaux nécessaires à son fonctionnement.**

Par exemple :

- Traefik doit communiquer avec les applications web et avec le socket-proxy, mais pas directement avec PostgreSQL.

- PostgreSQL doit communiquer avec les applications qui utilisent ses bases de données, mais n'a pas besoin d'être exposé aux utilisateurs.

- n8n doit pouvoir joindre les applications nécessaires à ses workflows, mais ne doit pas automatiquement accéder à tous les services techniques.

Cette logique devra être appliquée progressivement à mesure que les conteneurs seront déployés.

## **1.6. Les limites de la segmentation Docker**

Il est important de préciser qu'un réseau Docker n'est pas automatiquement équivalent à un pare-feu complet.

Lorsque deux conteneurs appartiennent au même réseau, ils disposent généralement d'une connectivité réseau entre eux.

Le fait de déclarer un réseau avec internal: true ne bloque pas les communications entre les conteneurs qui partagent ce réseau.

Cette option sert principalement à limiter la connectivité externe du réseau concerné.

Par conséquent, notre sécurité ne doit pas reposer uniquement sur la séparation en quatre réseaux.

Nous devrons également tenir compte des ports publiés, des autorisations applicatives, des comptes de service, des secrets et des règles de sécurité de la VM.

Le pare-feu de l'hôte constitue un contrôle distinct dont la configuration devra être vérifiée.

## **1.7. Une architecture progressive**

Tous les services du projet ne sont pas encore déployés.

Nous avons commencé par les composants d'infrastructure, notamment Traefik, le socket-proxy et PostgreSQL.

Les autres applications seront intégrées progressivement.

Nous distinguons donc :

- Les réseaux déclarés dans notre configuration Compose.

- Les réseaux effectivement créés par Docker.

- Les conteneurs réellement connectés à ces réseaux.

- Les communications déjà testées.

- Les communications prévues, mais non encore vérifiées.

Cette distinction est importante pour présenter fidèlement l'avancement du projet.

## **1.8. Conclusion**

Notre segmentation réseau vise à organiser les communications de la plateforme selon le rôle des services.

Les quatre réseaux retenus permettent de distinguer l'exposition web, les échanges applicatifs, l'accès aux données et les communications techniques avec Docker.

Cette organisation répond aux trois zones fonctionnelles demandées par le professeur, tout en ajoutant une séparation spécifique pour le socket-proxy.

La suite de la documentation présente la déclaration des réseaux dans Docker Compose, leur création effective et les vérifications déjà réalisées.

# **2. Déclaration, création et vérification des réseaux Docker**

## **2.1. Objectif de cette étape**

Après avoir défini les quatre réseaux nécessaires à notre architecture, nous avons procédé à leur déclaration dans notre fichier Docker Compose.

L'objectif était de préparer les zones de communication qui seront utilisées par les différents conteneurs de la plateforme.

Cette étape doit être distinguée du déploiement des services : nous pouvons prévoir plusieurs réseaux dans le fichier Compose sans que tous soient immédiatement créés ou utilisés par Docker.

Nous avons donc vérifié progressivement la présence des réseaux et observé leur création lors du déploiement des premiers services.

## **2.2. Choix des réseaux et justification de l'absence de backend-net**

Nous avons retenu les quatre réseaux suivants :

| **Réseau**   | **Fonction principale**                                    |
|--------------|------------------------------------------------------------|
| frontend-net | Communications entre Traefik et les applications web       |
| app-net      | Communications internes entre applications                 |
| data-net     | Communications avec les services de données                |
| socket-net   | Communications techniques entre Traefik et le socket-proxy |

**Pourquoi avons-nous envisagé un réseau backend-net ?**

Pendant notre réflexion sur l'architecture, nous avons également envisagé la possibilité d'ajouter un réseau appelé backend-net.

Dans certaines architectures, le terme *backend* désigne les composants qui assurent les traitements internes, par opposition au *frontend*, qui correspond à la partie accessible aux utilisateurs.

Nous aurions donc pu utiliser backend-net pour regrouper les communications internes de plusieurs services.

Cependant, nous disposions déjà de deux réseaux répondant à des besoins plus précis :

- app-net, destiné aux échanges entre applications ;

- data-net, destiné aux communications avec les bases de données et les autres services de données.

L'ajout de backend-net aurait donc risqué de créer un chevauchement entre plusieurs réseaux ayant des fonctions proches.

Il aurait également fallu justifier quels conteneurs devraient appartenir à backend-net plutôt qu'à app-net ou data-net.

**Pourquoi avons-nous décidé de ne pas l'ajouter ?**

Après avoir examiné les exigences du professeur et les schémas d'architecture de l'énoncé, nous avons choisi de conserver nos quatre réseaux.

Le professeur demande une séparation logique entre :

1.  Le réseau d'exposition.

2.  Le réseau applicatif.

3.  Le réseau de données.

Notre organisation répond directement à ces trois catégories.

Le réseau supplémentaire socket-net possède une fonction technique particulière : séparer les communications avec le socket-proxy Docker.

Nous avons donc estimé qu'un cinquième réseau backend-net n'apporterait pas, à ce stade, une séparation suffisamment distincte pour justifier sa complexité supplémentaire.

**Notre décision est de conserver frontend-net, app-net, data-net et socket-net, sans créer backend-net.**

Ce choix pourra être réévalué si un besoin technique précis apparaît, mais nous ne prévoyons pas de multiplier les réseaux sans justification.

## **2.3. Déclaration des réseaux dans compose.yaml**

Notre fichier Docker Compose se trouve dans le répertoire suivant de la VM Ubuntu :

> /opt/ac-platform/compose/compose.yaml

Nous y avons déclaré les quatre réseaux.

La partie pertinente de notre configuration est la suivante :

> networks:
>
> frontend-net:
>
> socket-net:
>
> internal: true
>
> app-net:
>
> internal: true
>
> data-net:
>
> internal: true

Cette déclaration indique à Docker Compose les réseaux que nous souhaitons utiliser pour les services de notre projet.

frontend-net est destiné aux communications avec les applications web exposées par Traefik.

Les trois autres réseaux sont déclarés avec l'option internal: true.

Nous devons toutefois bien comprendre la portée de cette option.

**Précision — Lecture de la configuration YAML**

Dans le bloc networks, chaque nom (frontend-net, socket-net, app-net et data-net) désigne un réseau logique géré par Compose. Les lignes indentées internal: true sont des paramètres de configuration propres au réseau concerné.

Le réseau frontend-net ne porte pas cette option dans l’extrait présenté. Cela ne signifie pas qu’il rend automatiquement toutes les applications accessibles depuis Internet : l’exposition effective dépend notamment des ports publiés et des règles de Traefik.

## **2.4. Signification de l'option internal: true**

Dans Docker Compose, l'option internal: true permet de créer un réseau Docker isolé de la connectivité externe habituelle.

Nous l'avons utilisée pour les réseaux qui n'ont pas vocation à servir directement de point d'entrée depuis l'extérieur :

- socket-net ;

- app-net ;

- data-net.

Par exemple, PostgreSQL est connecté à data-net.

Nous voulons qu'une application autorisée puisse communiquer avec PostgreSQL, sans que le réseau de données serve de réseau d'exposition vers l'extérieur.

L'option internal: true contribue à cette séparation.

**Ce que internal: true ne signifie pas**

Il serait incorrect d'affirmer que cette option empêche automatiquement les conteneurs d'un même réseau de communiquer entre eux.

Au contraire, deux conteneurs connectés à un même réseau Docker peuvent généralement communiquer directement, sous réserve de leur configuration et des contrôles applicatifs.

Prenons un exemple.

Supposons que Moodle et PostgreSQL soient tous deux connectés à data-net.

L'option internal: true ne doit pas empêcher Moodle de communiquer avec PostgreSQL : cette communication est précisément la raison pour laquelle nous les aurions connectés au même réseau.

Elle ne permet pas non plus de décider, à elle seule, qu'un conteneur du réseau peut communiquer avec PostgreSQL tandis qu'un autre conteneur du même réseau ne le peut pas.

Pour obtenir des restrictions plus fines, nous devons utiliser d'autres contrôles, notamment les autorisations des applications, les comptes PostgreSQL, les règles de filtrage appropriées et la limitation des connexions réseau.

**Autre limite importante**

Un conteneur connecté à plusieurs réseaux peut disposer de plusieurs chemins de communication.

Par conséquent, la présence de internal: true sur un réseau ne signifie pas automatiquement que chaque conteneur qui y appartient est complètement isolé de toute autre connectivité.

Nous devons examiner l'ensemble des réseaux auxquels chaque conteneur est connecté.

**Notre interprétation est donc la suivante : internal: true contribue à isoler un réseau de la connectivité externe, mais ne constitue pas un pare-feu entre les membres de ce réseau.**

## **2.5. Différence entre déclarer, créer et utiliser un réseau**

Cette distinction est importante, car elle explique les résultats que nous avons observés pendant nos manipulations.

**Déclarer un réseau**

Déclarer un réseau consiste à inscrire sa définition dans le fichier compose.yaml.

Par exemple :

> app-net:
>
> internal: true

Cela signifie que nous prévoyons un réseau appelé app-net dans notre projet Compose.

Cependant, cette déclaration ne prouve pas que le réseau existe déjà dans Docker.

**Créer un réseau**

Créer un réseau signifie que Docker a effectivement créé l'objet réseau.

Nous pouvons vérifier sa présence à l'aide de la commande :

> sudo docker network ls

Lorsque Docker Compose crée un réseau, son nom réel comprend généralement le nom du projet Compose comme préfixe.

Dans notre environnement, nous avons observé des noms tels que :

> compose_frontend-net
>
> compose_socket-net
>
> compose_data-net

Le préfixe compose\_ correspond au nom du projet Compose utilisé lors du déploiement.

**Utiliser un réseau**

Un réseau est utilisé lorsqu'un conteneur y est connecté.

Par exemple, notre conteneur PostgreSQL utilise data-net.

Il faut donc distinguer trois situations :

- Le réseau est déclaré dans le fichier Compose.

- Le réseau existe effectivement dans Docker.

- Un ou plusieurs conteneurs sont connectés à ce réseau.

Ces trois situations ne sont pas équivalentes.

## **2.6. Première vérification des réseaux Docker**

Après avoir déclaré nos quatre réseaux dans le fichier Compose, nous avons exécuté :

> sudo docker network ls

Nous souhaitions vérifier si Docker avait créé les quatre réseaux prévus.

**Résultat observé**

À ce moment-là, seuls deux réseaux de notre projet apparaissaient :

> compose_frontend-net
>
> compose_socket-net

Les réseaux suivants n'apparaissaient pas encore :

> compose_app-net
>
> compose_data-net

**Notre première interrogation**

Nous nous sommes demandé pourquoi Docker n'avait pas créé les quatre réseaux alors qu'ils figuraient tous dans compose.yaml.

Nous avons alors distingué la déclaration d'un réseau dans la configuration Compose de sa création effective dans Docker.

À ce stade, les services déjà déployés utilisaient frontend-net et socket-net.

Les autres réseaux étaient prévus pour les services que nous n'avions pas encore déployés.

Nous ne pouvions donc pas conclure que notre configuration était nécessairement incorrecte simplement parce que les quatre réseaux n'apparaissaient pas dans la liste.

## **2.7. Tentative de création des réseaux avec Docker Compose**

Nous avons ensuite exécuté la commande suivante :

> sudo docker compose -f /opt/ac-platform/compose/compose.yaml up -d --no-recreate

**Pourquoi avons-nous utilisé cette commande ?**

Nous voulions demander à Docker Compose de prendre en compte la configuration du projet, tout en évitant de recréer les conteneurs déjà présents.

L'option -d permet de lancer les services en arrière-plan.

L'option --no-recreate indique à Compose de ne pas recréer les conteneurs existants.

**Résultat observé**

Après cette commande, nous avons exécuté de nouveau :

> sudo docker network ls

Le résultat montrait toujours uniquement :

> compose_frontend-net
>
> compose_socket-net

Les réseaux compose_app-net et compose_data-net n'avaient donc pas été créés à cette étape.

**Ce que nous en avons conclu**

Nous avons constaté que l'exécution de cette commande n'avait pas suffi, dans notre situation, à faire apparaître les deux réseaux inutilisés.

Cela ne signifie pas que up -d --no-recreate ne peut jamais créer de réseau.

Cela signifie simplement que, lors de notre manipulation, cette commande n'a pas entraîné la création des réseaux app-net et data-net.

Nous avons donc décidé de ne pas forcer leur création manuellement à ce moment-là.

## **2.8. Pourquoi n'avons-nous pas créé les réseaux manuellement ?**

Docker permet de créer un réseau directement avec la commande docker network create.

Cependant, notre projet est organisé avec Docker Compose.

Nous souhaitons que les réseaux utilisés par nos services soient décrits et gérés de manière cohérente dans compose.yaml.

Créer manuellement des réseaux qui sont censés être gérés par Compose aurait ajouté une opération extérieure à notre configuration déclarative.

Nous aurions alors dû nous assurer que ces réseaux manuels possédaient exactement les caractéristiques attendues et que Compose les utilisait correctement.

Nous avons donc préféré laisser Docker Compose créer les réseaux lorsqu'ils deviendraient nécessaires au déploiement des services.

Cette décision était également cohérente avec notre volonté de garder une configuration reproductible.

**Précision — Infrastructure présente avant PostgreSQL**

Au moment des premières vérifications, Traefik et le socket-proxy étaient les composants d’infrastructure déjà déployés. Les réseaux frontend-net et socket-net étaient donc les seuls réseaux du projet effectivement observés dans docker network ls.

Cette précision aide à comprendre pourquoi data-net n’est apparu que lors du déploiement de PostgreSQL et pourquoi la présence de app-net restait à confirmer.

## **2.9. Création de data-net lors du déploiement de PostgreSQL**

Nous avons ensuite ajouté et déployé PostgreSQL.

Ce service a été configuré pour utiliser le réseau data-net.

Nous avons exécuté :

> sudo docker compose -f /opt/ac-platform/compose/compose.yaml up -d --no-deps postgres

**Explication de la commande**

> docker compose up demande à Compose de créer ou de démarrer le service concerné.

L'option -d permet de le lancer en arrière-plan.

L'option --no-deps indique de ne pas démarrer automatiquement les services dont PostgreSQL pourrait dépendre dans la configuration Compose.

Le dernier argument, postgres, désigne le service que nous souhaitions déployer.

**Résultat observé**

Lors de ce déploiement, Docker Compose a créé les ressources nécessaires à PostgreSQL.

Nous avons notamment observé la création :

> compose_data-net
>
> compose_postgres-data

compose-postgres-1

Ces trois éléments correspondent à des ressources différentes :

- compose_data-net : réseau Docker utilisé par PostgreSQL.

- compose_postgres-data : volume Docker destiné à conserver les données PostgreSQL.

- compose-postgres-1 : conteneur PostgreSQL.

Cette observation est importante : **le réseau data-net, qui n'apparaissait pas lors de nos premières vérifications, a été créé lorsque nous avons déployé un service qui l'utilisait.**

Cela confirme la chronologie de notre configuration.

## **2.10. Vérification de la connectivité de PostgreSQL sur data-net**

Après le déploiement de PostgreSQL, nous avons effectué un test à partir d'un conteneur client temporaire connecté au réseau compose_data-net.

Notre objectif était de vérifier si un autre conteneur appartenant au réseau de données pouvait atteindre le service PostgreSQL.

**Résultat observé**

Le client a pu joindre le serveur PostgreSQL.

Cependant, la tentative de connexion a échoué parce qu'aucun mot de passe n'avait été fourni.

**Interprétation**

Ce résultat doit être interprété correctement.

Il ne signifie pas que PostgreSQL était inaccessible sur le réseau.

Au contraire, le serveur a été atteint et a demandé une authentification.

Le test a donc fourni deux informations :

1.  Une communication réseau vers PostgreSQL était possible depuis un conteneur connecté à compose_data-net.

2.  PostgreSQL n'a pas accepté cette tentative de connexion sans mot de passe.

Ce test ne démontre pas encore que toutes les communications non autorisées sont bloquées.

Il ne démontre pas non plus qu'une connexion distante avec un mot de passe valide a été réalisée.

Ces vérifications devront être distinguées dans la suite de notre documentation.

## **2.11. Vérification de l'absence de publication du port PostgreSQL**

Nous avons également vérifié si le port de PostgreSQL était publié sur la VM.

La commande utilisée était :

> sudo docker port compose-postgres-1

**Résultat observé**

La commande n'a retourné aucune correspondance de port publié.

**Interprétation**

Cela signifie qu'aucun port du conteneur PostgreSQL n'était publié sur l'hôte par Docker à ce moment-là.

En particulier, le port PostgreSQL 5432 n'était pas publié directement sur la VM.

Cette configuration correspond à notre objectif : les applications doivent accéder à PostgreSQL par le réseau Docker approprié, sans exposer directement le service de base de données aux utilisateurs externes.

Il faut toutefois distinguer l'absence de publication de port de l'ensemble des autres contrôles de sécurité.

Nous devrons également vérifier les réseaux réellement connectés au conteneur, les permissions PostgreSQL et les règles de sécurité de la VM.

## **2.12. Situation actuelle de app-net**

Contrairement à data-net, le réseau app-net n'avait pas encore été créé lors de nos dernières vérifications documentées.

Il est bien déclaré dans compose.yaml, mais nous n'avons pas encore confirmé la présence de :

> compose_app-net

Cela s'explique par le fait que les applications destinées à utiliser ce réseau, comme Drupal Commerce, Moodle et n8n, n'avaient pas encore été déployées.

Nous prévoyons donc de vérifier sa création lorsque nous commencerons à déployer les services applicatifs.

Nous ne devons pas présenter ce réseau comme déjà créé tant que nous n'avons pas obtenu un résultat le confirmant.

## **2.13. Segmentation Docker et pare-feu de la VM**

Notre segmentation repose actuellement sur les réseaux Docker définis dans Compose.

Cependant, le professeur demande également la mise en place d'un pare-feu hôte dans les exigences de préparation de la VM.

Il s'agit de deux éléments distincts.

**Segmentation Docker**

Elle permet d'organiser la connectivité entre les conteneurs selon les réseaux auxquels ils appartiennent.

**Pare-feu de la VM**

Il permet de contrôler certaines communications entrantes et sortantes au niveau de l'hôte, selon sa configuration et son interaction avec les règles réseau de Docker.

Nous ne devons pas supposer que le pare-feu est correctement configuré simplement parce que les réseaux Docker existent.

De plus, la publication de ports Docker et les règles de filtrage de l'hôte doivent être examinées ensemble.

**État actuel**

La configuration et l'efficacité du pare-feu hôte n'ont pas encore été confirmées dans les vérifications documentées ici.

Nous devrons donc les contrôler séparément avant de déclarer cette exigence terminée.

## **2.14. Bilan chronologique de la création des réseaux**

Voici le déroulement réel de notre travail :

| **Étape** | **Action**                                                    | **Résultat**                                                       |
|-----------|---------------------------------------------------------------|--------------------------------------------------------------------|
| 1         | Déclaration des quatre réseaux dans compose.yaml              | Les quatre réseaux sont prévus dans la configuration               |
| 2         | Première commande docker network ls                           | Seuls compose_frontend-net et compose_socket-net apparaissent      |
| 3         | Exécution de docker compose up -d --no-recreate               | Pas de création observée de app-net ni de data-net                 |
| 4         | Décision de ne pas créer manuellement les réseaux             | Gestion conservée dans Docker Compose                              |
| 5         | Déploiement de PostgreSQL avec up -d --no-deps postgres       | Création de compose_data-net, du volume et du conteneur PostgreSQL |
| 6         | Test de connexion depuis un conteneur temporaire sur data-net | PostgreSQL est joignable, mais demande un mot de passe             |
| 7         | Vérification avec docker port compose-postgres-1              | Aucun port publié                                                  |
| 8         | Vérification de app-net                                       | Création non encore confirmée                                      |

Cette chronologie nous permet de présenter des faits observés plutôt que de supposer que toutes les ressources ont été créées au même moment.

## **2.15. Conclusion**

Nous avons déclaré quatre réseaux Docker dans notre fichier Compose afin de préparer la segmentation de la plateforme.

Les premières vérifications ont montré que seuls frontend-net et socket-net étaient effectivement présents.

La commande up -d --no-recreate n'a pas entraîné la création des deux réseaux supplémentaires dans notre situation.

Le réseau data-net a ensuite été créé lors du déploiement de PostgreSQL, ce qui a permis de confirmer son utilisation et de réaliser un premier test de connectivité.

Nous avons également vérifié que PostgreSQL ne publiait pas son port sur la VM.

En revanche, la création de app-net, les tests complets de communication entre zones et la configuration du pare-feu hôte restent à confirmer.

Ces éléments feront partie des prochaines vérifications de notre architecture.

# **3. Répartition des conteneurs entre les réseaux et justification des communications**

## **3.1. Objectif de la répartition des conteneurs**

Après avoir déclaré nos quatre réseaux Docker dans le fichier compose.yaml, nous devons déterminer quels conteneurs seront connectés à chacun de ces réseaux.

Cette étape est essentielle, car la simple création de réseaux distincts ne suffit pas à assurer une segmentation efficace.

Si nous connectons tous nos conteneurs aux quatre réseaux, nous risquons de perdre une grande partie des avantages recherchés par cette séparation.

Notre objectif est donc de permettre les communications nécessaires au fonctionnement de la plateforme, tout en évitant les connexions inutiles.

Nous nous appuyons sur le principe du **moindre accès réseau** : chaque conteneur doit être connecté uniquement aux réseaux dont il a réellement besoin.

Pour notre projet, nous avons retenu quatre réseaux :

- frontend-net : réseau d'exposition.

- app-net : réseau applicatif.

- data-net : réseau de données.

- socket-net : réseau technique destiné à l'accès au socket-proxy Docker.

Les trois premiers correspondent aux zones demandées par le professeur. Le quatrième est une mesure supplémentaire que nous avons choisie pour mieux protéger l'accès au moteur Docker.

## **3.2. Une particularité importante : un conteneur peut appartenir à plusieurs réseaux**

Un conteneur Docker n'est pas nécessairement limité à un seul réseau.

Nous pouvons le connecter à plusieurs réseaux lorsqu'il doit communiquer avec des services appartenant à différentes zones.

Cette possibilité est importante pour notre architecture.

Par exemple, une application web peut avoir besoin de recevoir les requêtes transmises par Traefik et de communiquer avec PostgreSQL.

Elle pourrait donc être connectée à un réseau partagé avec Traefik et à un réseau partagé avec PostgreSQL.

Cependant, **appartenir à plusieurs réseaux ne signifie pas que le conteneur doit automatiquement servir de passerelle entre eux**.

Il ne faut pas non plus considérer qu'une application connectée à plusieurs réseaux constitue, à elle seule, une barrière de sécurité entre ces réseaux.

Nous devons donc choisir soigneusement les connexions de chaque service.

## **3.3. Traefik : point d'entrée des applications web**

Traefik joue le rôle de reverse proxy dans notre plateforme.

Lorsqu'un utilisateur souhaite accéder à une application web, sa requête doit passer par Traefik plutôt que d'atteindre directement le port interne du conteneur applicatif.

Traefik reçoit la requête, examine les règles de routage et la transmet au service concerné.

**Réseaux nécessaires**

**frontend-net**

Traefik utilise ce réseau pour communiquer avec les applications web qui y seront connectées.

Par exemple, si Moodle doit être accessible par Traefik, les deux conteneurs devront partager un réseau permettant cette communication.

Dans notre architecture proposée, ce réseau commun sera frontend-net.

**socket-net**

Traefik utilise également ce réseau pour communiquer avec le socket-proxy.

Le socket-proxy lui permet d'obtenir les informations Docker nécessaires à la découverte des services, sans que Traefik ait directement accès au socket Docker.

**Réseaux que nous ne prévoyons pas d'attribuer à Traefik**

Nous ne prévoyons pas de connecter Traefik à data-net.

Traefik n'a pas besoin d'accéder directement aux bases de données pour transmettre les requêtes HTTP aux applications.

Nous ne prévoyons pas non plus de lui donner systématiquement accès à app-net, puisque les applications web pourront partager frontend-net avec lui.

Cette répartition permet de limiter la connectivité de Traefik.

**État actuel**

Traefik et le socket-proxy ont déjà été déployés et testés.

Nous avons également testé l'accès HTTPS avec un certificat local.

Cependant, les routes vers les applications qui restent à déployer ne sont pas encore opérationnelles.

## **3.4. Socket-proxy : accès contrôlé à l'API Docker**

Le socket Docker permet de communiquer avec le moteur Docker.

Cet accès est sensible, car l'API Docker peut permettre d'effectuer des opérations importantes sur les conteneurs et sur l'environnement hôte.

Nous avons donc choisi de ne pas monter directement le socket Docker dans le conteneur Traefik.

Nous utilisons un socket-proxy comme intermédiaire.

**Réseau nécessaire**

**socket-net**

Le socket-proxy doit être accessible à Traefik par ce réseau.

Il n'a pas besoin d'appartenir au réseau de données pour remplir cette fonction.

Il n'a pas non plus besoin d'être directement exposé aux utilisateurs de la plateforme.

**Limite importante**

Le fait d'utiliser un socket-proxy ne suffit pas, à lui seul, à garantir que toutes les opérations dangereuses sont interdites.

Nous devons également vérifier les permissions et les catégories de requêtes autorisées par sa configuration.

**État actuel**

Le socket-proxy a été déployé et utilisé avec Traefik.

Les vérifications de son fonctionnement ont été effectuées lors de notre configuration initiale.

Une vérification détaillée des permissions réellement autorisées devra être conservée dans la documentation de sécurité correspondante.

## **3.5. PostgreSQL : service de données**

PostgreSQL est notre serveur de bases de données.

Il est destiné à conserver les informations des applications qui utiliseront ce système de gestion de bases de données.

Contrairement à Traefik, PostgreSQL n'a pas besoin d'être directement accessible depuis le navigateur d'un utilisateur.

**Réseau nécessaire**

**data-net**

Nous avons connecté PostgreSQL uniquement à ce réseau dans notre configuration Compose.

Le service est déclaré avec :

> networks:

\- data-net

Cette configuration permet aux conteneurs également connectés à data-net de joindre PostgreSQL, sous réserve des règles réseau et des contrôles appliqués par le serveur.

**Réseaux que nous ne prévoyons pas de lui attribuer**

Nous ne prévoyons pas de connecter PostgreSQL à frontend-net.

Nous ne prévoyons pas non plus de le connecter à socket-net.

PostgreSQL n'a pas besoin d'accéder à l'API Docker pour remplir sa fonction de serveur de bases de données.

**Vérifications réalisées**

Nous avons confirmé que PostgreSQL ne publiait pas son port 5432 sur la VM.

Nous avons également effectué un test depuis un conteneur temporaire connecté à compose_data-net.

Le conteneur client a pu joindre PostgreSQL, mais la connexion a été refusée parce qu'aucun mot de passe n'avait été fourni.

Ces résultats démontrent que le réseau de données fonctionne pour cette communication et que PostgreSQL exige une authentification pour cette connexion.

Ils ne constituent pas encore un test complet de l'isolation entre tous les réseaux.

## **3.6. Drupal Commerce : application de commerce électronique**

Drupal Commerce constitue la partie commerce électronique de notre plateforme.

Dans le scénario demandé par le professeur, un achat simulé dans Drupal Commerce doit permettre de déclencher un traitement automatisé qui contribuera à l'attribution d'un accès à Moodle.

Drupal Commerce doit donc être accessible aux utilisateurs, mais aussi participer aux communications internes nécessaires à ce scénario.

**Réseaux proposés**

**frontend-net**

Drupal Commerce devra partager un réseau avec Traefik pour recevoir les requêtes web transmises par le reverse proxy.

**app-net**

Drupal Commerce pourrait avoir besoin de communiquer avec n8n ou d'autres services applicatifs pour transmettre les informations nécessaires au scénario d'intégration.

Cette communication devra être précisée en fonction de la méthode retenue : appel HTTP, webhook ou autre mécanisme prévu par les applications.

**Accès aux données**

L'accès de Drupal Commerce à une base de données devra être défini selon le moteur de base de données réellement retenu et compatible avec l'installation choisie.

Nous ne devons pas supposer que Drupal Commerce utilisera nécessairement notre instance PostgreSQL avant d'avoir vérifié cette compatibilité et les exigences de son déploiement.

**État actuel**

Drupal Commerce n'est pas encore déployé.

Sa répartition réseau constitue donc une proposition d'architecture à confirmer lors de son installation.

## **3.7. Moodle : plateforme d'apprentissage**

Moodle est destiné à fournir l'environnement pédagogique de notre plateforme.

Il doit être accessible aux utilisateurs et participer au scénario d'attribution d'accès après un achat simulé.

Le professeur demande également une intégration SSO avec Keycloak.

**Réseaux proposés**

**frontend-net**

Moodle devra être accessible par l'intermédiaire de Traefik.

**app-net**

Moodle pourrait avoir besoin de communiquer avec les autres services applicatifs, notamment pour les mécanismes d'intégration avec n8n et Keycloak.

Nous devrons déterminer précisément quels échanges se produisent entre ces services.

**Accès aux données**

Si Moodle utilise notre instance PostgreSQL, son conteneur devra pouvoir joindre PostgreSQL par data-net.

Il faudra alors prévoir une base de données et un compte adaptés à Moodle.

Cette configuration n'a pas encore été réalisée.

**État actuel**

Moodle n'est pas encore déployé.

Ses réseaux définitifs et ses connexions devront être confirmés lors de son installation.

## **3.8. Keycloak : gestion de l'identité et authentification**

Keycloak est destiné à assurer des fonctions de gestion des identités et d'authentification.

Dans notre projet, il doit notamment participer à l'intégration SSO avec Moodle.

**Réseaux proposés**

**frontend-net**

Keycloak devra être accessible par les clients qui participent au parcours d'authentification, généralement par l'intermédiaire de Traefik.

**app-net**

Ce réseau pourra être utilisé pour les communications applicatives nécessaires à l'intégration de Keycloak avec les autres services.

**Accès aux données**

Keycloak peut utiliser PostgreSQL comme base de données.

Si nous retenons cette configuration, il devra également être connecté à data-net.

Nous devrons créer une base et un compte spécifiques, puis vérifier les permissions accordées.

**État actuel**

Keycloak n'est pas encore déployé.

Sa connexion à PostgreSQL et son intégration SSO avec Moodle restent à réaliser.

## **3.9. n8n : automatisation des processus**

n8n est le service d'automatisation de notre plateforme.

Il doit permettre de réaliser le scénario fonctionnel demandé par le professeur.

Dans ce scénario, un événement lié à un achat simulé dans Drupal Commerce doit déclencher un workflow contribuant à l'attribution d'un accès à Moodle.

**Réseaux proposés**

**app-net**

Ce réseau est particulièrement important pour n8n, car il doit communiquer avec d'autres services applicatifs.

Selon la méthode d'intégration retenue, n8n pourra recevoir un événement de Drupal Commerce et effectuer des appels vers les services nécessaires au traitement.

**frontend-net, si nécessaire**

Si nous devons accéder à l'interface web de n8n par Traefik ou recevoir des webhooks par ce point d'entrée, une connexion à frontend-net pourra être nécessaire.

Nous ne devons pas lui attribuer ce réseau sans vérifier les besoins réels d'exposition.

**Accès aux données**

Si n8n utilise PostgreSQL pour conserver ses propres données, il devra être connecté à data-net.

Il faudra alors lui attribuer une base et un compte appropriés.

**État actuel**

n8n n'est pas encore déployé.

Ses communications avec Drupal Commerce, Moodle et PostgreSQL restent à configurer et à tester.

## **3.10. Portainer CE : administration des conteneurs**

Portainer CE est un outil de gestion des environnements conteneurisés.

Le professeur demande son intégration à la plateforme.

Son rôle est différent de celui des applications destinées aux utilisateurs.

Portainer permet d'administrer des ressources Docker et doit donc être considéré comme un service sensible.

**Réseau proposé**

Nous devrons définir une méthode d'accès administratif à Portainer.

Une possibilité serait de publier son interface par l'intermédiaire de Traefik, avec des restrictions d'accès appropriées.

Cependant, ce choix doit être évalué avant sa mise en place, car il ne serait pas souhaitable d'exposer librement une interface d'administration.

**Accès au moteur Docker**

Portainer doit disposer d'un moyen approprié pour administrer Docker.

Cette question est distincte de l'utilisation du socket-proxy par Traefik.

Nous ne devons pas supposer que les permissions limitées du socket-proxy destiné à Traefik seront suffisantes pour les fonctions d'administration de Portainer.

**État actuel**

Portainer CE n'est pas encore déployé.

Son mode de connexion au moteur Docker et son exposition réseau restent à définir.

## **3.11. Composants complémentaires prévus dans l'énoncé**

En plus des services principaux, le professeur propose plusieurs composants facultatifs destinés à enrichir la plateforme.

Nous devons les prendre en compte dans notre réflexion sur la segmentation réseau, même si nous ne décidons pas nécessairement de les déployer.

### **3.11.1. OpenAppSec — Protection applicative WAF**

OpenAppSec est proposé comme une couche de protection applicative pouvant être placée en frontal des services web.

Son rôle serait d'analyser les requêtes entrantes afin de contribuer à la protection des applications contre certaines attaques web.

Dans notre architecture, son intégration concernerait principalement la couche d'exposition et son fonctionnement avec Traefik.

Le professeur précise qu'OpenAppSec constitue un bonus facultatif et que son absence ne bloque pas le déploiement de Traefik.

Si nous décidons de l'intégrer, il devra fonctionner en mode local, sans dépendance à un compte cloud tiers.

**État actuel : composant facultatif non déployé. Son intégration réseau reste à déterminer si le groupe décide de le retenir.**

### **3.11.2. Opencast — Service de gestion vidéo**

Opencast est proposé comme un service vidéo compatible avec Moodle.

Il pourrait servir à gérer des contenus vidéo destinés aux activités pédagogiques.

Son intégration nécessiterait d'examiner les communications avec Moodle et les éventuels besoins d'accès web.

Nous devrions notamment déterminer si son interface doit être accessible par Traefik et quelles communications internes sont nécessaires.

Le professeur présente Opencast comme optionnel, mais fortement souhaité.

**État actuel : composant non déployé. Répartition réseau à définir si son installation est retenue.**

### **3.11.3. Redis — Cache et gestion des sessions**

Redis est proposé comme composant bonus pour le cache ou la gestion des sessions lorsque cela est pertinent.

Contrairement aux applications web, Redis n'a normalement pas besoin d'être directement accessible aux utilisateurs.

Si nous décidons de l'intégrer, nous devrons déterminer quelles applications en ont réellement besoin et limiter les connexions à ces services.

Le professeur place les services de données, notamment Redis, dans la zone de données de son architecture.

Une intégration éventuelle à data-net serait donc cohérente avec cette organisation, sous réserve de vérifier les besoins réels.

Nous éviterions de publier directement Redis sur la VM ou de l'exposer par Traefik.

**État actuel : composant bonus non déployé.**

### **3.11.4. Grafana et Loki — Observabilité**

Le professeur demande un volet minimal d'observabilité et propose Grafana et Loki lorsque leur intégration reste réalisable sans compromettre la qualité globale du projet.

Grafana peut servir à consulter des tableaux de bord et à explorer des informations de surveillance.

Loki est destiné à la collecte et à la consultation des journaux.

Leur intégration nécessiterait une réflexion particulière sur les communications réseau.

Par exemple, une interface Grafana pourrait nécessiter un accès administratif contrôlé, tandis que Loki devrait recevoir les journaux des composants autorisés.

Il faudrait également éviter d'exposer inutilement les interfaces et les données de surveillance.

Nous ne pouvons pas encore attribuer définitivement ces services à nos réseaux sans connaître la méthode de collecte des journaux que nous retiendrons.

**État actuel : Grafana et Loki non déployés. Le mécanisme minimal d'observabilité reste à définir et à documenter.**

### **3.11.5. Notre approche concernant les composants facultatifs**

Nous avons choisi de privilégier une architecture simple et cohérente, conformément à l'objectif pédagogique du travail.

Les composants facultatifs seront donc évalués selon leur utilité, leur complexité et les ressources disponibles dans notre VM.

Nous ne les ajouterons pas uniquement pour multiplier le nombre de conteneurs.

Si nous décidons de ne pas déployer un composant facultatif, nous pourrons expliquer ce choix dans notre documentation.

Inversement, si nous choisissons de l'intégrer, nous devrons préciser son rôle, ses réseaux, ses communications nécessaires et les mesures de sécurité appliquées.

## **3.12. Communications nécessaires et communications à éviter**

### **3.12.1. Objectif**

Le professeur demande que nous soyons capables d'expliquer quels services communiquent entre eux, pourquoi ces communications sont nécessaires et quels accès sont volontairement interdits.

Pour répondre à cette exigence, nous devons partir du fonctionnement attendu de notre plateforme.

Il ne suffit pas de dire que Drupal Commerce, Moodle, Keycloak et n8n appartiendront au réseau applicatif. Nous devons comprendre les échanges nécessaires entre ces services et vérifier que notre segmentation permettra de les réaliser.

Notre architecture doit notamment prendre en charge deux mécanismes :

- Le parcours d'achat simulé et d'attribution d'accès à une formation.

- L'authentification des utilisateurs avec Keycloak, notamment pour Moodle.

Nous allons examiner ces deux mécanismes séparément.

### **3.12.2. Premier scénario : achat d'une formation dans Drupal Commerce**

Le professeur demande qu'un utilisateur puisse consulter une offre de formation dans Drupal Commerce et effectuer un paiement fictif, simulé ou en environnement sandbox.

Nous n'avons donc pas besoin de mettre en place une véritable infrastructure de paiement bancaire.

L'objectif est de démontrer qu'un événement commercial peut déclencher un processus automatisé d'attribution d'accès à une formation.

**Étape 1 — L'utilisateur accède à Drupal Commerce**

L'utilisateur ouvre son navigateur et accède au portail Drupal Commerce.

Sa requête passe par Traefik, notre reverse proxy.

Traefik doit pouvoir transmettre cette requête au conteneur Drupal Commerce.

Pour cela, les deux services devront partager un réseau Docker.

Dans notre architecture proposée, ce réseau sera frontend-net.

Le flux prévu est donc :

Utilisateur → Traefik → Drupal Commerce.

**Étape 2 — L'utilisateur effectue un achat simulé**

L'utilisateur sélectionne une formation et réalise l'opération de paiement simulé.

Drupal Commerce doit alors enregistrer les informations nécessaires au traitement de cette commande.

Cette opération peut nécessiter un accès à sa base de données, selon le moteur retenu lors de son installation.

Nous devrons donc vérifier les exigences techniques de Drupal Commerce avant de définir définitivement son accès à la couche de données.

**Étape 3 — Drupal Commerce transmet un événement à n8n**

Après la confirmation simulée de la commande, un événement doit permettre de déclencher un workflow dans n8n.

Une solution possible consiste à utiliser un webhook.

Dans ce cas, Drupal Commerce envoie une requête HTTP vers une adresse prévue pour recevoir l'événement dans n8n.

Cette communication doit être autorisée entre les deux services.

Nous proposons d'utiliser app-net pour cette communication interne, si la méthode d'intégration retenue permet un appel direct entre les conteneurs.

Il faudra ensuite vérifier que le webhook est correctement configuré et que seuls les événements attendus peuvent déclencher le traitement.

**Étape 4 — n8n traite l'événement**

Lorsque n8n reçoit l'événement, il exécute un workflow.

Ce workflow doit identifier les informations nécessaires à l'attribution de l'accès, notamment l'utilisateur concerné et la formation achetée.

Nous devrons déterminer quelles informations Drupal Commerce transmettra à n8n et comment elles seront utilisées.

Nous devrons également éviter de transmettre des données inutiles ou sensibles dans les journaux et les requêtes.

**Étape 5 — n8n communique avec Moodle**

Le workflow doit ensuite déclencher l'attribution, l'inscription ou l'activation d'un accès dans Moodle.

Cette opération peut être réalisée à l'aide d'une interface de programmation appropriée, selon la configuration choisie.

n8n devra donc pouvoir joindre le service Moodle.

Dans notre architecture proposée, cette communication pourrait passer par app-net, puisque les deux conteneurs appartiendraient à ce réseau.

Cependant, nous devrons vérifier que Moodle dispose des fonctions nécessaires, que l'interface utilisée est activée et que les identifiants possèdent uniquement les permissions requises.

**Étape 6 — L'utilisateur accède à sa formation dans Moodle**

Après l'attribution de l'accès, l'utilisateur doit pouvoir consulter le contenu pédagogique dans Moodle.

Son navigateur passera par Traefik pour accéder à l'interface web de Moodle.

Ce flux utilisera la couche d'exposition.

Le scénario complet peut donc être résumé ainsi :

Utilisateur → Traefik → Drupal Commerce → n8n → Moodle.

Ce résumé représente le parcours fonctionnel attendu. Les échanges réels pourront comprendre plusieurs requêtes et des interactions avec les bases de données et les services d'identité.

**État actuel : scénario demandé par le professeur et communications prévues. L'intégration Drupal Commerce–n8n–Moodle n'a pas encore été configurée ni testée.**

### **3.12.3. Deuxième scénario : authentification avec Keycloak**

Le professeur demande également de configurer un mécanisme de SSO avec Keycloak, au minimum entre Keycloak et Moodle.

Le SSO, ou authentification unique, permet à un utilisateur de s'authentifier auprès d'un fournisseur d'identité afin d'accéder à une application intégrée.

Dans notre architecture, Keycloak jouera le rôle de fournisseur d'identité.

Moodle sera l'une des applications utilisant ce mécanisme.

**Étape 1 — L'utilisateur accède à Moodle**

L'utilisateur ouvre Moodle dans son navigateur.

La requête passe par Traefik avant d'atteindre le service Moodle.

**Étape 2 — Moodle déclenche l'authentification**

Si l'utilisateur doit s'authentifier, Moodle pourra le rediriger vers Keycloak, selon le protocole d'intégration choisi.

Par exemple, une intégration basée sur OpenID Connect peut utiliser une redirection du navigateur vers le fournisseur d'identité.

**Étape 3 — L'utilisateur s'authentifie auprès de Keycloak**

Keycloak vérifie l'identité de l'utilisateur selon sa configuration.

L'utilisateur doit pouvoir atteindre l'interface d'authentification de Keycloak.

Cela justifie que Keycloak soit accessible par l'intermédiaire de Traefik.

**Étape 4 — L'authentification est communiquée à Moodle**

Après une authentification réussie, le protocole utilisé permet à Moodle de recevoir ou de vérifier les informations nécessaires pour établir une session.

Selon le protocole et sa configuration, des échanges directs entre Moodle et Keycloak peuvent également être nécessaires.

Nous devrons donc examiner les besoins de communication exacts avant de finaliser leur configuration réseau.

**Point important :** il ne faut pas confondre le SSO et l'attribution des droits d'accès à une formation.

Keycloak permet de gérer l'authentification, tandis que Moodle doit également déterminer si l'utilisateur est autorisé à accéder au cours concerné.

Un utilisateur peut donc être correctement authentifié sans être automatiquement inscrit à toutes les formations.

Cette distinction est importante pour notre scénario pédagogique.

**État actuel : intégration SSO exigée, mais non encore configurée ni testée.**

### **3.12.4. Communications avec PostgreSQL**

Notre instance PostgreSQL est destinée à héberger les bases de données des services compatibles avec ce système.

Nous avons choisi de placer PostgreSQL sur data-net, sans publier directement son port sur la VM.

Lorsqu'une application utilise PostgreSQL, elle doit pouvoir le joindre par le réseau de données.

Par exemple, si Keycloak utilise notre instance PostgreSQL, son conteneur devra être connecté à data-net.

La même logique pourra s'appliquer à Moodle et à n8n si nous retenons PostgreSQL pour ces services.

Nous devrons toutefois créer des bases et des comptes adaptés à chaque application.

Une application ne devrait pas utiliser automatiquement le compte administrateur général postgres.

Nous devons également éviter de connecter à data-net des conteneurs qui n'ont pas besoin de communiquer avec PostgreSQL.

**État actuel : PostgreSQL déployé et connecté à data-net. Connexions des applications à leurs bases respectives non encore réalisées.**

### **3.12.5. Communications entre Traefik et le socket-proxy**

Traefik doit obtenir certaines informations du moteur Docker pour découvrir les services et appliquer les règles de routage.

Nous avons choisi d'utiliser un socket-proxy plutôt que de donner directement à Traefik accès au socket Docker.

Les deux conteneurs partagent donc un réseau technique, socket-net.

Ce réseau n'est pas destiné aux utilisateurs ni aux communications avec les bases de données.

Il doit servir uniquement aux communications nécessaires entre les composants autorisés.

Cette séparation réduit les possibilités d'accès inutiles à l'interface Docker, mais sa sécurité dépend également des permissions configurées dans le socket-proxy.

**État actuel : Traefik et socket-proxy déployés. Leur fonctionnement a été testé pendant la mise en place de l'infrastructure.**

### **3.12.6. Communications que nous voulons volontairement éviter**

Notre segmentation doit également limiter les communications qui ne sont pas nécessaires au fonctionnement de la plateforme.

**Accès direct des utilisateurs à PostgreSQL**

Les utilisateurs doivent accéder aux applications web, et non directement au serveur de bases de données.

Nous avons donc évité de publier le port 5432 de PostgreSQL sur la VM.

**Accès de Traefik au réseau de données**

Traefik transmet des requêtes web aux applications.

Il n'a pas besoin de communiquer directement avec PostgreSQL pour accomplir cette fonction.

Nous ne prévoyons donc pas de connecter Traefik à data-net.

**Accès des applications au socket Docker**

Les applications comme Moodle ou Drupal Commerce n'ont normalement pas besoin d'administrer le moteur Docker.

Nous devons donc éviter de leur donner accès au socket Docker ou au réseau technique réservé au socket-proxy.

**Accès des applications aux bases des autres services**

Même lorsque plusieurs applications partagent PostgreSQL, elles ne doivent pas automatiquement disposer des permissions sur toutes les bases.

La segmentation réseau doit être complétée par des comptes et des autorisations adaptés.

**Exposition directe des services internes**

Les services de données et les interfaces techniques ne doivent pas être rendus accessibles depuis l'extérieur sans nécessité.

Les accès web nécessaires doivent être centralisés et contrôlés, notamment par Traefik.

### **3.12.7. Ce que nous devrons vérifier lors du déploiement**

Les communications décrites dans cette section constituent notre architecture prévue.

Nous devrons vérifier leur fonctionnement à mesure que nous déploierons les applications.

Nous devrons notamment confirmer que Traefik peut joindre les applications web autorisées, que Drupal Commerce peut transmettre un événement à n8n et que n8n peut communiquer avec Moodle.

Nous devrons également vérifier le fonctionnement du SSO avec Keycloak, les connexions des applications à PostgreSQL et l'absence d'exposition directe des services de données.

Enfin, nous devrons réaliser des tests de communications non autorisées, afin de confirmer que les conteneurs qui ne partagent pas de réseau approprié ne peuvent pas atteindre directement les services concernés.

Ces vérifications devront être accompagnées de commandes, de résultats observés et d'explications.

Il faudra distinguer un échec causé par l'isolation réseau d'un échec causé par l'authentification ou par un service indisponible.

### **3.12.8. Conclusion**

La répartition de nos conteneurs doit permettre le fonctionnement du scénario pédagogique tout en limitant les communications inutiles.

Nous avons identifié les principaux flux nécessaires entre Traefik, Drupal Commerce, n8n, Moodle, Keycloak et PostgreSQL.

Nous avons également défini plusieurs communications que nous souhaitons interdire ou éviter.

Cependant, ces règles ne sont pas toutes encore mises en œuvre.

Leur validation dépendra du déploiement des applications et des tests de connectivité et de sécurité.

Notre objectif est de démontrer une segmentation réellement fonctionnelle, et non simplement de présenter plusieurs réseaux dans un fichier Compose.

## **3.13. État actuel de la répartition réseau**

À ce stade, nous distinguons deux catégories.

**Connexions déjà mises en place**

Nous avons déployé Traefik, le socket-proxy et PostgreSQL.

Nous avons notamment confirmé la présence du réseau de données et son utilisation par PostgreSQL.

Les tests déjà réalisés permettent de documenter une partie du fonctionnement de notre architecture.

**Connexions encore à mettre en place**

Les connexions de Drupal Commerce, Moodle, Keycloak, n8n et Portainer restent à déterminer définitivement et à configurer.

Nous devons aussi confirmer la création du réseau app-net et vérifier les communications autorisées et interdites.

Il serait donc prématuré de présenter l'ensemble de la segmentation comme terminé.

## **3.14. Conclusion de la section**

La segmentation réseau de notre plateforme ne repose pas uniquement sur la création de plusieurs réseaux Docker.

Elle dépend également de la manière dont les conteneurs sont répartis entre ces réseaux.

Nous avons choisi de limiter les connexions aux besoins réels des services, en distinguant l'exposition web, les communications applicatives, les accès aux données et les communications techniques avec Docker.

Les services d'infrastructure déjà déployés constituent une première étape.

La suite du travail consistera à déployer les applications, à préciser leurs besoins de communication et à vérifier que les accès prévus fonctionnent sans exposer inutilement les services sensibles.

Cette démarche doit nous permettre de démontrer une segmentation cohérente, conforme aux principes de sécurité demandés dans le travail de session.

# 4. Vérification de la segmentation réseau et preuves techniques

Documentation détaillée à destination des membres du groupe AC • État observé sur la VM Ubuntu • Version de travail à valider

Cette section explique notre démarche dans l’ordre où les vérifications ont été réalisées. Les commandes et les résultats proviennent des captures de la session SSH fournies. Une observation ne doit jamais être interprétée comme une preuve plus large que ce qu’elle démontre réellement.

## 4.1. Objectif de cette étape

Après avoir défini notre architecture réseau et déterminé les réseaux auxquels chaque conteneur doit appartenir, nous devons vérifier que la configuration réellement appliquée par Docker correspond à ce que nous avons prévu.

En effet, le simple fait de déclarer quatre réseaux dans notre fichier compose.yaml ne suffit pas à démontrer que notre plateforme est correctement segmentée.

Nous devons notamment vérifier que :

Les réseaux déclarés existent réellement dans Docker.

Les conteneurs sont connectés uniquement aux réseaux nécessaires à leur fonctionnement.

Les communications autorisées entre les services sont possibles.

Les communications que nous souhaitons interdire sont effectivement bloquées.

Les services sensibles, notamment PostgreSQL, ne sont pas directement exposés aux utilisateurs externes.

Ces vérifications sont importantes pour notre travail de session, car le professeur demande non seulement de mettre en place une segmentation logique, mais également de pouvoir expliquer et justifier les communications entre les services.

Notre objectif est donc de passer d’une architecture déclarée dans Docker Compose à une architecture vérifiée à l’aide de commandes et de tests techniques.

### 4.1.1. Pourquoi les vérifications sont-elles nécessaires ?

Prenons l’exemple de PostgreSQL. Dans compose.yaml, nous avons prévu que PostgreSQL soit connecté uniquement à data-net, afin de séparer le serveur de bases de données des services accessibles aux utilisateurs. Mais ce choix écrit dans un fichier ne garantit pas, à lui seul, que le conteneur fonctionne réellement de cette manière.

Il faut vérifier ses réseaux, ses ports publiés et sa disponibilité depuis des conteneurs clients placés sur différents réseaux. Chacune de ces commandes répond à une question distincte.

| **Question**                            | **Commande ou méthode**     | **Ce que cela établit**                      |
|-----------------------------------------|-----------------------------|----------------------------------------------|
| Les réseaux existent-ils ?              | docker network ls           | Inventaire des réseaux présents              |
| Comment un réseau est-il configuré ?    | docker network inspect      | Propriétés, sous-réseau et membres du réseau |
| Quels réseaux utilise un conteneur ?    | docker inspect              | Appartenance du conteneur à ses réseaux      |
| Un port est-il publié sur la VM ?       | docker port et docker ps    | Correspondances de ports Docker vers l’hôte  |
| Le service répond-il depuis ce réseau ? | Conteneur client temporaire | Résultat d’un test de communication précis   |

### 4.1.2. Configuration prévue, appliquée et testée

Une configuration prévue est une intention décrite dans notre architecture ou dans compose.yaml. Exemple : Moodle devra rejoindre certains réseaux lorsque son déploiement sera effectué. Cela ne veut pas dire que Moodle y est déjà connecté.

Une configuration appliquée est une situation observable dans Docker. Exemple : le réseau compose_data-net existe et PostgreSQL y est connecté.

Une configuration testée est un comportement réellement observé. Exemple : un conteneur temporaire connecté à compose_data-net a reçu une réponse de PostgreSQL sur le port 5432.

Ces trois niveaux ne doivent pas être confondus. Une configuration appliquée n’est pas nécessairement une communication réussie; une communication réussie ne prouve pas que toutes les autres sont interdites.

### 4.1.3. Méthode retenue pour la documentation

Pour chaque manipulation, nous conservons cinq éléments : (1) pourquoi la vérification est utile; (2) la commande exacte; (3) le résultat observé; (4) l’explication des champs ou messages; (5) un bilan qui indique ce qui est confirmé et ce qui reste à démontrer.

### 4.1.4. Environnement et périmètre de la séance

Les commandes ont été exécutées depuis une session SSH ouverte sur notre VM Ubuntu, où Docker Engine et Docker Compose sont installés. Les conteneurs réellement en fonctionnement au moment des vérifications sont Traefik, socket-proxy et PostgreSQL. Les autres applications prévues (Drupal Commerce, Moodle, Keycloak, n8n et autres services du projet) ne font pas partie de ces tests de connectivité.

Le projet Docker Compose est nommé compose dans les résultats. Cela explique le préfixe compose\_ des réseaux et compose- des conteneurs. Les réseaux déclarés dans le fichier sont frontend-net, socket-net, app-net et data-net. Les noms réels créés par Compose portent le préfixe du projet.

## 4.2. Vérification de l’existence et de la configuration des réseaux Docker

### 4.2.1. Que veut dire « vérifier l’existence d’un réseau » ?

Un réseau Docker est un environnement de communication virtuel auquel Docker peut connecter des conteneurs. Déclarer un réseau dans compose.yaml signifie demander à Compose de le prévoir. Vérifier son existence consiste à demander directement au moteur Docker quels réseaux sont effectivement présents à cet instant.

Cette première étape est nécessaire avant toute analyse de connexions : on ne peut pas étudier l’appartenance d’un conteneur à un réseau qui n’a pas encore été créé.

Après le démarrage de la VM et la connexion SSH, nous avons exécuté :

| **sudo docker network ls** |
|----------------------------|

| **Réseau observé**   | **Pilote** | **Interprétation**                    |
|----------------------|------------|---------------------------------------|
| bridge               | bridge     | Réseau Docker par défaut              |
| compose_data-net     | bridge     | Réseau de données du projet           |
| compose_frontend-net | bridge     | Réseau d’exposition du projet         |
| compose_socket-net   | bridge     | Réseau technique du projet            |
| host                 | host       | Mode réseau utilisant celui de l’hôte |
| none                 | null       | Mode sans réseau habituel             |

Nous retrouvons donc trois de nos quatre réseaux de projet : frontend-net, socket-net et data-net. En revanche, compose_app-net ne figure pas dans cette liste. Il est déclaré dans l’architecture, mais sa création n’est pas confirmée à ce stade. Cette absence est cohérente avec le fait que les applications devant l’utiliser ne sont pas encore déployées.

La colonne DRIVER indique ici bridge pour les trois réseaux du projet : Docker crée des réseaux virtuels locaux à la VM. La colonne SCOPE vaut local : ces réseaux sont gérés par ce moteur Docker, et non par un cluster multi-hôtes.

### **Bilan de cette vérification**

Trois réseaux de notre projet existent réellement. Le quatrième, app-net, demeure prévu mais non créé lors de cette séance. Cette commande ne nous apprend pas encore quels conteneurs appartiennent à chaque réseau, ni si les communications sont autorisées ou refusées.

### 4.2.2. Que signifie « inspecter un réseau » ?

La commande précédente répondait à « quels réseaux existent ? ». Inspecter un réseau consiste à ouvrir sa fiche technique complète dans Docker : nom, pilote, sous-réseau, passerelle, option Internal et liste des conteneurs connectés. Le mot inspect n’est donc pas une analyse de trafic ni un test de communication : c’est une lecture de la configuration et de l’état actuel du réseau.

Nous allons inspecter séparément data-net, socket-net et frontend-net. Pourquoi les trois ? Parce que chacun a un rôle différent et des règles attendues différentes. L’inspection individuelle permet de repérer une erreur qui serait cachée dans un inventaire général; la synthèse finale permettra ensuite de comparer l’ensemble.

### 4.2.3. Inspection du réseau de données : compose_data-net

Pourquoi commencer par celui-ci ? PostgreSQL est notre service de données sensible. Nous voulons savoir si le réseau de données est effectivement interne et si PostgreSQL en est membre.

| **sudo docker network inspect compose_data-net** |
|--------------------------------------------------|

| **Champ inspecté** | **Résultat observé** | **Signification**                           |
|--------------------|----------------------|---------------------------------------------|
| Name               | compose_data-net     | Nom réel du réseau                          |
| Driver             | bridge               | Réseau virtuel de type bridge               |
| Scope              | local                | Géré localement par Docker                  |
| Internal           | true                 | Réseau créé avec la propriété interne       |
| Subnet             | 172.20.0.0/16        | Plage d’adresses de ce réseau               |
| Gateway            | 172.20.0.1           | Adresse de passerelle de ce réseau          |
| Containers         | compose-postgres-1   | Conteneur membre observé                    |
| IPv4Address        | 172.20.0.2/16        | Adresse attribuée à PostgreSQL sur data-net |

Le champ Internal: true confirme que l’option internal: true déclarée dans Compose a bien été appliquée à ce réseau. Attention : « interne » ne signifie pas que chaque conteneur du réseau est isolé de tous les autres. Les conteneurs qui partagent data-net peuvent communiquer si leurs services et leurs règles le permettent. Cela ne constitue pas non plus, à lui seul, un pare-feu individuel.

La rubrique Containers confirme que PostgreSQL est membre de ce réseau et possède l’adresse 172.20.0.2. L’adresse 172.20.0.1 est celle de la passerelle Docker; le /16 désigne la taille du sous-réseau. Ces valeurs décrivent ce réseau au moment de l’inspection : elles ne sont pas à considérer comme des adresses garanties après toute recréation.

Les étiquettes com.docker.compose.project et com.docker.compose.network relient ce réseau au projet Compose et au nom data-net du YAML. Elles expliquent pourquoi nous utilisons data-net dans le fichier, mais compose_data-net dans les commandes Docker.

L’inspection montre que PostgreSQL est connecté à data-net. Mais elle ne suffit pas à prouver qu’il n’est connecté à aucun autre réseau. Nous ferons donc une vérification centrée sur le conteneur à la section 4.3.

### **Bilan de cette vérification**

compose_data-net existe, utilise bridge, possède Internal: true et contient compose-postgres-1 à l’adresse 172.20.0.2/16. L’appartenance de PostgreSQL est observée; son appartenance exclusive sera confirmée avec docker inspect.

### 4.2.4. Inspection du réseau technique : compose_socket-net

Le réseau technique a un rôle différent : permettre à Traefik de joindre socket-proxy pour obtenir certaines informations sur les conteneurs Docker, sans monter directement le socket Docker dans Traefik. Nous devons donc vérifier que les deux conteneurs partagent ce réseau, et que celui-ci est interne.

| **sudo docker network inspect compose_socket-net** |
|----------------------------------------------------|

| **Champ inspecté**  | **Résultat observé**   | **Signification**                    |
|---------------------|------------------------|--------------------------------------|
| Internal            | true                   | Réseau déclaré interne               |
| Subnet              | 172.19.0.0/16          | Plage IPv4 du réseau technique       |
| Gateway             | 172.19.0.1             | Passerelle du réseau                 |
| Containers          | compose-traefik-1      | Traefik est connecté au réseau       |
| IPv4Address Traefik | 172.19.0.3/16          | Adresse de Traefik sur socket-net    |
| Containers          | compose-socket-proxy-1 | Le proxy est connecté au même réseau |
| IPv4Address proxy   | 172.19.0.2/16          | Adresse du proxy sur socket-net      |

Le résultat montre que Traefik et socket-proxy partagent le réseau compose_socket-net. Cette proximité réseau rend possible un chemin de communication entre les deux, mais ne démontre pas encore que l’API du proxy répond ni que ses permissions sont correctement filtrées. Ces points seront testés plus loin.

Pourquoi ce réseau est-il distinct de frontend-net ? frontend-net sert aux communications web, tandis que socket-net sert à la communication technique avec l’API Docker. Cette séparation évite de placer directement le proxy sur le réseau d’exposition. Cependant, tout conteneur auquel on donnerait accès à socket-net pourrait tenter de joindre le proxy : la configuration de ses permissions reste donc indispensable.

### **Bilan de cette vérification**

compose_socket-net existe, possède Internal: true et relie Traefik (172.19.0.3) à socket-proxy (172.19.0.2). Nous avons établi l’existence d’un chemin réseau possible, pas encore la sécurité des opérations permises par le proxy.

### 4.2.5. Inspection du réseau d’exposition : compose_frontend-net

Le réseau d’exposition doit accueillir Traefik et, plus tard, les applications web concernées. Son rôle est différent : Traefik doit pouvoir recevoir les requêtes web via les ports publiés de la VM. Nous cherchons donc à vérifier sa configuration et les conteneurs actuellement présents.

| **sudo docker network inspect compose_frontend-net** |
|------------------------------------------------------|

| **Champ inspecté** | **Résultat observé** | **Signification**                            |
|--------------------|----------------------|----------------------------------------------|
| Internal           | false                | Réseau non déclaré interne                   |
| Subnet             | 172.18.0.0/16        | Plage d’adresses de frontend-net             |
| Gateway            | 172.18.0.1           | Passerelle Docker                            |
| Containers         | compose-traefik-1    | Seul conteneur observé dans cette inspection |
| IPv4Address        | 172.18.0.2/16        | Adresse de Traefik sur frontend-net          |

Le champ Internal: false est cohérent avec notre choix de ne pas rendre le réseau d’exposition interne. Toutefois, il ne veut pas dire que tous les conteneurs de ce réseau sont accessibles directement depuis Internet. L’accessibilité depuis l’extérieur dépend notamment des ports publiés sur la VM, de son réseau et des règles de filtrage. Nous vérifierons les ports en 4.4.

Pourquoi Traefik apparaît-il ici et aussi dans socket-net ? Un conteneur peut être connecté à plusieurs réseaux Docker. Traefik possède donc une interface sur frontend-net (172.18.0.2) et une autre sur socket-net (172.19.0.3). Ce n’est pas une anomalie : il a besoin de deux types de communications. Cela ne signifie pas qu’il transfère automatiquement tout le trafic entre les deux réseaux.

### **Bilan de cette vérification**

> compose_frontend-net existe, possède Internal: false et contient Traefik à 172.18.0.2. Aucun conteneur applicatif n’y est encore observé. La publication effective des ports reste à vérifier.

### 4.2.6. Bilan comparatif des inspections réseau

| **Réseau**           | **Internal** | **Conteneurs observés** | **État**                    |
|----------------------|--------------|-------------------------|-----------------------------|
| compose_frontend-net | false        | Traefik                 | Créé et inspecté            |
| compose_socket-net   | true         | Traefik, socket-proxy   | Créé et inspecté            |
| compose_data-net     | true         | PostgreSQL              | Créé et inspecté            |
| compose_app-net      | Non vérifié  | Aucun                   | Absent de docker network ls |

Nous savons maintenant comment chaque réseau existant est configuré et quels membres Docker y déclarait au moment des inspections. Nous ne savons pas encore si chaque conteneur appartient exclusivement aux réseaux voulus : pour cela, nous allons inverser le point de vue et inspecter les conteneurs eux-mêmes.

## 4.3. Vérification des connexions réseau de chaque conteneur

### 4.3.1. Pourquoi inspecter les conteneurs après avoir inspecté les réseaux ?

Une inspection réseau répond à « qui est présent sur ce réseau ? ». Une inspection de conteneur répond à « à quels réseaux ce conteneur est-il connecté ? ». Les deux approches se complètent. Nous cherchons à éviter qu’un conteneur sensible possède une connexion supplémentaire inutile, qui créerait un chemin de communication non prévu.

La commande ci-dessous utilise docker inspect avec un format qui n’affiche que les noms des réseaux du conteneur. La syntaxe {{...}} est un modèle Go utilisé par Docker pour sélectionner les informations à afficher; elle ne modifie rien.

| **sudo docker inspect --format '{{range \$name, \$\_ := .NetworkSettings.Networks}}{{println \$name}}{{end}}' compose-postgres-1** |
|------------------------------------------------------------------------------------------------------------------------------------|

Résultat réellement obtenu :

| **compose_data-net** |
|----------------------|

PostgreSQL n’apparaît donc que sur data-net dans l’état observé. Il n’a pas d’interface déclarée sur frontend-net ou socket-net. Il ne possède pas non plus d’interface sur app-net, qui n’était pas créé.

Pourquoi est-ce utile ? Cela réduit les chemins directs de communication vers le serveur de données. Les futurs services qui auront besoin de PostgreSQL devront disposer d’une connexion réseau appropriée, plutôt que d’y accéder depuis le réseau d’exposition.

### **Bilan de cette vérification**

PostgreSQL est connecté uniquement à compose_data-net, conformément à la répartition prévue pour ce service. Cela confirme son appartenance réseau, mais pas encore le comportement de tous les chemins de communication.

### 4.3.2. Vérification des connexions de Traefik

Traefik est notre point d’entrée web et utilise également les informations Docker fournies par socket-proxy. Il doit donc appartenir à deux réseaux, mais pas à celui des données. Nous vérifions cette répartition directement.

| **sudo docker inspect --format '{{range \$name, \$\_ := .NetworkSettings.Networks}}{{println \$name}}{{end}}' compose-traefik-1** |
|-----------------------------------------------------------------------------------------------------------------------------------|

Résultat réellement obtenu :

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>compose_frontend-net<br />
compose_socket-net</strong></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

Le résultat est important : il confirme que Traefik possède exactement les deux connexions attendues dans l’état actuel. frontend-net sert à son rôle de point d’entrée web; socket-net sert à joindre le proxy Docker. Traefik n’est pas connecté à data-net et n’a pas besoin de parler directement à PostgreSQL.

Une même application peut être « multi-réseaux » sans que les réseaux soient fusionnés. La présence de Traefik sur deux réseaux ne signifie pas automatiquement qu’il joue le rôle de routeur IP entre eux. Son comportement de proxy HTTP doit être analysé séparément de ses interfaces réseau.

### **Bilan de cette vérification**

Traefik est connecté à compose_frontend-net et compose_socket-net, et à aucun autre réseau déclaré dans le résultat de docker inspect. Sa répartition est conforme à notre architecture pour les services déjà installés.

### 4.3.3. Vérification des connexions du socket-proxy

Le socket-proxy doit être accessible à Traefik sur le réseau technique, mais il n’a aucune raison d’appartenir au réseau d’exposition ou au réseau de données. Nous vérifions donc qu’aucune connexion supplémentaire n’a été ajoutée.

| **sudo docker inspect --format '{{range \$name, \$\_ := .NetworkSettings.Networks}}{{println \$name}}{{end}}' compose-socket-proxy-1** |
|----------------------------------------------------------------------------------------------------------------------------------------|

Résultat réellement obtenu :

| **compose_socket-net** |
|------------------------|

Le proxy appartient uniquement à socket-net. Cette vérification confirme la séparation des interfaces Docker, mais n’indique pas encore quelles requêtes API il accepte ou refuse. Un proxy joignable peut encore être mal configuré : les permissions seront contrôlées à la section 4.5.

### **Bilan de cette vérification**

socket-proxy est connecté uniquement à compose_socket-net. Les trois conteneurs actuellement déployés possèdent les connexions réseau attendues.

### 4.3.4. Bilan global de l’appartenance réseau

| **Conteneur**          | **Réseaux confirmés**                     | **Rôle de cette répartition**                   |
|------------------------|-------------------------------------------|-------------------------------------------------|
| compose-postgres-1     | compose_data-net                          | Service de données limité à son réseau          |
| compose-traefik-1      | compose_frontend-net + compose_socket-net | Entrée web et accès au proxy technique          |
| compose-socket-proxy-1 | compose_socket-net                        | Intermédiaire technique sans interface frontend |

Ce bilan constitue une preuve de configuration, non une preuve complète d’isolement. L’étape suivante consiste à examiner les ports que Docker rend accessibles depuis la VM : un conteneur correctement placé sur son réseau pourrait malgré tout publier un port non souhaité.

## 4.4. Vérification des ports publiés sur la machine virtuelle

### 4.4.1. Pourquoi les ports sont-ils une autre vérification ?

Un réseau Docker indique avec quels autres conteneurs un service peut partager un chemin de communication. Un port publié crée, lui, une correspondance entre un port de la VM hôte et un port du conteneur. Ce sont deux mécanismes différents. Nous voulons que Traefik reçoive les connexions web sur les ports 80 et 443, mais pas que PostgreSQL ou socket-proxy soient publiés directement sur l’hôte.

Il faut distinguer un port simplement indiqué comme port du conteneur (ex. 5432/tcp) et un port publié avec une correspondance du type 0.0.0.0:80-\>80/tcp. Le premier ne signifie pas à lui seul que le service est accessible depuis l’extérieur de la VM.

| **sudo docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'** |
|-------------------------------------------------------------------------|

| **Conteneur**          | **État observé** | **Ports affichés**                                                                      |
|------------------------|------------------|-----------------------------------------------------------------------------------------|
| compose-postgres-1     | Up 34 minutes    | 5432/tcp                                                                                |
| compose-traefik-1      | Up 34 minutes    | 0.0.0.0:80-\>80/tcp ; \[::\]:80-\>80/tcp ; 0.0.0.0:443-\>443/tcp ; \[::\]:443-\>443/tcp |
| compose-socket-proxy-1 | Up 34 minutes    | 2375/tcp                                                                                |

La colonne STATUS confirme que les trois conteneurs étaient démarrés au moment de la commande. Elle ne garantit pas, à elle seule, que toutes leurs fonctions applicatives étaient opérationnelles.

### 4.4.2. Lecture du résultat pour PostgreSQL

PostgreSQL affiche 5432/tcp : c’est le port habituel de son service à l’intérieur de l’environnement Docker. Aucune correspondance avec un port de la VM n’est affichée. Pour vérifier directement l’absence de publication, nous avons exécuté :

| **sudo docker port compose-postgres-1** |
|-----------------------------------------|

Résultat observé : aucune ligne affichée, puis retour de l’invite de commande. La commande docker port ne trouve donc aucune correspondance de port publiée par Docker pour ce conteneur.

Cela ne veut pas dire que PostgreSQL est inaccessible à tous les autres conteneurs. Nous démontrerons d’ailleurs qu’un client placé sur data-net peut le joindre. Nous avons seulement établi l’absence de publication Docker sur la VM.

### **Bilan de cette vérification**

Le port 5432 existe comme port de service dans le conteneur PostgreSQL, mais il n’est pas publié par Docker sur la VM.

### 4.4.3. Lecture du résultat pour Traefik

Traefik présente des correspondances pour les ports 80 et 443 sur les interfaces IPv4 (0.0.0.0) et IPv6 (\[::\]) de la VM. Le port 80 est généralement utilisé pour HTTP; le port 443 pour HTTPS. C’est cohérent avec le rôle de point d’entrée web confié à Traefik.

L’expression 0.0.0.0 signifie que la publication écoute sur les interfaces IPv4 de l’hôte selon la configuration Docker; elle ne prouve pas que la VM soit joignable depuis Internet. Le pare-feu, le réseau de la VM, les redirections de ports et les règles de l’environnement hôte peuvent encore limiter l’accès.

### **Bilan de cette vérification**

Docker publie les ports 80 et 443 de Traefik. L’accessibilité depuis un client externe n’a pas été testée dans cette série de commandes.

### 4.4.4. Lecture du résultat pour socket-proxy

Le socket-proxy affiche 2375/tcp, son port de service interne. Comme pour PostgreSQL, aucune correspondance hôte-\>conteneur n’est affichée. Nous avons confirmé ce point avec :

| **sudo docker port compose-socket-proxy-1** |
|---------------------------------------------|

Résultat observé : aucune ligne affichée. Le port 2375 n’est donc pas publié par Docker sur la VM. Cela évite une exposition directe par publication de port, mais ne remplace pas le contrôle des accès des conteneurs déjà connectés à socket-net.

### **Bilan de cette vérification**

Seul Traefik publie des ports sur la VM parmi les trois conteneurs vérifiés. PostgreSQL et socket-proxy ont des ports internes sans correspondance de publication Docker.

### 4.4.5. Bilan comparatif des ports

| **Conteneur** | **Port du service** | **Publication Docker sur la VM** | **Interprétation**                                      |
|---------------|---------------------|----------------------------------|---------------------------------------------------------|
| Traefik       | 80, 443             | Oui                              | Point d’entrée web déclaré                              |
| PostgreSQL    | 5432                | Non                              | Accès possible uniquement par chemins réseau appropriés |
| Socket-proxy  | 2375                | Non                              | API non publiée sur l’hôte par Docker                   |

Nous avons vérifié les propriétés statiques de l’environnement. Nous allons maintenant réaliser des tests actifs, c’est-à-dire envoyer des requêtes depuis des conteneurs clients afin de voir si les services répondent dans les conditions choisies.

## 4.5. Tests de communication et vérification du filtrage

### 4.5.1. Pourquoi tester les communications après les inspections ?

Inspecter un réseau ou un conteneur montre sa configuration déclarée et son appartenance actuelle. Un test de communication vérifie un comportement : un client peut-il joindre un service à une adresse et à un port donnés ? Pour ne pas confondre un service en panne avec un réseau inaccessible, nous vérifions d’abord que le service fonctionne localement.

Les clients de test sont des conteneurs temporaires lancés avec docker run --rm. Ils sont placés sur le réseau voulu sans modifier les conteneurs permanents. L’option --rm supprime le conteneur temporaire après l’exécution; elle ne supprime pas nécessairement l’image téléchargée.

### 4.5.2. Premier contrôle : PostgreSQL fonctionne-t-il localement ?

| **sudo docker exec compose-postgres-1 pg_isready** |
|----------------------------------------------------|

**Résultat observé :**

| **/var/run/postgresql:5432 - accepting connections** |
|------------------------------------------------------|

docker exec exécute une commande dans le conteneur PostgreSQL déjà en cours d’exécution. pg_isready vérifie si le serveur PostgreSQL est prêt à accepter des tentatives de connexion. Le chemin /var/run/postgresql indique ici une vérification via un socket Unix local, à l’intérieur du conteneur.

Ce test confirme que PostgreSQL répond localement. Il ne teste ni le réseau Docker ni les identifiants de connexion à une base de données. Il sert de point de comparaison : si une tentative distante échoue ensuite, nous savons au moins que le service répondait localement au moment du contrôle.

### **Bilan de cette vérification**

PostgreSQL était disponible localement et prêt à accepter des tentatives de connexion. Nous pouvons passer aux essais depuis les réseaux Docker.

### 4.5.3. Test autorisé : un client placé sur data-net

Objectif : confirmer qu’un conteneur connecté au réseau de données peut atteindre PostgreSQL sur le port 5432. Ce chemin sera utile aux applications autorisées qui utiliseront ce serveur de bases de données.

| **sudo docker run --rm --network compose_data-net postgres:17 pg_isready -h compose-postgres-1 -p 5432** |
|----------------------------------------------------------------------------------------------------------|

Décomposition de la commande : docker run crée un client temporaire; --rm le supprime à la fin; --network compose_data-net le place sur le réseau de données; postgres:17 fournit l’outil pg_isready; -h compose-postgres-1 désigne le serveur; -p 5432 désigne le port.

**Résultat observé :**

| **compose-postgres-1:5432 - accepting connections** |
|-----------------------------------------------------|

Nous avons donc confirmé que le nom du conteneur a pu être utilisé pour atteindre PostgreSQL depuis data-net, et que PostgreSQL a répondu à la vérification de disponibilité. pg_isready ne vérifie pas qu’un utilisateur possède les bons identifiants ou qu’il peut consulter une base de données.

L’option internal: true n’interdit pas la communication entre deux conteneurs qui partagent un réseau interne. Le succès de ce test est donc cohérent avec notre architecture.

### **Bilan de cette vérification**

Communication réussie depuis un client temporaire sur compose_data-net vers PostgreSQL:5432. Ce test confirme un chemin autorisé, sans tester l’authentification à une base de données.

### 4.5.4. Test depuis le réseau d’exposition frontend-net

Objectif : voir si un conteneur placé uniquement sur le réseau d’exposition peut atteindre directement PostgreSQL. Comme PostgreSQL n’est connecté qu’à data-net, ce chemin direct n’est pas prévu.

| **sudo docker run --rm --network compose_frontend-net postgres:17 pg_isready -h 172.20.0.2 -p 5432 -t 3** |
|-----------------------------------------------------------------------------------------------------------|

Pourquoi utiliser 172.20.0.2 ? Cette adresse a été relevée dans l’inspection de data-net. Elle permet de cibler PostgreSQL sans confondre un éventuel problème de résolution DNS avec un problème de communication. -t 3 fixe le délai d’attente de pg_isready à trois secondes.

**Résultat observé :**

| **172.20.0.2:5432 - no response** |
|-----------------------------------|

Dans ces conditions, le client placé sur frontend-net n’a obtenu aucune réponse de PostgreSQL. Cela est cohérent avec la séparation voulue. Mais il faut éviter une conclusion trop large : « no response » ne dit pas à lui seul si la cause précise est l’absence de route, un filtrage réseau ou un autre facteur. Nous n’avons pas analysé de paquets pour identifier le mécanisme exact.

### **Bilan de cette vérification**

La tentative depuis compose_frontend-net vers PostgreSQL:5432 n’a pas abouti dans les conditions du test. Cela soutient notre objectif de séparation, sans démontrer tous les mécanismes d’isolation possibles.

### 4.5.5. Test depuis le réseau technique socket-net

Objectif : vérifier que le réseau utilisé par Traefik et socket-proxy n’offre pas, dans les conditions testées, un accès direct à PostgreSQL. Ce réseau sert à la consultation contrôlée de l’API Docker, et non aux échanges avec la base de données.

| **sudo docker run --rm --network compose_socket-net postgres:17 pg_isready -h 172.20.0.2 -p 5432 -t 3** |
|---------------------------------------------------------------------------------------------------------|

**Résultat observé :**

| **172.20.0.2:5432 - no response** |
|-----------------------------------|

Le client temporaire connecté à socket-net n’a pas obtenu de réponse du service PostgreSQL. Là encore, le résultat est cohérent avec l’architecture, mais ne révèle pas à lui seul le mécanisme exact de l’échec.

### 4.5.6. Bilan comparatif des trois tests PostgreSQL

| **Réseau du client** | **Cible**               | **Résultat**          | **Ce que nous pouvons affirmer**      |
|----------------------|-------------------------|-----------------------|---------------------------------------|
| compose_data-net     | compose-postgres-1:5432 | accepting connections | PostgreSQL joignable depuis ce réseau |
| compose_frontend-net | 172.20.0.2:5432         | no response           | Aucune réponse pendant le test        |
| compose_socket-net   | 172.20.0.2:5432         | no response           | Aucune réponse pendant le test        |

Les tests illustrent le comportement attendu : le réseau de données permet l’accès au service, tandis que les deux autres clients temporaires n’obtiennent pas de réponse. Il ne s’agit pas d’une preuve exhaustive de l’absence de tous les chemins possibles, notamment lorsque de nouveaux conteneurs, des connexions multi-réseaux ou des règles d’hôte seront ajoutés.

### 4.5.7. Deuxième famille de tests : Traefik et socket-proxy

Nous avons ensuite vérifié un autre type de communication autorisée : celle qui permet à Traefik de consulter l’API Docker à travers socket-proxy. Il ne suffit pas que les deux conteneurs soient sur socket-net : il faut que le proxy réponde et qu’il applique des restrictions aux opérations sensibles.

Avant les requêtes, nous avons vérifié l’état de Traefik :

| **sudo docker inspect --format '{{.State.Status}} \| {{if .State.Health}}{{.State.Health.Status}}{{else}}aucun healthcheck configuré{{end}}' compose-traefik-1** |
|------------------------------------------------------------------------------------------------------------------------------------------------------------------|

Résultat :

| **running \| aucun healthcheck configuré** |
|--------------------------------------------|

running signifie que le conteneur est démarré. L’absence de healthcheck signifie que Docker ne dispose pas d’un contrôle de santé applicatif configuré pour Traefik. Nous ne pouvons pas en déduire que Traefik traite correctement toutes les requêtes web ou qu’il échange effectivement avec socket-proxy.

### **Bilan de cette vérification**

Traefik est démarré, mais aucun healthcheck Docker n’est configuré. Nous devons tester l’accès au socket-proxy séparément.

### 4.5.8. Vérification de la disponibilité de l’API via socket-proxy

Nous avons lancé un conteneur curl temporaire sur socket-net, puis envoyé une requête GET à l’endpoint /\_ping du proxy. Cet endpoint permet de vérifier que l’API répond à une requête simple.

| **sudo docker run --rm --network compose_socket-net curlimages/curl:8.12.1 -sS -i --max-time 5 http://compose-socket-proxy-1:2375/\_ping** |
|--------------------------------------------------------------------------------------------------------------------------------------------|

Explication : curlimages/curl:8.12.1 fournit l’outil HTTP curl; -sS réduit l’affichage inutile mais montre les erreurs; -i affiche les en-têtes de réponse; --max-time 5 limite la durée de la requête; le nom compose-socket-proxy-1 et le port 2375 désignent le proxy sur le réseau technique.

Au premier lancement, Docker a téléchargé l’image curlimages/curl:8.12.1, absente localement. Ce téléchargement est normal et n’est pas une erreur du test.

Résultat essentiel :

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>HTTP/1.1 200 OK<br />
api-version: 1.56<br />
server: Docker/29.8.1 (linux)<br />
<br />
OK</strong></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

HTTP 200 signifie que la requête a été acceptée et traitée. Le corps OK est la réponse attendue de /\_ping. Les autres en-têtes renseignent sur la réponse du serveur. Nous avons ainsi vérifié qu’un client du réseau socket-net peut joindre le proxy et obtenir une réponse de l’API Docker pour cette opération.

Attention : la requête provenait de notre conteneur de test, et non du processus Traefik. Nous avons prouvé la disponibilité du chemin depuis socket-net, pas encore un échange réel émis par Traefik.

### **Bilan de cette vérification**

La requête GET /\_ping a reçu HTTP 200 OK. Le socket-proxy est joignable depuis un client temporaire sur socket-net et autorise cette opération de lecture.

### 4.5.9. Vérification de la consultation des conteneurs

Traefik doit découvrir certains conteneurs pour configurer ses routes. Nous avons donc testé un endpoint de consultation qui retourne la liste des conteneurs Docker. Ce test permet aussi de voir si le proxy autorise des lectures plus riches qu’un simple ping.

| **sudo docker run --rm --network compose_socket-net curlimages/curl:8.12.1 -sS -i --max-time 5 http://compose-socket-proxy-1:2375/containers/json** |
|-----------------------------------------------------------------------------------------------------------------------------------------------------|

Résultat observé : HTTP/1.1 200 OK, suivi d’une très longue réponse JSON contenant des informations sur les conteneurs. Le texte volumineux est normal : cet endpoint renvoie une liste structurée avec notamment des noms, images, états, réseaux et montages.

Dans cette réponse, nous retrouvons les conteneurs PostgreSQL, Traefik et socket-proxy. Le fait d’obtenir 200 OK prouve que la lecture de la liste est autorisée. Cela ne signifie pas que le proxy autorise aussi la création, la suppression ou la modification de conteneurs.

Cette réponse démontre également une limite importante : tout conteneur ayant accès à ce proxy sur socket-net pourrait tenter les lectures autorisées. Il faut donc limiter l’appartenance au réseau technique et les permissions du proxy.

### **Bilan de cette vérification**

GET /containers/json a reçu HTTP 200 OK et a renvoyé des informations Docker. La consultation des conteneurs fonctionne; les permissions d’écriture restent à vérifier.

### 4.5.10. Inspection des permissions déclarées du socket-proxy

Avant de tester une opération refusée, nous avons lu les variables d’environnement du conteneur. Pourquoi ? Ces variables configurent les familles de requêtes que le proxy accepte ou bloque. Leur lecture nous permet de comprendre le comportement attendu avant de le confronter à un test réel.

| **sudo docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' compose-socket-proxy-1** |
|-----------------------------------------------------------------------------------------------------|

| **Paramètre**                         | **Valeur observée** | **Interprétation**                                                     |
|---------------------------------------|---------------------|------------------------------------------------------------------------|
| PING                                  | 1                   | Autorise la requête de disponibilité                                   |
| VERSION                               | 1                   | Autorise la consultation de version                                    |
| CONTAINERS                            | 1                   | Autorise des opérations de lecture liées aux conteneurs selon le proxy |
| EVENTS                                | 1                   | Autorise l’accès aux événements selon le proxy                         |
| POST                                  | 0                   | Bloque les requêtes HTTP POST                                          |
| ALLOW_START                           | 0                   | N’autorise pas le démarrage via cette option                           |
| ALLOW_STOP                            | 0                   | N’autorise pas l’arrêt via cette option                                |
| AUTH, SECRETS, VOLUMES                | 0                   | Catégories désactivées                                                 |
| IMAGES, EXEC, BUILD, NETWORKS, SYSTEM | 0                   | Autres catégories sensibles désactivées                                |

Les variables CONTAINERS=1 et POST=0 ne se contredisent pas : le proxy peut permettre la lecture d’informations sur les conteneurs tout en bloquant les requêtes POST. C’est une application du principe du moindre privilège : fournir les informations nécessaires à Traefik sans lui donner automatiquement des capacités d’administration.

Ces variables montrent la configuration déclarée. Pour confirmer qu’une restriction est effectivement appliquée, il faut tester une requête que le proxy doit refuser.

### 4.5.11. Test de refus d’une requête POST

Nous avons choisi POST /\_ping. Cette requête ne demande pas de créer, supprimer ou modifier un conteneur; elle sert à tester le blocage global des requêtes POST configuré par POST=0.

| **sudo docker run --rm --network compose_socket-net curlimages/curl:8.12.1 -sS -i --max-time 5 -X POST http://compose-socket-proxy-1:2375/\_ping** |
|----------------------------------------------------------------------------------------------------------------------------------------------------|

Résultat réellement observé :

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>HTTP/1.0 403 Forbidden<br />
<br />
Request forbidden by administrative rules.</strong></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

HTTP 403 signifie que le proxy refuse cette requête selon ses règles administratives. Nous avons ainsi deux observations complémentaires : des lectures GET choisies sont acceptées, tandis que cette requête POST est refusée. Le refus n’est pas une panne de réseau : le proxy a bien répondu, mais il a appliqué une règle de contrôle d’accès.

Ce test ne prouve pas que chaque endpoint sensible de l’API est bloqué. Il confirme concrètement le refus de la requête POST testée, cohérent avec POST=0.

### **Bilan de cette vérification**

Le socket-proxy répond à GET /\_ping et GET /containers/json par HTTP 200, mais refuse POST /\_ping par HTTP 403. Nous disposons donc d’une preuve expérimentale de filtrage pour ces requêtes précises.

### 4.5.12. Traefik est-il réellement configuré pour utiliser le socket-proxy ?

Les requêtes précédentes provenaient d’un client curl temporaire. Pour vérifier le choix de configuration de Traefik lui-même, nous avons consulté ses arguments de démarrage. L’objectif est de déterminer si Traefik pointe vers le proxy ou vers le socket Docker directement.

| **sudo docker inspect --format '{{json .Config.Cmd}}' compose-traefik-1** |
|---------------------------------------------------------------------------|

Parmi les paramètres observés :

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>--providers.docker=true<br />
--providers.docker.exposedbydefault=false<br />
--providers.docker.endpoint=tcp://socket-proxy:2375</strong></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

--providers.docker=true active le fournisseur Docker de Traefik, qui permet la découverte des services. --providers.docker.exposedbydefault=false indique que les conteneurs ne sont pas automatiquement publiés par Traefik; les applications devront être explicitement configurées pour être exposées. Enfin, --providers.docker.endpoint=tcp://socket-proxy:2375 configure Traefik pour utiliser l’API Docker via le proxy réseau.

Cela confirme le chemin configuré Traefik → socket-proxy → API Docker. Nous n’avons pas encore capturé une requête réelle émise par Traefik, mais ses arguments montrent clairement la destination qu’il est censé utiliser.

### **Bilan de cette vérification**

La configuration de Traefik désigne explicitement tcp://socket-proxy:2375 et désactive l’exposition automatique des conteneurs. Ce sont des choix cohérents avec l’architecture de sécurité prévue.

### 4.5.13. Traefik possède-t-il un montage direct du socket Docker ?

Même si Traefik pointe vers socket-proxy, il faut vérifier qu’un autre accès plus direct n’a pas été ajouté par montage de fichier. Le socket Docker de l’hôte est généralement /var/run/docker.sock; son montage dans un conteneur donne accès à une interface d’administration sensible.

| **sudo docker inspect --format '{{range .Mounts}}{{println .Source "-\>" .Destination}}{{end}}' compose-traefik-1** |
|---------------------------------------------------------------------------------------------------------------------|

Résultat réellement obtenu :

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<thead>
<tr class="header">
<th><strong>/opt/ac-platform/certs -&gt; /certs<br />
/opt/ac-platform/traefik/dynamic -&gt; /etc/traefik/dynamic</strong></th>
</tr>
</thead>
<tbody>
</tbody>
</table>

Le premier montage met à disposition des fichiers de certificats dans /certs. Le second fournit la configuration dynamique de Traefik. Le socket /var/run/docker.sock n’apparaît pas dans la liste des montages de Traefik.

Dans une vérification précédente, les informations Docker montraient que socket-proxy possède, lui, un montage de /var/run/docker.sock. La différence est intentionnelle : Traefik communique avec le proxy, tandis que le proxy est le composant qui accède au socket Docker et applique ses règles de filtrage.

Cette vérification confirme l’absence de montage direct du socket Docker dans la configuration inspectée de Traefik. Elle ne constitue pas, à elle seule, une preuve absolue qu’aucun autre mécanisme d’accès administratif n’existe dans l’environnement.

### **Bilan de cette vérification**

Traefik ne possède pas de montage direct de /var/run/docker.sock dans les montages observés. L’accès prévu à l’API Docker passe par socket-proxy, dont les permissions ont été partiellement testées.

### 4.5.14. Bilan général des tests de communication

| **Vérification**                      | **Résultat observé**             | **Portée du résultat**                     |
|---------------------------------------|----------------------------------|--------------------------------------------|
| PostgreSQL local                      | accepting connections            | Service disponible localement              |
| Client sur data-net → PostgreSQL      | accepting connections            | Communication autorisée observée           |
| Client sur frontend-net → PostgreSQL  | no response                      | Aucune réponse dans les conditions testées |
| Client sur socket-net → PostgreSQL    | no response                      | Aucune réponse dans les conditions testées |
| GET /\_ping via socket-proxy          | 200 OK                           | Lecture de disponibilité autorisée         |
| GET /containers/json via socket-proxy | 200 OK                           | Consultation des conteneurs autorisée      |
| POST /\_ping via socket-proxy         | 403 Forbidden                    | Requête POST testée refusée                |
| Arguments de Traefik                  | endpoint=tcp://socket-proxy:2375 | Usage du proxy configuré                   |
| Montages de Traefik                   | Pas de docker.sock               | Absence de montage direct observée         |

Nos tests sont cohérents avec la segmentation recherchée. Ils constituent un ensemble de preuves ciblées, mais pas une validation exhaustive de tous les flux ou de toutes les permissions possibles.

## 4.6. Synthèse des preuves, interprétation et limites

### 4.6.1. Ce que nous avons réellement confirmé

Les réseaux frontend-net, socket-net et data-net existent dans Docker; app-net n’apparaît pas encore dans l’inventaire.

data-net et socket-net sont créés avec Internal: true; frontend-net avec Internal: false.

PostgreSQL n’est connecté qu’à data-net; socket-proxy qu’à socket-net; Traefik à frontend-net et socket-net.

Docker publie les ports 80 et 443 de Traefik, mais aucun port de PostgreSQL ni du socket-proxy sur la VM.

PostgreSQL répond depuis un client sur data-net, mais pas depuis les clients temporaires sur frontend-net et socket-net dans les conditions testées.

Le socket-proxy accepte les deux requêtes GET testées et refuse la requête POST testée.

Traefik est configuré pour utiliser socket-proxy et n’a pas de montage direct du socket Docker dans les montages inspectés.

### 4.6.2. Ce que ces résultats ne prouvent pas encore

Ils ne démontrent pas une isolation absolue entre toutes les zones ni l’absence de toute route alternative.

Ils ne vérifient pas le pare-feu Ubuntu, les règles de l’hôte Windows, la configuration de QEMU ni l’accessibilité depuis Internet.

Ils ne vérifient pas l’authentification applicative complète à PostgreSQL, ni ses droits d’accès aux bases.

Ils ne testent pas tous les endpoints de l’API Docker ni toutes les méthodes HTTP.

Ils ne prouvent pas qu’une requête réelle a été émise par le processus Traefik vers le proxy pendant le test.

Ils ne valident pas les communications de Moodle, Drupal Commerce, Keycloak, n8n ou des autres applications non déployées lors de la séance.

### 4.6.3. Pourquoi conserver ces limites dans la documentation ?

En sécurité, une bonne documentation ne consiste pas seulement à présenter des résultats positifs. Elle doit expliquer exactement ce que chaque commande a vérifié. Par exemple, « no response » n’identifie pas la cause précise d’un échec; « 5432/tcp » n’est pas une publication de port; « Internal: true » n’interdit pas les échanges entre conteneurs du même réseau; et « running » ne signifie pas « application entièrement saine ».

En distinguant ces points, nous donnons aux membres du groupe les moyens de comprendre les résultats, de les reproduire et de poursuivre les tests sans tirer de conclusions trompeuses.

### 4.6.4. Prochaines vérifications lorsque les applications seront déployées

Lorsque les autres services seront ajoutés, il faudra inspecter app-net, confirmer les interfaces réseau de chaque nouveau conteneur, vérifier les ports réellement publiés, puis tester les communications nécessaires au fonctionnement de Drupal Commerce, Moodle, Keycloak et n8n. Il faudra également examiner les communications non souhaitées et les règles de filtrage propres à l’hôte.

Les résultats devront être recueillis à ce moment-là. Nous ne pouvons pas les présenter aujourd’hui comme des tests déjà réalisés.

## 4.7. Conclusion de la section 4

Cette étape nous a permis de passer d’une architecture réseau prévue dans Docker Compose à une première vérification technique de l’environnement réellement déployé. Nous avons commencé par inventorier les réseaux Docker, puis inspecté leurs propriétés et leurs conteneurs membres. Nous avons ensuite vérifié, pour chaque conteneur, l’ensemble de ses connexions réseau et les ports publiés sur la VM.

Les tests actifs ont confirmé que PostgreSQL répond depuis data-net et qu’aucune réponse n’a été obtenue depuis les conteneurs temporaires placés sur frontend-net et socket-net. Nous avons également montré que socket-proxy accepte certaines lectures nécessaires à la découverte Docker et refuse la requête POST testée. Enfin, les arguments et montages de Traefik confirment qu’il est configuré pour passer par le proxy sans monter directement le socket Docker.

Ces résultats sont cohérents avec les principes de segmentation logique et de moindre privilège retenus dans les sections précédentes. Ils ne suffisent toutefois pas à démontrer une isolation complète de toute la plateforme, puisque plusieurs applications et vérifications complémentaires restent à réaliser.

Cette documentation constitue donc un état vérifié et reproductible de notre infrastructure au moment de la séance, ainsi qu’une base de travail pour la suite du déploiement.

# Annexe A — Récapitulatif des commandes utilisées

> sudo docker network ls
>
> sudo docker network inspect compose_data-net
>
> sudo docker network inspect compose_socket-net
>
> sudo docker network inspect compose_frontend-net
>
> sudo docker inspect --format '{{range \$name, \$\_ := .NetworkSettings.Networks}}{{println \$name}}{{end}}' compose-postgres-1
>
> sudo docker inspect --format '{{range \$name, \$\_ := .NetworkSettings.Networks}}{{println \$name}}{{end}}' compose-traefik-1
>
> sudo docker inspect --format '{{range \$name, \$\_ := .NetworkSettings.Networks}}{{println \$name}}{{end}}' compose-socket-proxy-1
>
> sudo docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
>
> sudo docker port compose-postgres-1
>
> sudo docker port compose-socket-proxy-1
>
> sudo docker exec compose-postgres-1 pg_isready
>
> sudo docker run --rm --network compose_data-net postgres:17 pg_isready -h compose-postgres-1 -p 5432
>
> sudo docker run --rm --network compose_frontend-net postgres:17 pg_isready -h 172.20.0.2 -p 5432 -t 3
>
> sudo docker run --rm --network compose_socket-net postgres:17 pg_isready -h 172.20.0.2 -p 5432 -t 3
>
> sudo docker inspect --format '{{.State.Status}} \| {{if .State.Health}}{{.State.Health.Status}}{{else}}aucun healthcheck configuré{{end}}' compose-traefik-1
>
> sudo docker run --rm --network compose_socket-net curlimages/curl:8.12.1 -sS -i --max-time 5 http://compose-socket-proxy-1:2375/\_ping
>
> sudo docker run --rm --network compose_socket-net curlimages/curl:8.12.1 -sS -i --max-time 5 http://compose-socket-proxy-1:2375/containers/json
>
> sudo docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' compose-socket-proxy-1
>
> sudo docker run --rm --network compose_socket-net curlimages/curl:8.12.1 -sS -i --max-time 5 -X POST http://compose-socket-proxy-1:2375/\_ping
>
> sudo docker inspect --format '{{json .Config.Cmd}}' compose-traefik-1
>
> sudo docker inspect --format '{{range .Mounts}}{{println .Source "-\>" .Destination}}{{end}}' compose-traefik-1

# Annexe B — Source et traçabilité

Source principale : « Segmentation réseau Capture.pdf », document de 64 pages transmis pour reconstituer la chronologie des manipulations, les commandes, les captures d’écran et les bilans. Les pages 1–24 couvrent les objectifs, réseaux et connexions; 24–30 les ports; 31–40 les tests PostgreSQL; 41–61 les tests socket-proxy et la configuration Traefik; 62–64 la préparation de la documentation.

Les valeurs et résultats présentés dans cette version correspondent aux observations de la séance. Les interprétations précisent leurs limites et ne constituent pas de nouveaux tests.
