# CR465 — Groupe AC : préparation de la publication GitHub

**Document :** `docs/operations/publication-github.md`
**Branche :** `rolande`
**Date :** 23 octobre 2026
**Statut :** préparation en cours — aucun commit ni push effectué

## 1. Objectif

Cette procédure décrit les opérations réalisées pour préparer la publication sur GitHub des modifications techniques et des documentations du projet CR465.
Le travail ne consiste pas uniquement à ajouter des documents Markdown. Il comprend également la récupération et l'intégration du fichier compose.yaml utilisé sur la machine virtuelle Ubuntu, afin que le dépôt Git reflète les configurations réalisées.
Les principaux éléments concernés sont :
- Traefik et Docker socket-proxy ;
- PostgreSQL 17 ;
- le volume persistant PostgreSQL ;
- le secret Docker Compose associé au mot de passe PostgreSQL ;
- les réseaux frontend-net, socket-net, app-net et data-net ;
- les documentations PostgreSQL et segmentation réseau.
L'objectif est de publier les configurations reproductibles sans publier les secrets ni les données internes des conteneurs.

## 2. Environnement de travail

| Élément | Configuration |
|---|---|
| Ordinateur hôte | Windows 11 |
| Terminal | PowerShell |
| Virtualisation | QEMU |
| Machine virtuelle | Ubuntu Server 24.04.5 LTS |
| Utilisateur Ubuntu | acadmin |
| Connexion | SSH avec redirection du port 2223 |
| Dépôt distant | https://github.com/jires-c/CR465-A26-groupe-c.git |
| Branche | rolande |
Répertoire local du projet :
C:\Users\rolat\Documents\TP_Conteneurs\CR465-A26-groupe-c
Fichier Compose utilisé sur Ubuntu :
/opt/ac-platform/compose/compose.yaml
Fichier Compose suivi par Git :
compose/compose.yaml

### Démarrage de la VM avec QEMU

La procédure détaillée est conservée dans [`demarrage-vm-qemu.md`](demarrage-vm-qemu.md). La commande et ses redirections sont également rappelées ci-dessous pour documenter l’environnement utilisé lors de la préparation GitHub.

La VM Ubuntu est exécutée sur Windows avec QEMU. La commande utilisée comprend trois redirections de ports permettant d'accéder à SSH et aux ports HTTP/HTTPS de la VM.
Commande PowerShell :

```powershell
& "C:\Program Files\qemu\qemu-system-x86_64.exe" -net nic -net user,hostfwd=tcp:127.0.0.1:2223-:22,hostfwd=tcp:127.0.0.1:8080-:80,hostfwd=tcp:127.0.0.1:8443-:443 -machine accel=tcg -m 2048 -nographic -hda ac-test2.img
```

| Port Windows | Port Ubuntu | Fonction |
|---|---|---|
| 127.0.0.1:2223 | 22 | Connexion SSH et transfert SCP |
| 127.0.0.1:8080 | 80 | Accès HTTP |
| 127.0.0.1:8443 | 443 | Accès HTTPS |
Les redirections sont liées à 127.0.0.1, ce qui limite leur accès direct à l'ordinateur Windows.
La redirection vers le port 443 ne configure pas elle-même HTTPS : la gestion TLS relève de Traefik.
Le fichier `ac-test2.img` est le disque virtuel local. Il ne doit pas être publié dans GitHub.

## 3. Vérification initiale du dépôt Git

Les commandes suivantes ont été exécutées dans PowerShell Windows :

```bash
git status
```

```bash
git branch --show-current
```

```bash
git remote -v
```

```bash
git ls-files
```

### Résultats observés

- La branche active était rolande.
- Git indiquait que la branche était à jour par rapport à sa référence locale origin/rolande.
- Le dépôt distant origin pointait vers le dépôt GitHub du groupe.
- Le fichier compose/compose.yaml était déjà suivi par Git.
- Les deux documentations suivantes étaient présentes, mais non encore suivies :
docs/architecture/Documentation_Segmentation_reseau.md
docs/operations/Documentation_PostgreSQL.md
Précision : l'indication « up to date » ne remplace pas une vérification ultérieure des éventuels nouveaux commits distants.

## 4. Comparaison des configurations Docker Compose

### 4.1 Vérification du fichier Windows

Commande exécutée :

```powershell
Get-Content .\compose\compose.yaml
```

Le fichier contenait Traefik, socket-proxy, frontend-net et socket-net.
Il ne contenait pas encore PostgreSQL, app-net, data-net, le volume persistant ou la déclaration du secret PostgreSQL.

### 4.2 Vérification du fichier Ubuntu

Après connexion SSH à la VM, les commandes suivantes ont été exécutées :

```bash
sudo ls -l /opt/ac-platform/compose/compose.yaml
```

```bash
cat /opt/ac-platform/compose/compose.yaml
```

Le fichier Ubuntu contenait les configurations supplémentaires suivantes :
- postgres:17 ;
- restart: unless-stopped pour les trois services ;
- POSTGRES_PASSWORD_FILE ;
- le secret Compose postgres_password ;
- le volume nommé postgres-data ;
- les réseaux internes app-net et data-net.
Le réseau app-net était déclaré, mais n'était pas encore associé à un service dans le fichier examiné.

## 5. Problème rencontré lors du transfert SCP

### 5.1 Première tentative

La commande initiale était :

```bash
scp -P 2223 acadmin@127.0.0.1:/opt/ac-platform/compose/compose.yaml .\compose\compose-ubuntu-verification.yaml
```

La première tentative a rencontré une interruption de connexion. Une autre tentative a produit :
Permission denied (publickey).

### 5.2 Diagnostic

La connexion SSH existante fonctionnait, mais la nouvelle connexion utilisée par scp ne présentait pas la bonne clé privée.
La commande suivante a permis d'inventorier les noms des fichiers SSH disponibles sur Windows :

```powershell
Get-ChildItem $HOME\.ssh | Select-Object Name
```

Une clé dédiée au projet a été identifiée :
tp_conteneurs_ed25519
La vérification de l'empreinte de la clé publique du serveur SSH Ubuntu avait également été effectuée :
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
L'empreinte correspondait à celle présentée lors de la connexion SSH.

### 5.3 Solution

La connexion a réussi lorsque la clé privée du projet a été précisée explicitement :

```bash
ssh -i "$HOME\.ssh\tp_conteneurs_ed25519" -o IdentitiesOnly=yes -p 2223 acadmin@127.0.0.1
```

Une phrase secrète a été demandée pour déverrouiller cette clé privée.
Le transfert a ensuite été effectué avec :

```bash
scp -i "$HOME\.ssh\tp_conteneurs_ed25519" -o IdentitiesOnly=yes -P 2223 acadmin@127.0.0.1:/opt/ac-platform/compose/compose.yaml .\compose\compose-ubuntu-verification.yaml
```

Résultat : transfert réussi, 1 556 octets copiés.
La clé privée et sa phrase secrète n'ont pas été copiées dans le dépôt Git.

## 6. Vérification et intégration du fichier Compose

### 6.1 Comparaison des deux versions

Commande exécutée sur Windows :

```bash
git diff --no-index -- .\compose\compose.yaml .\compose\compose-ubuntu-verification.yaml
```

Les différences observées correspondaient aux ajouts techniques réalisés sur Ubuntu. Aucune suppression de configuration existante n'a été constatée dans cette comparaison.

### 6.2 Validation Docker Compose

Une tentative de validation sur Windows a échoué parce que la commande docker n'était pas disponible sur l'hôte.
La validation a donc été réalisée directement sur Ubuntu :

```bash
cd /opt/ac-platform/compose && docker compose config --quiet
```

Résultat : commande terminée sans erreur affichée.
Cette vérification confirme que Docker Compose accepte la configuration. Elle ne constitue pas à elle seule un test de fonctionnement de tous les services.

### 6.3 Sauvegarde et intégration sur Windows

Avant de remplacer le fichier suivi par Git, une sauvegarde locale a été créée :

```powershell
Copy-Item .\compose\compose.yaml .\compose\compose-avant-postgresql.bak
```

La version Ubuntu a ensuite été copiée dans le fichier suivi par Git :

```powershell
Copy-Item .\compose\compose-ubuntu-verification.yaml .\compose\compose.yaml
```

Les changements ont été contrôlés avec :

```bash
git diff -- compose/compose.yaml
```

Résultat : les ajouts PostgreSQL, réseaux, volume, secret et politiques de redémarrage apparaissaient correctement dans le fichier suivi par Git.
Des avertissements concernant les fins de ligne LF et CRLF ont été observés sur Windows. Ils n'ont pas empêché la comparaison.

## 7. Protection des fichiers et modification de .gitignore

Le fichier .gitignore contenait déjà des exclusions pour plusieurs types de fichiers sensibles, notamment les fichiers .env, certaines extensions de clés privées et les images de machines virtuelles.
Deux exclusions supplémentaires ont été ajoutées :

```gitignore
# Copies locales de verification du Compose
/compose/compose-ubuntu-verification.yaml
/compose/compose-avant-postgresql.bak
```

Ces règles empêchent Git de proposer normalement ces deux fichiers temporaires non suivis.
Il faut également rappeler que .gitignore ne remplace pas une vérification de sécurité : il ne protège pas automatiquement contre un secret écrit directement dans un fichier suivi par Git.

### Vérification de .gitignore

Les règles ci-dessus ont ensuite été vérifiées.

Une première vérification avec :

```bash
git status --short --ignored
```

a montré que compose-ubuntu-verification.yaml était ignoré (!!), mais que la sauvegarde .bak apparaissait encore comme non suivie (??).
Plusieurs vérifications ont été effectuées :

```powershell
Get-Content .gitignore | Select-Object -Last 6
```

```bash
git check-ignore -v --no-index compose/compose-avant-postgresql.bak
```

```bash
git ls-files --stage -- compose/compose-avant-postgresql.bak
```

```bash
git check-ignore -v -- compose/compose-avant-postgresql.bak
```

```bash
git status --short --ignored --untracked-files=all
```

```powershell
Get-ChildItem .\compose | Select-Object Name
```

Les résultats ont confirmé que la règle d'exclusion existait, que le nom du fichier était correct et que la sauvegarde ne figurait pas dans l'index Git.
Une vérification supplémentaire a été effectuée :

```bash
git -c core.untrackedCache=false status --short --ignored
```

Résultat observé : les deux fichiers temporaires apparaissaient avec !!, indiquant qu'ils étaient ignorés lors de cette vérification.
L'influence du cache des fichiers non suivis est une hypothèse plausible, mais sa responsabilité exacte n'a pas été démontrée.

## 8. Gestion du secret PostgreSQL

La configuration Compose contient une référence au secret, et non le mot de passe en clair :

```yaml
environment:
  POSTGRES_PASSWORD_FILE: /run/secrets/postgres_password
secrets:
  - postgres_password
```

La déclaration globale est :

```yaml
secrets:
  postgres_password:
    file: /opt/ac-platform/secrets/postgres_password
```

Le fichier réel `/opt/ac-platform/secrets/postgres_password` reste sur Ubuntu et **ne doit pas être publié sur GitHub**. Chaque environnement de déploiement doit fournir son propre fichier secret. Les volumes Docker et les données internes de PostgreSQL ne doivent pas être ajoutés au dépôt.

### Vérification des informations sensibles

Une recherche ciblée a été exécutée dans `compose/compose.yaml` :

```powershell
Select-String -Path .\compose\compose.yaml -Pattern 'password|secret|token|api.key|private.key' -CaseSensitive:$false
```

Les correspondances examinées concernaient les noms et chemins du secret PostgreSQL (`POSTGRES_PASSWORD_FILE`, `postgres_password`, `/opt/ac-platform/secrets/postgres_password`). Aucun mot de passe en clair n'a été constaté dans ces résultats.

Les fichiers `docs/operations/Documentation_PostgreSQL.md` et `docs/architecture/Documentation_Segmentation_reseau.md` ont aussi fait l'objet de recherches ciblées d'affectations de mots de passe, de jetons, de clés API et de marqueurs de clés privées. Aucune correspondance n'a été affichée lors des recherches les plus précises.

Une recherche d'adresses IPv4 a fait apparaître des adresses locales ou privées, notamment `127.0.0.1`, `172.18.0.2`, `172.19.0.2`, `172.19.0.3` et `172.20.0.2`, ainsi que les sous-réseaux Docker associés. Aucune adresse IP publique n'a été identifiée dans les résultats examinés.

**Limite :** ces recherches ciblées ne constituent pas un audit exhaustif de tous les secrets possibles.

### Vérification de la mise en forme avec Git

La commande `git diff --check` a initialement signalé `compose/compose.yaml:60: new blank line at EOF`. La ligne vide supplémentaire a été corrigée dans PowerShell :

```powershell
$path = '.\compose\compose.yaml'; $content = [System.IO.File]::ReadAllText((Resolve-Path $path).Path); [System.IO.File]::WriteAllText((Resolve-Path $path).Path, ($content.TrimEnd([char]13,[char]10) + "`n"), (New-Object System.Text.UTF8Encoding($false)))
```

Un nouveau `git diff --check` n'a plus signalé cette anomalie. Les avertissements de conversion LF/CRLF étaient toujours présents, sans autre anomalie signalée.

## 9. État de la publication

| Opération | Statut |
|---|---|
| Branche et dépôt distant vérifiés | Terminé |
| Inventaire des fichiers suivis | Terminé |
| Comparaison du Compose Windows et Ubuntu | Terminé |
| Transfert SCP avec clé dédiée | Terminé |
| Validation Docker Compose sur Ubuntu | Terminé |
| Sauvegarde locale de l'ancien Compose | Terminée |
| Intégration du Compose Ubuntu sur Windows | Terminée |
| Vérification des modifications avec git diff | Terminée |
| Ajout des exclusions .gitignore | Terminé |
| Contrôle des fichiers ignorés | Confirmé avec cache désactivé |
| Recherches ciblées de secrets | Effectuées |
| Vérification des adresses IP documentées | Effectuée |
| Correction de la fin de compose.yaml | Terminée |
| Documentation QEMU indépendante (`demarrage-vm-qemu.md`) | Rédigée et vérifiée localement |
| Vérification finale des fichiers à préparer | À effectuer |
| git add ciblé | Non effectué |
| Commit | Non effectué |
| Push GitHub | Non effectué |

## 10. Prochaines étapes

Première commande prévue :

```bash
git status --short --ignored
```

Ensuite :
1. Recontrôler les deux exclusions si de nouveaux changements ont été faits.
2. Examiner les modifications de `.gitignore`.
3. Vérifier les documentations Markdown à publier.
4. Examiner les fichiers candidats pour détecter d’éventuels secrets.
5. Vérifier l'état récent de la branche distante avant publication.
6. Ajouter uniquement les fichiers validés avec des commandes git add ciblées.
7. Contrôler les modifications préparées pour le commit.
8. Créer le commit.
9. Effectuer le push normal sur la branche rolande.
10. Vérifier les fichiers publiés sur GitHub.
Ne pas utiliser git add . sans inspection préalable, ni git push --force.

### Reprise du travail

Les prochaines opérations devront être réalisées une commande à la fois, dans PowerShell Windows.
Les fichiers prévus pour la publication sont :

- `.gitignore`
- `compose/compose.yaml`
- `docs/architecture/Documentation_Segmentation_reseau.md`
- `docs/operations/Documentation_PostgreSQL.md`
Les documents `docs/operations/connexion-ssh.md`, `docs/operations/demarrage-vm-qemu.md` et `docs/operations/publication-github.md` devront également être pris en compte.
Avant le commit, vérifier les fichiers modifiés, les informations sensibles, la zone de préparation Git et l'état de la branche distante.
Ne pas utiliser git add . ni git push --force.

## 11. Publication effective sur GitHub

Date : 23 octobre 2026
Branche : rolande
Commit : 8541d05

Les sept fichiers prepares ont ete enregistres dans un commit local, puis publies sur GitHub avec les commandes suivantes :

```powershell
git commit -m "Ajout PostgreSQL, segmentation reseau et documentation technique"
git push origin rolande
```

Le push a reussi vers le depot CR465-A26-groupe-c sur la branche rolande.

La verification finale avec git status -sb a confirme la synchronisation entre rolande et origin/rolande, sans modification locale restante.

La publication sur la branche rolande est terminee. Aucune fusion avec la branche principale du projet n a ete effectuee.
