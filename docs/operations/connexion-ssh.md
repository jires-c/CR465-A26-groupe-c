# Connexion SSH à la VM

## 1. Objectif

L'accès à la machine virtuelle Ubuntu se fait en SSH à l'aide de clés cryptographiques.

L'authentification SSH par mot de passe est désactivée dans la configuration cloud-init (`ssh_pwauth: false`). Chaque membre du groupe doit donc utiliser sa propre clé privée pour se connecter au compte `acadmin`.

Les clés publiques correspondantes sont déclarées dans le fichier `cloud-init/user-data.yaml`.

## 2. Prérequis

Avant de se connecter à la VM, chaque membre doit disposer :

- de sa propre paire de clés SSH ;
- de sa clé privée sur son ordinateur ;
- de sa clé publique déclarée dans `cloud-init/user-data.yaml` ;
- d'une VM créée à partir de la configuration cloud-init du projet.

La clé privée doit rester sur l'ordinateur de son propriétaire. Elle ne doit jamais être copiée dans le dépôt GitHub ni partagée avec un autre membre du groupe.


## 3. Démarrage de la VM et redirection SSH

Dans l'environnement QEMU utilisé pour le projet, la VM est démarrée avec une redirection du port SSH.

La configuration suivante est utilisée :

`hostfwd=tcp::2223-:22`

Elle signifie que :

- le port `2223` de l'ordinateur Windows est redirigé vers le port `22` de la VM Ubuntu ;
- le port `22` est le port utilisé par le service SSH dans la VM ;
- lorsque la VM est exécutée sur son propre ordinateur, le membre peut utiliser `localhost` pour la joindre.

Le chemin de communication est donc :

`Windows localhost:2223 → QEMU → VM Ubuntu:22 → service SSH`

Chaque membre qui exécute sa propre VM avec cette même redirection peut utiliser `localhost`. `localhost` désigne l'ordinateur sur lequel la commande SSH est exécutée ; il ne permet pas de joindre directement une VM exécutée sur l'ordinateur d'un autre membre.


## 4. Connexion SSH

Une fois la VM démarrée, ouvrir PowerShell sur Windows et utiliser la commande suivante :

`ssh -i "$env:USERPROFILE\.ssh\NOM_DE_LA_CLE_PRIVEE" -p 2223 acadmin@localhost`

Chaque membre doit remplacer `NOM_DE_LA_CLE_PRIVEE` par le nom de sa propre clé privée SSH.

Par exemple, si la clé privée se nomme `tp_conteneurs_ed25519`, la commande devient :

`ssh -i "$env:USERPROFILE\.ssh\tp_conteneurs_ed25519" -p 2223 acadmin@localhost`

Dans cette commande :

- `ssh` lance le client SSH ;
- `-i` indique le fichier contenant la clé privée à utiliser ;
- `$env:USERPROFILE\.ssh\...` désigne le dossier `.ssh` de l'utilisateur Windows ;
- `-p 2223` indique le port utilisé sur l'ordinateur Windows ;
- `acadmin` est le compte utilisé dans la VM Ubuntu ;
- `localhost` désigne l'ordinateur sur lequel QEMU et la VM sont exécutés.

Si la clé privée est protégée par une passphrase, SSH peut demander cette passphrase lors de la connexion. La passphrase protège la clé privée locale et n'est pas un mot de passe de connexion à la VM.

## 5. Fin de la session

Pour quitter une session SSH sans arrêter la VM, utiliser :

`exit`

Pour arrêter complètement la VM Ubuntu de manière propre, utiliser dans la VM :

`sudo poweroff`

Après `sudo poweroff`, la connexion SSH est interrompue automatiquement lorsque la VM s'arrête.