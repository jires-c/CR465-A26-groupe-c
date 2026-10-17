

# CR465 — Infrastructure Docker et Keycloak

## 1. Objectif

Déployer une infrastructure conteneurisée comprenant :

- PostgreSQL : base de données
- Traefik : reverse proxy
- Keycloak : gestion des identités et authentification SSO

## 2. Environnement

- Ubuntu Server 22.04 LTS
- Docker Engine
- Docker Compose
- VirtualBox

## 3. Configuration

Depuis la racine du dépôt :

```bash
cp docker/.env.example docker/.env
```

Modifier le fichier docker/.env pour définir les mots de passe.

Ne jamais publier ce fichier.

## 4. Démarrage

```bash
cd docker
sudo docker compose up -d
```

## 5. Vérification

```bash
sudo docker compose ps
```

Services attendus :

- PostgreSQL
- Traefik
- Keycloak

## 6. Accès à Keycloak

Adresse de développement :

http://keycloak.localhost:8080/admin/

Si Docker fonctionne dans une VM avec des ports liés à
127.0.0.1, un tunnel SSH peut être nécessaire.

## 7. Configuration Keycloak

Realm créé : CR465

L'authentification centralisée des applications sera
configurée avec Keycloak.

## 8. Travaux restants

- Automatiser l'initialisation PostgreSQL
- Configurer HTTPS
- Intégrer les applications au SSO
- Tester le déploiement sur une nouvelle VM

## 9. Sécurité

- Ne jamais publier les mots de passe
- Utiliser des variables d'environnement
- Supprimer les comptes administrateurs temporaires
- Limiter l'accès aux interfaces d'administration
