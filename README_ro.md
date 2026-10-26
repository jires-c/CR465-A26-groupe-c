# CR465 — Groupe AC
## Reconstruction de la VM Ubuntu avec QEMU, cloud-init et Docker Compose

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