# Règles de sécurité du dépôt Git

Ce document présente les règles à respecter par tous les membres du groupe lors de l'utilisation du dépôt GitHub du projet.

Le dépôt étant public, aucun secret ou renseignement d'authentification sensible ne doit être ajouté à Git.

## 1. Informations à ne jamais déposer dans Git

Ne jamais ajouter au dépôt :

- une clé privée SSH ;
- une clé privée TLS ;
- un mot de passe ;
- un token ou une clé API ;
- un fichier `.env` contenant de vraies valeurs sensibles ;
- un certificat ou fichier contenant une clé privée ;
- une image de machine virtuelle (`.img`, `.qcow2`, `.vmdk`, `.vdi`).

Les clés publiques SSH peuvent être versionnées lorsqu'elles sont nécessaires à la configuration du projet. Les clés privées doivent toujours rester sur l'ordinateur de leur propriétaire.


## 2. Fichiers `.env` et `.env.example`

Les vraies valeurs sensibles utilisées par les services ne doivent pas être enregistrées dans Git.

Un fichier `.env` peut être utilisé localement pour stocker les valeurs nécessaires au fonctionnement des services. Ce fichier est exclu du dépôt par le `.gitignore`.

Lorsqu'il est nécessaire d'indiquer aux autres membres quelles variables doivent être configurées, utiliser un fichier `.env.example` contenant uniquement des valeurs fictives ou des espaces réservés.

Exemple :

`POSTGRES_PASSWORD=CHANGE_ME`

Le fichier `.env.example` peut être versionné dans Git, contrairement au fichier `.env` contenant les vraies valeurs.


## 3. Vérification avant un commit

Avant chaque commit, utiliser :

`git status`

Cette commande permet de vérifier les fichiers ajoutés, modifiés ou supprimés avant de les enregistrer dans l'historique Git.

Chaque membre doit vérifier qu'aucun secret, fichier `.env`, clé privée ou fichier inutile n'apparaît dans la liste des fichiers qui seront ajoutés au commit.

Chaque membre travaille dans sa propre branche. Les modifications doivent être vérifiées avant d'être intégrées à la branche `main`.

Le fichier `.gitignore` constitue une protection supplémentaire contre l'ajout accidentel de certains fichiers sensibles ou inutiles, mais il ne remplace pas la vérification par les membres du groupe.