# CR465 – Architecture actuellement déployée

## 1. Présentation du projet

Ce document présente l'architecture actuellement mise en place pour le travail de session du cours CR465 – Technologies et sécurité des conteneurs. Il décrit les composants déjà déployés, leur rôle et les principaux choix techniques et de sécurité réalisés jusqu'à présent.

L'objectif du projet est de déployer, sur une machine virtuelle Linux créée et configurée à l'aide de cloud-init, une plateforme pédagogique conteneurisée avec Docker Compose.

L'architecture finale devra notamment intégrer Traefik, Drupal Commerce, Moodle, Keycloak, n8n, PostgreSQL et Portainer. Les différents services seront progressivement intégrés en appliquant des principes de sécurité tels que la segmentation réseau, la réduction de l'exposition des services, le moindre privilège et la protection de l'accès au Docker Engine.

## 2. État actuel du projet

À ce stade, les éléments suivants ont été réalisés :

- création et démarrage d'une VM Ubuntu avec QEMU ;
- configuration initiale de la VM avec cloud-init ;
- création de l'utilisateur administrateur `acadmin` ;
- configuration de l'accès SSH par clés publiques ;
- installation de Docker Engine et Docker Compose ;
- préparation du répertoire `/opt/ac-platform/compose` ;
- activation du pare-feu UFW ;
- déploiement de Traefik ;
- déploiement d'un Docker socket proxy ;
- création d'un réseau `frontend-net` pour le côté exposition ;
- création d'un réseau interne `socket-net` pour la communication entre Traefik et le socket proxy ;
- publication des ports 80 et 443 de Traefik ;
- validation de la communication entre Traefik et le Docker socket proxy ;
- validation de l'accès HTTP à Traefik.

Les autres composants de la plateforme ne sont pas encore déployés.

Les ports 80 et 443 sont actuellement publiés par Traefik. La configuration TLS et les certificats HTTPS ne sont toutefois pas encore mis en place. Le port 443 est donc préparé pour la suite du déploiement, mais cela ne signifie pas encore que HTTPS est fonctionnel.


## 3. Architecture actuellement déployée

L'architecture actuellement mise en place est volontairement minimale. Elle constitue la base sur laquelle les autres services seront ajoutés progressivement.

```text
                         VM Ubuntu
                             |
                      ports 80 / 443
                             |
                             v
                          Traefik
                         /       \
                        /         \
                       v           v
                frontend-net    socket-net
                                  |
                                  v
                             socket-proxy
                                  |
                                  v
                       /var/run/docker.sock
                                  |
                                  v
                            Docker Engine  
```

### Rôle des composants

**Traefik**  
Traefik est le reverse proxy de la plateforme. Il constitue le point d'entrée centralisé pour les services web. À terme, il recevra les requêtes des utilisateurs et les dirigera vers les services concernés, par exemple Drupal Commerce, Moodle ou Keycloak.

**frontend-net**  
`frontend-net` est le réseau utilisé du côté de l'exposition. Traefik y est actuellement connecté. Les services web qui devront être accessibles par l'intermédiaire de Traefik pourront être connectés à ce réseau selon leurs besoins.

**Docker socket**  
Le Docker socket `/var/run/docker.sock` est un point de communication permettant d'accéder à l'API du Docker Engine. Cet accès étant sensible, le socket n'est pas monté directement dans le conteneur Traefik.

**socket-proxy**
Le socket proxy sert d'intermédiaire entre Traefik et le Docker socket. Traefik communique avec le socket proxy en TCP sur le port interne 2375 au lieu d'accéder directement à `/var/run/docker.sock`. Le Docker socket est monté dans le conteneur socket-proxy avec l'option `:ro`. L'accès à l'API Docker est également limité aux catégories nécessaires à Traefik dans la configuration actuelle : `CONTAINERS`, `EVENTS`, `PING` et `VERSION`. Cette architecture permet ainsi d'éviter de donner à Traefik un accès direct au Docker socket.

**socket-net**
`socket-net` est un réseau Docker interne réservé à la communication entre Traefik et le socket proxy. Il est configuré avec `internal: true`. Traefik est connecté à la fois à `frontend-net`, pour le côté exposition, et à `socket-net`, uniquement pour communiquer avec le socket proxy. Cette séparation évite de placer le socket proxy sur le réseau d'exposition et limite ainsi son accessibilité aux composants qui en ont besoin.