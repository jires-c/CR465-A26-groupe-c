# CR465 — Groupe AC
## Reconstruction de la VM Ubuntu avec QEMU, cloud-init et Docker Compose

# Partie I — Présentation de la plateforme conteneurisée

## 1. Vue d'ensemble de la plateforme

Notre projet consiste à mettre en place une plateforme informatique composée de plusieurs services exécutés dans des conteneurs Docker.

Pour cela, nous avons créé une machine virtuelle Ubuntu Server 24.04 à l'aide de QEMU. Cette machine virtuelle sert d'environnement d'hébergement pour notre plateforme.

À l'intérieur d'Ubuntu, nous avons installé Docker et Docker Compose. Docker permet d'exécuter les conteneurs, tandis que Docker Compose permet de définir et de démarrer plusieurs services à partir d'un seul fichier de configuration, appelé `compose.yaml`.

L'objectif est d'obtenir une plateforme organisée, dans laquelle chaque service possède un rôle précis et où les communications sont séparées à l'aide de réseaux Docker.

Nous avons également utilisé cloud-init pour automatiser la préparation de la machine virtuelle. Ainsi, lorsqu'une nouvelle VM est créée à partir d'une image Ubuntu propre, cloud-init peut installer Docker, récupérer les fichiers de configuration, préparer les éléments nécessaires et démarrer les conteneurs.

Cette automatisation permet de reconstruire notre architecture minimale sans devoir installer et configurer chaque composant manuellement.

## 2. Les composants actuellement déployés

Notre plateforme minimale comprend trois conteneurs Docker :

### 2.1. Traefik — Le point d'entrée de la plateforme

Traefik est un reverse proxy, c'est-à-dire un service qui reçoit les requêtes des utilisateurs et les dirige vers les applications concernées.

Par exemple, lorsqu'un utilisateur voudra accéder à Moodle ou à Drupal, il ne sera pas nécessaire d'exposer directement chaque application sur un port différent de la machine virtuelle.

L'utilisateur pourra passer par Traefik, qui examinera la requête pour déterminer vers quelle application l'envoyer.

Pour prendre cette décision, Traefik utilise des règles de routage. Ces règles peuvent notamment se baser sur le nom de domaine demandé.

Par exemple, dans une future configuration :

- Une requête vers `moodle.exemple.local` pourrait être dirigée vers Moodle.
- Une requête vers `drupal.exemple.local` pourrait être dirigée vers Drupal.
- Une requête vers `auth.exemple.local` pourrait être dirigée vers Keycloak.

Ces noms sont uniquement des exemples pour expliquer le principe. Ils ne correspondent pas encore à des routes applicatives déployées dans notre plateforme.

Traefik joue également un rôle dans la gestion des connexions HTTPS. Il peut présenter un certificat TLS afin de chiffrer les communications entre le navigateur de l'utilisateur et le point d'entrée de la plateforme.

Dans notre environnement actuel, Traefik écoute sur les ports 80 (HTTP) et 443 (HTTPS) de la machine virtuelle.

Nous avons vérifié qu'il répond en HTTPS avec le certificat local prévu dans notre configuration.

### 2.2. Socket-proxy — Le contrôle de l'accès à Docker

Traefik doit connaître les conteneurs disponibles afin de pouvoir diriger les requêtes vers les bons services.

Pour cela, il utilise le fournisseur Docker (*Docker provider*), qui lui permet de consulter des informations sur les conteneurs et leurs configurations.

Cependant, l'accès direct au socket Docker représente un risque de sécurité important, car ce socket donne accès à l'API du moteur Docker.

Nous avons donc ajouté un conteneur appelé **docker-socket-proxy** entre Traefik et Docker.

Son rôle est de servir d'intermédiaire.

Le fonctionnement est le suivant :

1. Traefik demande des informations sur les conteneurs.
2. La demande est envoyée à socket-proxy.
3. Socket-proxy applique les restrictions configurées sur les appels à l'API Docker.
4. Les informations autorisées sont transmises à Traefik.

Dans notre configuration, Traefik communique avec socket-proxy à l'adresse :

`tcp://socket-proxy:2375`

Cette adresse fonctionne à l'intérieur du réseau Docker partagé entre les deux conteneurs.

Traefik n'a donc pas besoin de monter directement le socket Docker.

Cette séparation réduit son exposition aux privilèges de Docker, même si elle ne supprime pas tous les risques liés à l'API Docker.

Nous avons également configuré un contrôle de santé (*healthcheck*) pour socket-proxy.

Ce contrôle vérifie périodiquement que le service répond à une requête de vérification.

Grâce à la correction A5, Docker Compose attend que socket-proxy soit déclaré sain (`healthy`) avant de démarrer Traefik lors de la création des services.

### 2.3. PostgreSQL — Le service de base de données

PostgreSQL est notre troisième conteneur.

Il s'agit d'un système de gestion de bases de données relationnelles.

Son rôle est de conserver et de gérer les données des applications qui utiliseront cette base de données.

Dans notre architecture actuelle, PostgreSQL est déjà installé et démarré, mais les applications qui devront éventuellement communiquer avec lui ne sont pas encore déployées.

Nous avons prévu un volume Docker nommé `postgres-data` afin de conserver les données de PostgreSQL indépendamment du cycle de vie de son conteneur.

Ainsi, une recréation du conteneur ne supprime pas automatiquement les données présentes dans ce volume.

Le mot de passe PostgreSQL n'est pas écrit directement dans le fichier `compose.yaml`. Il est généré lors de la préparation de la VM et stocké dans un fichier local utilisé comme secret Docker Compose.

## 3. Les réseaux Docker : pourquoi les avons-nous séparés ?

Nous avons créé plusieurs réseaux Docker pour éviter de placer tous les services dans un seul réseau.

L'objectif est de contrôler quelles catégories de services peuvent communiquer directement entre elles.

Dans notre configuration, nous avons défini quatre réseaux : `frontend-net`, `socket-net`, `app-net` et `data-net`.

Les trois premiers réseaux internes cités ci-dessous ne sont pas tous utilisés de la même manière. Il est important de comprendre leur fonction.

### 3.1. frontend-net — Le réseau d'exposition

Le réseau `frontend-net` est destiné aux services qui doivent être accessibles par l'intermédiaire de Traefik.

Actuellement, seul Traefik est connecté à ce réseau.

Lorsque nous ajouterons des applications Web, celles qui devront recevoir des requêtes de Traefik pourront également y être connectées.

Par exemple, Moodle et Drupal pourraient partager ce réseau avec Traefik pour recevoir les requêtes qui leur sont destinées.

Attention : le nom `frontend-net` ne signifie pas que ce réseau Docker est directement accessible depuis Internet.

Dans notre configuration actuelle, l'accès extérieur passe par les ports publiés par Traefik sur la machine virtuelle.

### 3.2. socket-net — Le réseau réservé à Traefik et socket-proxy

Le réseau `socket-net` permet à Traefik de communiquer avec socket-proxy.

Deux conteneurs y sont actuellement connectés :

- Traefik ;
- socket-proxy.

Ce réseau est déclaré `internal: true` dans Docker Compose.

Il sert à isoler les échanges liés à l'accès à l'API Docker des communications destinées aux applications.

Lorsqu'un utilisateur accède à Traefik, sa requête n'est pas envoyée à socket-proxy.

Socket-proxy sert uniquement aux échanges entre Traefik et l'API Docker.

### 3.3. app-net — Le réseau applicatif

Le réseau `app-net` est prévu pour les communications internes entre les services applicatifs.

Par exemple, lorsque nous ajouterons les applications prévues dans notre projet, certaines pourront avoir besoin de communiquer entre elles sans que ces échanges passent par le réseau d'exposition.

Ce réseau est déclaré `internal: true`.

Il existe dans notre fichier `compose.yaml`, mais aucun conteneur de notre déploiement minimal actuel n'y est connecté. Il n'apparaissait donc pas dans la liste des réseaux Docker effectivement créés lors de notre vérification.

Il sera utilisé lorsque nous intégrerons les services qui en auront besoin.

### 3.4. data-net — Le réseau de données

Le réseau `data-net` est destiné aux communications avec les services de données.

Actuellement, seul PostgreSQL est connecté à ce réseau.

Lorsqu'une application devra accéder à PostgreSQL, elle devra être connectée au réseau approprié et disposer des autorisations nécessaires.

Ce réseau est également déclaré `internal: true`.

L'objectif est de ne pas exposer directement PostgreSQL aux utilisateurs qui accèdent à la plateforme depuis Windows.

Dans notre configuration actuelle, aucun port PostgreSQL n'est publié vers l'extérieur de la VM.

## 4. Comment les conteneurs sont-ils connectés ?

Voici l'organisation réellement observée lors de notre validation :

| Conteneur | Réseau Docker | Rôle |
|---|---|---|
| Traefik | `frontend-net` | Recevoir les requêtes HTTP/HTTPS |
| Traefik | `socket-net` | Consulter l'API Docker par l'intermédiaire de socket-proxy |
| Socket-proxy | `socket-net` | Contrôler les requêtes vers l'API Docker |
| PostgreSQL | `data-net` | Héberger le service de base de données |

Le réseau `app-net` est prévu dans la configuration, mais n'est pas encore utilisé par les trois conteneurs actuels.

Traefik possède donc deux connexions réseau : une pour son rôle de point d'entrée et une pour communiquer avec socket-proxy.

Socket-proxy n'a pas besoin d'être connecté au réseau d'exposition.

PostgreSQL est séparé des deux autres conteneurs et n'a pas de connexion directe avec Traefik dans notre architecture minimale.

## 5. Comment une requête circule-t-elle dans notre plateforme ?

Prenons l'exemple d'un utilisateur qui veut accéder à une future application Moodle.

Le fonctionnement prévu sera le suivant :

**Étape 1 — L'utilisateur envoie une requête**

L'utilisateur ouvre son navigateur et saisit l'adresse de Moodle.

La requête est envoyée vers le point d'entrée HTTPS de notre plateforme.

**Étape 2 — Traefik reçoit la requête**

Traefik reçoit la connexion sur son port HTTPS 443.

Il utilise son certificat TLS pour établir la connexion chiffrée avec le navigateur.

**Étape 3 — Traefik identifie l'application**

Traefik examine les règles de routage associées aux services disponibles.

Grâce au fournisseur Docker, il peut découvrir les conteneurs et les informations de routage autorisées.

Pour obtenir ces informations, il communique avec socket-proxy, qui contrôle les requêtes vers l'API Docker.

Il est important de préciser que Traefik ne contacte pas nécessairement socket-proxy à chaque requête d'un utilisateur. Il utilise les informations de configuration et de découverte dont il dispose.

**Étape 4 — Traefik transmet la requête**

Si une règle correspond à l'adresse demandée et que l'application est accessible sur un réseau partagé, Traefik transmet la requête au conteneur concerné.

**Étape 5 — L'application répond**

L'application traite la demande et renvoie sa réponse à Traefik, qui la transmet ensuite au navigateur de l'utilisateur.

Cette circulation décrit le fonctionnement prévu après l'ajout des applications.

Dans notre plateforme actuelle, aucune route applicative n'est encore configurée. C'est pourquoi nos tests sur les ports HTTP et HTTPS ont retourné `404 Not Found`.

## 6. Comment accédons-nous à la plateforme depuis Windows ?

Notre plateforme fonctionne dans une machine virtuelle Ubuntu exécutée par QEMU sur Windows.

QEMU assure notamment la correspondance entre certains ports Windows et les ports de la VM.

Lors de notre validation, nous avons utilisé les correspondances suivantes :

| Accès depuis Windows | Destination dans Ubuntu | Utilité |
|---|---|---|
| `127.0.0.1:2225` | Port 22 | Connexion SSH |
| `127.0.0.1:8082` | Port 80 | Accès HTTP à Traefik |
| `127.0.0.1:8445` | Port 443 | Accès HTTPS à Traefik |

Pour administrer Ubuntu, nous utilisons SSH avec une clé privée :

```powershell
ssh -i "$HOME\.ssh\tp_conteneurs_ed25519" -p 2225 acadmin@127.0.0.1
```

Le nom de la clé doit être adapté à chaque membre.

Pour tester HTTPS depuis Windows, nous avons utilisé :

```powershell
curl.exe -vk https://127.0.0.1:8445/
```

Cette commande a confirmé que Traefik répond sur le port HTTPS.

Nous avons également vérifié que le certificat réellement présenté par Traefik correspond au certificat généré lors du déploiement automatique.

Il s'agit toutefois d'un certificat local qui n'est pas reconnu automatiquement comme fiable par Windows.

## 7. Comment notre plateforme se reconstruit-elle automatiquement ?

La reproductibilité repose principalement sur cloud-init et Docker Compose.

Lors du premier démarrage d'une nouvelle VM Ubuntu créée à partir d'une image Cloud propre :

1. QEMU démarre Ubuntu avec un support NoCloud contenant les fichiers `user-data` et `meta-data`.
2. Cloud-init lit les instructions fournies.
3. Il prépare l'utilisateur `acadmin` et les accès SSH.
4. Il installe les outils nécessaires, dont Docker et Docker Compose.
5. Il récupère les fichiers de configuration depuis GitHub.
6. Il génère le mot de passe PostgreSQL et le certificat TLS local.
7. Il lance Docker Compose.
8. Docker Compose crée les réseaux nécessaires et démarre les conteneurs.

La correction A5 permet de vérifier l'état de socket-proxy avant le démarrage de Traefik.

Nous avons testé cette procédure sur une nouvelle VM Ubuntu indépendante des précédentes.

Les vérifications ont confirmé le démarrage automatique des trois conteneurs, la création des réseaux utilisés, la présence des fichiers TLS et le fonctionnement technique de HTTPS.

## 8. Ce qui est déjà opérationnel et ce qui reste à développer

**Notre architecture minimale actuellement opérationnelle comprend :**

- Une VM Ubuntu Server exécutée avec QEMU.
- Une installation automatisée par cloud-init.
- Docker et Docker Compose.
- Traefik.
- Docker socket-proxy.
- PostgreSQL.
- Les réseaux `frontend-net`, `socket-net` et `data-net`.
- Un certificat TLS local utilisé par Traefik.
- Un accès SSH depuis Windows.

**Les éléments prévus pour la suite du projet comprennent :**

- L'intégration de Drupal.
- L'intégration de Moodle.
- L'intégration de Keycloak.
- L'intégration de n8n.
- L'utilisation du réseau applicatif `app-net`.
- La configuration des routes Traefik vers les applications.
- La vérification des communications entre les applications et les services de données.

Ces éléments ne doivent pas être présentés comme déjà déployés ou validés.

## 9. Passage à la reconstruction de la plateforme

Nous avons présenté les principaux composants de notre plateforme, leurs rôles et les communications entre les conteneurs.

La deuxième partie explique maintenant comment reconstruire cette architecture sur une nouvelle machine virtuelle Ubuntu à partir des fichiers disponibles sur GitHub. Elle présente les étapes d'installation, le démarrage automatique des services et les vérifications permettant de confirmer que la plateforme fonctionne correctement.

# Partie II — Reconstruction et validation de la VM

### 1. Objectif

Ce document explique comment reconstruire automatiquement notre plateforme minimale sur une nouvelle machine virtuelle Ubuntu.

La configuration repose sur :

- **QEMU** pour exécuter la machine virtuelle.
- **Ubuntu Server 24.04** comme système d'exploitation.
- **cloud-init / NoCloud** pour automatiser l'installation.
- **Docker Compose** pour déployer les conteneurs.
- **Traefik** comme reverse proxy.
- **Docker socket-proxy** pour limiter l'accès de Traefik à l'API Docker.
- **PostgreSQL** comme service de base de données.

La procédure a été validée sur une nouvelle VM Ubuntu créée à partir d'une image Cloud propre.

### 2. Fichiers nécessaires

Les fichiers de configuration se trouvent dans le dépôt GitHub du groupe, sur la branche `rolande` :

https://github.com/jires-c/CR465-A26-groupe-c/tree/rolande

| Fichier | Utilité |
|---|---|
| `cloud-init/user-data-reproductible.yaml` | Automatise l'installation et le déploiement |
| `cloud-init/meta-data` | Identifie la nouvelle instance Ubuntu |
| `compose/compose.yaml` | Définit les conteneurs et les réseaux Docker |
| `traefik/dynamic/tls.yaml` | Configure le certificat TLS de Traefik |

Les disques virtuels, clés privées SSH et mots de passe ne doivent pas être publiés sur GitHub.

### 3. Préparer l'environnement

Sur Windows, installer QEMU et disposer d'une image Ubuntu Server Cloud compatible avec l'architecture x86-64.

L'image utilisée lors de notre validation se nommait :

`noble-server-cloudimg-amd64.img`

Créer un dossier de travail, par exemple :

`C:\TP_Conteneurs\qemu-lab`

Y placer l'image Ubuntu Cloud.

Les commandes suivantes supposent que QEMU est installé dans :

`C:\Program Files\qemu\`

### 4. Récupérer les fichiers du dépôt GitHub

Dans PowerShell Windows :

```powershell
git clone --branch rolande https://github.com/jires-c/CR465-A26-groupe-c.git
```

Le dépôt contient les fichiers cloud-init nécessaires à la préparation de la VM.

Chaque membre doit disposer de la clé privée SSH correspondant à l'une des clés publiques autorisées dans `user-data-reproductible.yaml`.

Les clés privées ne doivent jamais être ajoutées au dépôt.

### 5. Générer le support NoCloud

Le support NoCloud doit contenir deux fichiers :

- `user-data`, provenant de `cloud-init/user-data-reproductible.yaml`.
- `meta-data`, provenant de `cloud-init/meta-data`.

Dans notre environnement, nous avons utilisé l'utilitaire Linux `cloud-localds` pour générer le support.

Depuis un environnement Ubuntu disposant de `cloud-localds`, exécuter :

```bash
cloud-localds seed-reproductible.iso user-data-reproductible.yaml meta-data
```

Cette commande suppose que les fichiers se trouvent dans le répertoire courant.

Le fichier `seed-reproductible.iso` obtenu doit ensuite être placé dans le dossier Windows `qemu-lab`.

Il est également possible d'utiliser un autre environnement Linux compatible pour effectuer cette opération.

### 6. Créer le disque de la nouvelle VM

**Environnement : PowerShell Windows**

Depuis le dossier `qemu-lab`, créer un nouveau disque indépendant :

```powershell
& "C:\Program Files\qemu\qemu-img.exe" convert -f qcow2 -O qcow2 ".\noble-server-cloudimg-amd64.img" ".\ac-reproductible.img"
```

Agrandir ensuite sa capacité virtuelle à 20 Go :

```powershell
& "C:\Program Files\qemu\qemu-img.exe" resize ".\ac-reproductible.img" 20G
```

Cette opération doit être effectuée sur un nouveau disque afin de ne pas écraser une VM existante.

### 7. Démarrer la VM

**Environnement : CMD Windows**

Se placer dans le dossier `qemu-lab` :

```cmd
cd /d C:\TP_Conteneurs\qemu-lab
```

Démarrer QEMU :

```cmd
"C:\Program Files\qemu\qemu-system-x86_64.exe" -machine accel=tcg -m 2048 -net nic -net user,hostfwd=tcp:127.0.0.1:2225-:22,hostfwd=tcp:127.0.0.1:8082-:80,hostfwd=tcp:127.0.0.1:8445-:443 -drive file=ac-reproductible.img,format=qcow2 -drive file=seed-reproductible.iso,media=cdrom,readonly=on -nographic
```

**Correspondance des ports :**

| Port Windows | Port Ubuntu | Service |
|---|---|---|
| 2225 | 22 | SSH |
| 8082 | 80 | HTTP |
| 8445 | 443 | HTTPS |

Au premier démarrage, cloud-init exécute automatiquement les instructions du fichier `user-data`.

Le processus peut prendre plusieurs minutes.

### 8. Se connecter à Ubuntu

**Environnement : PowerShell Windows**

```powershell
ssh -i "$HOME\.ssh\tp_conteneurs_ed25519" -p 2225 acadmin@127.0.0.1
```

Adapter le chemin de la clé privée SSH à chaque membre du groupe.

La connexion par mot de passe SSH est désactivée dans notre configuration.

### 9. Vérifier cloud-init

**Environnement : terminal Ubuntu**

```bash
cloud-init status --long
```

Lors de notre validation, le résultat indiquait :

```text
status: done
extended_status: done
boot_status_code: enabled-by-generator
detail: DataSourceNoCloud [seed=/dev/sr0]
errors: []
recoverable_errors: {}
```

Ce résultat confirme que cloud-init a détecté le support NoCloud et terminé sans erreur signalée.

### 10. Vérifier les conteneurs Docker

```bash
sudo docker ps
```

Les trois conteneurs attendus sont :

- `compose-traefik-1`
- `compose-socket-proxy-1`
- `compose-postgres-1`

Le conteneur socket-proxy doit notamment afficher l'état `healthy`.

### 11. Vérifier les réseaux Docker

```bash
sudo docker network ls
```

Les trois réseaux personnalisés attendus sont :

- `compose_frontend-net`
- `compose_socket-net`
- `compose_data-net`

Vérifier les connexions des conteneurs :

```bash
sudo docker network inspect compose_frontend-net compose_socket-net compose_data-net --format '{{.Name}} : {{range .Containers}}{{.Name}} {{end}}'
```

Lors de notre validation :

- Traefik était connecté à `frontend-net` et `socket-net`.
- Socket-proxy était connecté à `socket-net`.
- PostgreSQL était connecté à `data-net`.

### 12. Vérifier Docker Compose

```bash
cd /opt/ac-platform/compose && sudo docker compose config --quiet
```

La commande doit se terminer sans erreur.

### 13. Vérifier HTTPS

**Environnement : PowerShell Windows**

Tester la connexion HTTPS :

```powershell
curl.exe -vk https://127.0.0.1:8445/
```

Lors de notre validation, Traefik a répondu :

`HTTP/1.1 404 Not Found`

Cela confirme que la connexion HTTPS aboutit. Le code 404 indique qu'aucune route applicative ne correspond à la requête.

Le certificat TLS utilisé dans notre laboratoire est local et n'est pas reconnu comme fiable par Windows.

Un test sans l'option `-k` peut donc retourner :

`SEC_E_UNTRUSTED_ROOT`

### 14. Vérifier HTTP et la correction A1

```powershell
curl.exe -v http://127.0.0.1:8082/
```

Lors de notre validation, Traefik a répondu `404 Not Found`, sans redirection vers un ancien port HTTPS codé en dur.

Cela confirme le comportement attendu pour cette requête après la correction A1.

### 15. Vérifier la correction A5

Dans Ubuntu :

```bash
sudo docker ps
```

Vérifier que `compose-socket-proxy-1` affiche `healthy`.

Le fichier Compose contient également une condition `service_healthy`, qui demande à Docker Compose d'attendre que socket-proxy soit sain avant de démarrer Traefik.

### 16. Précautions de sécurité

Cette configuration est destinée à un laboratoire pédagogique.

- Ne jamais publier les clés privées SSH.
- Ne pas publier les fichiers contenant les mots de passe générés.
- Le compte `acadmin` possède des privilèges sudo sans mot de passe ; ce choix doit être revu pour un environnement de production.
- Le certificat TLS local n'est pas approuvé automatiquement par Windows.
- Les ports QEMU sont liés à `127.0.0.1`, ce qui limite leur exposition au poste Windows local.
- Chaque nouvelle VM doit être créée à partir d'une image Ubuntu propre.

### 17. Résultat de notre validation

La reconstruction automatique de notre architecture minimale a réussi sur une nouvelle VM Ubuntu.

Les vérifications ont confirmé :

- Le démarrage d'Ubuntu.
- L'exécution de cloud-init.
- L'installation de Docker.
- Le démarrage de Traefik, socket-proxy et PostgreSQL.
- La création des réseaux Docker.
- La présence des corrections A1 et A5.
- La validation du fichier Docker Compose.
- Le fonctionnement technique de HTTPS avec le certificat TLS prévu.

Cette validation concerne l'architecture minimale. Les applications finales telles que Drupal, Moodle, Keycloak et n8n devront être intégrées et vérifiées séparément.

### 18. Conclusion

Notre plateforme est organisée autour d'une idée simple : **séparer les responsabilités des services et contrôler leurs communications**.

Traefik constitue le point d'entrée pour les futures applications Web. Socket-proxy lui permet d'obtenir les informations nécessaires auprès de Docker sans lui donner directement accès au socket Docker. PostgreSQL fournit un service de base de données isolé sur son réseau.

Les réseaux Docker servent à organiser les communications selon leur fonction : exposition, accès à l'API Docker, échanges applicatifs et données.

Enfin, cloud-init et Docker Compose permettent de reconstruire automatiquement cette architecture minimale sur une nouvelle VM Ubuntu.

Nous disposons ainsi d'une base fonctionnelle et reproductible sur laquelle les autres services du projet pourront être ajoutés progressivement.