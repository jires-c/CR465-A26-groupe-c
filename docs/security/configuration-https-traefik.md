# Configuration HTTPS de Traefik

## 1. Objectif

Dans le cadre du projet CR465 – Technologies et sécurité des conteneurs, nous avons configuré Traefik comme point d'entrée des services Web de notre plateforme.

L'objectif est de sécuriser les communications entre les utilisateurs et les applications qui seront déployées dans les conteneurs Docker.

Pour cela, nous avons mis en place :

- Un accès HTTPS utilisant TLS pour chiffrer les communications.
- Un certificat TLS autosigné pour notre environnement de laboratoire.
- Une redirection automatique des requêtes HTTP vers HTTPS.
- Un montage des fichiers de configuration et des certificats en lecture seule dans le conteneur Traefik.

Cette configuration constitue une première étape. Les applications Drupal, Moodle et Keycloak ne sont pas encore raccordées à Traefik.

## 2. Environnement utilisé

Notre laboratoire repose sur une machine virtuelle Ubuntu 24.04 LTS exécutée avec QEMU sur un ordinateur Windows.

Dans la VM, nous utilisons Docker Engine et Docker Compose pour gérer les conteneurs.

Les services actuellement configurés sont :

- **Traefik** : reçoit les requêtes HTTP et HTTPS et assurera leur acheminement vers les applications.
- **socket-proxy** : sert d'intermédiaire entre Traefik et l'API Docker afin de limiter les accès directs au socket Docker.

Traefik utilise deux réseaux Docker :

- `frontend-net` : réseau destiné à la communication avec les futurs services Web.
- `socket-net` : réseau interne réservé aux échanges entre Traefik et socket-proxy.

Les ports utilisés sont :

| Fonction | Port dans Ubuntu | Port accessible depuis Windows |
|---|---|---|
| HTTP | 80 | 8080 |
| HTTPS | 443 | 8443 |
| SSH | 22 | 2223 |

Les ports 8080, 8443 et 2223 sont utilisés dans notre laboratoire en raison des redirections configurées avec QEMU. Ils ne constituent pas une obligation pour le déploiement final du groupe.


## 3. Création du certificat TLS autosigné

### 3.1. Pourquoi un certificat TLS ?

Traefik utilise TLS pour chiffrer les communications HTTPS entre le client et le serveur.

Pour notre laboratoire, nous avons choisi un certificat autosigné, généré localement avec OpenSSL. Cette solution permet de tester HTTPS sans dépendre d'une autorité de certification externe.

Cependant, un certificat autosigné n'est pas automatiquement reconnu comme fiable par les navigateurs. Il convient donc à nos essais locaux, mais ne remplace pas un certificat délivré par une autorité de certification reconnue pour un service public.

### 3.2. Création du répertoire

Dans la VM Ubuntu, nous avons créé le répertoire destiné aux certificats :

```bash
mkdir -p /opt/ac-platform/certs
```

### 3.3. Génération du certificat et de la clé privée

Nous avons utilisé OpenSSL avec la commande suivante :

```bash
openssl req -x509 -newkey rsa:3072 -sha256 -days 365 -nodes -keyout /opt/ac-platform/certs/local.key -out /opt/ac-platform/certs/local.crt -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
```

Cette commande génère deux fichiers :

- `local.crt` : le certificat TLS autosigné.
- `local.key` : la clé privée associée au certificat.

Le certificat est prévu pour notre environnement local (`localhost` et `127.0.0.1`) et possède une durée de validité de 365 jours.

L'option `-nodes` permet de créer une clé privée sans phrase secrète, afin que Traefik puisse la charger automatiquement au démarrage. Cette clé doit donc être protégée par des permissions appropriées et ne jamais être publiée.

### 3.4. Protection de la clé privée

Le fichier `local.key` est sensible. Toute personne qui obtient cette clé pourrait usurper l'identité du serveur dans certaines conditions.

Nous ne plaçons donc pas cette clé dans notre dépôt GitHub.

Chaque membre du groupe doit générer sa propre paire certificat/clé privée sur sa VM.

Le fichier `tls.yaml` peut être partagé, car il contient uniquement les chemins des fichiers TLS, et non leur contenu.

**Vérification et correction des permissions**

Le 20 octobre 2026, nous avons vérifié les permissions de notre clé privée avec la commande suivante :

```bash
ls -l /opt/ac-platform/certs/local.key
```

Nous avons constaté que le fichier possédait les permissions `664` (`rw-rw-r--`). Cela signifiait que le propriétaire et les membres de son groupe pouvaient lire et modifier le fichier, tandis que les autres utilisateurs pouvaient également le lire.

Cette situation présentait un risque de sécurité, puisque la clé privée ne doit pas être accessible aux autres utilisateurs de la VM.

Nous avons donc exécuté la commande suivante :

```bash
chmod 600 /opt/ac-platform/certs/local.key
```

Cette commande réserve les permissions de lecture et d'écriture au propriétaire du fichier, `acadmin`.

Une nouvelle vérification avec `ls -l` a confirmé que les permissions étaient devenues `600` (`rw-------`).

**Vérification du fonctionnement de Traefik**

Après cette modification, nous avons redémarré les conteneurs Traefik et socket-proxy.

Nous avons ensuite vérifié leur état avec :

```bash
sudo docker ps
```

Les deux conteneurs étaient en cours d'exécution.

Pour vérifier que Traefik pouvait toujours établir une connexion HTTPS avec notre certificat, nous avons exécuté :

```bash
curl -kI https://localhost
```

Le serveur a répondu :

```text
HTTP/2 404
```

Cette réponse confirme que Traefik fonctionne toujours en HTTPS après la modification des permissions de la clé privée.

Le code `404 Not Found` est normal à cette étape, puisque les applications ne sont pas encore configurées derrière Traefik.

L'option `-k` permet d'effectuer ce test avec notre certificat autosigné sans exiger qu'il soit reconnu par une autorité de certification de confiance.

**Résultat :** la clé privée est maintenant protégée par les permissions `600`, et notre test confirme que Traefik continue de répondre en HTTPS.


## 4. Intégration du certificat TLS dans Traefik

### 4.1. Configuration du fichier tls.yaml

Nous avons créé le fichier suivant dans Ubuntu :

`/opt/ac-platform/traefik/dynamic/tls.yaml`

Son contenu est :

```yaml
tls:
  certificates:
    - certFile: /certs/local.crt
      keyFile: /certs/local.key
```

Ce fichier indique à Traefik où trouver le certificat TLS et la clé privée.

Les chemins `/certs/local.crt` et `/certs/local.key` correspondent aux chemins visibles depuis le conteneur Traefik, et non directement aux chemins de la VM Ubuntu.

### 4.2. Modifications apportées à compose.yaml

Dans le service `traefik`, nous avons ajouté le paramètre suivant :

```yaml
- "--providers.file.directory=/etc/traefik/dynamic"
```

Ce paramètre active la lecture des fichiers de configuration dynamique présents dans le répertoire `/etc/traefik/dynamic` du conteneur.

Nous avons également ajouté deux montages :

```yaml
volumes:
  - "/opt/ac-platform/traefik/dynamic:/etc/traefik/dynamic:ro"
  - "/opt/ac-platform/certs:/certs:ro"
```

Le premier montage permet à Traefik de lire le fichier `tls.yaml`.

Le deuxième permet à Traefik de lire le certificat `local.crt` et la clé privée `local.key`.

Le suffixe `:ro` signifie *read-only* (lecture seule). Il empêche le conteneur de modifier ces fichiers à travers ces montages.

### 4.3. Séparation entre configuration et secrets

Notre dépôt GitHub contient :

- `compose/compose.yaml`
- `traefik/dynamic/tls.yaml`

En revanche, les fichiers de certificats sont générés directement dans chaque VM, dans `/opt/ac-platform/certs`.

Cette séparation permet de partager la configuration technique du projet sans publier la clé privée TLS.

Le montage en lecture seule limite les modifications possibles depuis le conteneur, mais ne remplace pas la protection des permissions sur les fichiers de la VM.


## 5. Redirection HTTP vers HTTPS et validation

### 5.1. Configuration de la redirection

Nous avons configuré Traefik pour rediriger automatiquement les requêtes HTTP vers HTTPS.

Dans le fichier `compose.yaml`, les paramètres suivants ont été ajoutés au service Traefik :

```yaml
- "--entryPoints.web.http.redirections.entryPoint.scheme=https"
- "--entryPoints.web.http.redirections.entryPoint.to=:8443"
```

Le premier paramètre indique que la redirection doit utiliser HTTPS.

Le deuxième précise le port HTTPS utilisé depuis notre ordinateur Windows, soit `8443`.

Cette adaptation est nécessaire pour notre laboratoire QEMU, où le port `8443` de Windows est redirigé vers le port `443` de la VM Ubuntu.

Dans un environnement utilisant directement les ports standards HTTP 80 et HTTPS 443, cette configuration devra être adaptée, par exemple avec `to=websecure`.

### 5.2. Application de la configuration

Après avoir modifié `compose.yaml`, nous avons vérifié sa validité avec :

```bash
sudo docker compose -f /opt/ac-platform/compose/compose.yaml config --quiet
```

Cette commande vérifie la structure du fichier Compose, mais ne garantit pas à elle seule que Traefik fonctionne correctement.

Nous avons ensuite appliqué les modifications en recréant uniquement le conteneur Traefik :

```bash
sudo docker compose -f /opt/ac-platform/compose/compose.yaml up -d --no-deps traefik
```

Le service `socket-proxy` a été conservé.

### 5.3. Test de la redirection HTTP

Depuis PowerShell Windows, nous avons exécuté :

```powershell
curl.exe -I http://localhost:8080
```

Résultat obtenu :

```text
HTTP/1.1 308 Permanent Redirect
Location: https://localhost:8443/
```

Le code HTTP `308` confirme que Traefik demande au client d'utiliser l'adresse HTTPS, tout en conservant la méthode de la requête.

### 5.4. Test de la connexion HTTPS

Nous avons ensuite exécuté :

```powershell
curl.exe -kIL http://localhost:8080
```

L'option `-L` permet de suivre automatiquement la redirection. L'option `-k` autorise temporairement l'utilisation de notre certificat autosigné pour ce test.

Nous avons obtenu successivement :

```text
HTTP/1.1 308 Permanent Redirect
Location: https://localhost:8443/

HTTP/1.1 404 Not Found
```

La première réponse confirme la redirection HTTP vers HTTPS.

La deuxième confirme que le client a suivi la redirection et a obtenu une réponse du serveur HTTPS.

Le code `404 Not Found` est attendu à ce stade : aucune application, comme Drupal, Moodle ou Keycloak, n'est encore configurée derrière Traefik pour répondre à cette adresse.

### 5.5. Résultat et limites

Les tests confirment que la redirection HTTP vers HTTPS et la connexion TLS fonctionnent dans notre laboratoire.

Cependant, notre certificat est autosigné et n'est pas automatiquement reconnu comme fiable par les navigateurs.

De plus, l'option `-k` utilisée pendant les tests désactive la vérification du certificat par le client. Elle convient à cette vérification locale, mais ne doit pas être considérée comme une solution de sécurité pour les utilisateurs finaux.

Enfin, le port `8443` correspond à notre configuration QEMU actuelle. La redirection devra être revue lorsque le groupe définira les adresses et les ports de son environnement commun.



## 6. Précautions de sécurité et travaux restants

### 6.1. Protection de la clé privée TLS

La clé privée `local.key` doit rester confidentielle. Elle ne doit jamais être publiée sur GitHub ni transmise aux autres membres du groupe.

Chaque membre peut générer son propre certificat autosigné et sa propre clé privée sur sa VM.

Après la correction effectuée dans notre laboratoire, nous avons confirmé les permissions suivantes :

- `local.key` : `600` (`rw-------`), accessible uniquement à son propriétaire.
- `local.crt` : `664` (`rw-rw-r--`), certificat public pouvant être lu par les autres utilisateurs.

Ces permissions doivent être vérifiées sur chaque nouvelle VM. Le montage Docker en lecture seule (`:ro`) empêche la modification des fichiers par ce montage, mais n'empêche pas Traefik de lire la clé privée, ce qui est nécessaire à son fonctionnement.

### 6.2. Limites du certificat autosigné

Le certificat a été créé pour `localhost` et `127.0.0.1`.

Il ne sera donc pas automatiquement adapté à une connexion utilisant une autre adresse IP ou un autre nom d'hôte.

Si le groupe utilise l'adresse IP de la VM pour accéder à la plateforme, il faudra générer un certificat dont le champ `subjectAltName` contient cette adresse IP.

Un certificat autosigné ne garantit pas à lui seul l'identité du serveur auprès des utilisateurs. Pour éviter les avertissements de confiance, il faudra prévoir une méthode permettant aux clients de faire confiance au certificat ou à son autorité de certification.

### 6.3. Adaptation à l'environnement commun

La redirection vers le port `8443` correspond à notre laboratoire QEMU sur Windows.

Dans une installation où les utilisateurs accèdent directement à la VM sur les ports standards, nous pourrons utiliser une redirection vers l'entryPoint `websecure`, qui écoute sur le port `443`.

Cette adaptation devra être effectuée avant de considérer la configuration comme définitive.

### 6.4. Travaux restants

Les prochaines étapes du projet comprennent :

- Configurer les règles de routage Traefik vers Drupal, Moodle et Keycloak.
- Définir les noms d'hôte ou les adresses utilisés pour accéder à chaque application.
- Vérifier les certificats TLS avec les adresses retenues.
- Compléter la segmentation des réseaux Docker pour les services applicatifs et les bases de données.
- Tester les accès HTTPS aux applications une fois celles-ci déployées.
- Vérifier les permissions, les privilèges et les journaux des conteneurs.

Notre configuration actuelle constitue donc une base fonctionnelle pour HTTPS, mais ne représente pas encore la plateforme finale.