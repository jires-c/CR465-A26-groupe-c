# CR465 - Groupe AC : Demarrage de la VM Ubuntu avec QEMU

## 1. Objectif

Ce document explique comment demarrer la VM Ubuntu du projet CR465 depuis Windows avec QEMU et comment configurer les redirections de ports pour SSH, HTTP et HTTPS.

## 2. Commande de demarrage

Depuis PowerShell Windows, se placer dans le repertoire contenant ac-test2.img, puis executer :

```powershell
& "C:\Program Files\qemu\qemu-system-x86_64.exe" -net nic -net user,hostfwd=tcp:127.0.0.1:2223-:22,hostfwd=tcp:127.0.0.1:8080-:80,hostfwd=tcp:127.0.0.1:8443-:443 -machine accel=tcg -m 2048 -nographic -hda ac-test2.img
```

```

## 3. Redirections de ports

La commande QEMU configure trois redirections entre Windows et la VM Ubuntu.

| Adresse et port Windows | Port Ubuntu | Utilisation |
|---|---|---|
| 127.0.0.1:2223 | 22 | Connexion SSH et transfert SCP |
| 127.0.0.1:8080 | 80 | Acces HTTP aux services de la VM |
| 127.0.0.1:8443 | 443 | Acces HTTPS aux services de la VM |

Les redirections utilisent 127.0.0.1, ce qui limite leur acces direct a l ordinateur Windows.

La redirection du port 8443 vers le port 443 ne configure pas HTTPS a elle seule. Dans notre projet, Traefik gere les connexions HTTPS selon sa configuration TLS.

## 4. Explication des parametres QEMU

| Parametre | Explication |
|---|---|
| -net nic | Ajoute une carte reseau virtuelle a Ubuntu. |
| -net user | Utilise le reseau utilisateur de QEMU, avec traduction des adresses reseau. |
| hostfwd | Configure les redirections des ports Windows vers Ubuntu. |
| -machine accel=tcg | Utilise le moteur d emulation logicielle TCG de QEMU. |
| -m 2048 | Attribue 2048 Mio de memoire vive a la VM. |
| -nographic | Utilise une console texte sans interface graphique classique. |
| -hda ac-test2.img | Utilise ac-test2.img comme disque virtuel Ubuntu. |

Le caractere & place devant le chemin de QEMU permet a PowerShell d executer un programme dont le chemin contient des espaces.

Le fichier ac-test2.img doit se trouver dans le repertoire courant au moment du lancement. Sinon, il faut indiquer son chemin complet.

## 5. Precautions de securite et limites

Les redirections QEMU utilisent 127.0.0.1 afin de limiter leur acces direct a l ordinateur Windows.

La redirection 8443 vers 443 permet d atteindre le port HTTPS de la VM, mais ne garantit pas que HTTPS fonctionne. Les certificats TLS, les routes Traefik et les services doivent etre configures et verifies separement.

La commande de demarrage ne contient aucun mot de passe ni aucune cle privee.

Le disque virtuel ac-test2.img, les cles privees SSH et les fichiers contenant des secrets ne doivent pas etre publies sur GitHub.

Cette documentation decrit la configuration utilisee dans notre environnement de laboratoire. Elle ne constitue pas une preuve que tous les services sont accessibles depuis Windows.
