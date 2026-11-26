CR465 GROUPE C - PLATEFORME PEDAGOGIQUE CONTENEURISEE (V5.5)
=============================================================
Document technique associe au cloud-init V5.5. Utilisation en laboratoire.
IMPORTANT : le fichier a ete valide statiquement ; le test reel sur une VM
entierement neuve doit etre effectue avant sa remise comme version verifiee.

ARCHITECTURE / AUTOMATISATION
- Ubuntu 24.04 LTS, SSH par cle, UFW 22/4443.
- Docker Engine Rootless sous ubuntu avec linger=yes ; pas de daemon rootful.
- Traefik 3.5 HTTPS local, routage file provider, dashboard BasicAuth.
- Quatre reseaux: exposition, applicatif (172.28.240.0/24), donnees et
  observabilite. Donnees et observabilite ont le drapeau --internal.
- PostgreSQL 17 avec roles et bases distincts: moodle, keycloak, drupal,
  n8n et opencast (BD seule; application Opencast NON activee).
- Moodle 5.3 / Keycloak 26.8.2 : client OIDC, realm, SSO et demo etudiant.
- Moodle: 11 cours et API REST pour inscrire par identifiant du cours.
- Drupal 11 / Commerce : catalogue, paiement manuel SIMULE, webhook paye.
- n8n 2.17.0 : workflow Drupal -> recherche utilisateur/cours Moodle ->
  API enrol_manual_enrol_users -> confirmation et codes d'erreur 403/422/502.
- Grafana, Loki et Alloy : journaux Docker Rootless vers Loki/Grafana.
- Portainer CE et Nginx temporaire pour la demonstration de reverse proxy.
- Redis, OpenAppSec WAF et application Opencast : NON DEPLOYES.

CORRECTIFS V5.5
1. Healthcheck Drupal: URL HTTP locale avec Host: drupal.groupec.test.
2. Premier acces SSO: plugin local_groupec_ssofix installe avant connexion.
   Il efface auth_forcepasswordchange UNIQUEMENT pour etudiant-test@groupec.test
   avec authentification oauth2. Aucune desactivation globale de passwordpolicy.
3. Secret n8n et mots de passe crees sans retour a la ligne final sur VM neuve.
4. Aucune valeur de mot de passe/token inscrite en clair dans ce YAML.
5. Drupal: reverse_proxy et X-Forwarded-* de Traefik; evite les redirects HTTP 404.
6. Moodle: role systeme limite au compte n8n-service pour chercher un email.
7. n8n: webhookId top-level, deux conditions IF uniques, publication et restart.
8. Verification fail-fast: faux token sur webhook publie => HTTP 403.

ACCES WINDOWS
Lancer le compagnon GroupeC-Windows-Acces-V5.ps1 sur Windows en mode admin.
Il configure le NAT VirtualBox et le fichier hosts pour les noms du laboratoire.
Le cloud-init Linux ne peut pas configurer ces deux elements Windows.
URL Drupal : https://drupal.groupec.test:4443/formations
URL n8n : https://n8n.groupec.test:4443/
URL Moodle : https://moodle.groupec.test:4443/
URL Keycloak : https://keycloak.groupec.test:4443/
URL Grafana : https://grafana.groupec.test:4443/
URL Portainer : https://portainer.groupec.test:4443/
URL Traefik : https://dashboard.localhost:4443/dashboard/
Certificat autosigne : laboratoire local uniquement, non publiquement approuve.

SECRETS SUR LA VM (ne jamais les partager)
/opt/GroupeC/secrets/moodle_admin_password  (Moodle: admin)
/opt/GroupeC/secrets/etudiant_test_password  (Keycloak: etudiant-test)
/opt/GroupeC/secrets/keycloak_permanent_admin_password (groupec-admin)
/opt/GroupeC/secrets/drupal_admin_password  (Drupal: admin)
/opt/GroupeC/secrets/n8n_admin_password (n8n: admin@groupec.test)
/opt/GroupeC/secrets/n8n_webhook_token  (Drupal -> n8n)
/opt/GroupeC/secrets/n8n_encryption_key (NE JAMAIS REGENERER avec volume n8n existant)
Aucun token, fichier .env reel, cle ou mot de passe ne doit etre remis sur Git.

DEMONSTRATION COMPLETE AU PROFESSEUR
1. Lancer la VM neuve a partir du fichier cloud-init final ; verifier cloud-init.
2. Appliquer le script Windows NAT/hosts et ouvrir les interfaces HTTPS.
3. Se connecter dans Moodle via "Log in with Keycloak Groupe C" avec
   etudiant-test et le secret local. L'utilisateur Moodle se cree au premier SSO.
   Verifier acces au Dashboard SANS demande de changement de mot de passe.
4. Dans Moodle/My courses, il ne doit PAS encore y avoir CYBER465 avant achat
   sur la VM neuve (verifier visuellement avant de continuer).
5. Dans Drupal /formations, choisir CYBER465 (600 CAD) et utiliser la MEME
   adresse de courriel: etudiant-test@groupec.test.
6. Terminer le checkout avec paiement manuel fictif, puis confirmer la reception
   en tant qu'administrateur Drupal. AUCUNE transaction bancaire reelle.
7. Observer l'execution n8n (workflow GroupeCMoodle001). Webhook doit contenir
   commande_id, etat=completed, total_paye, email et article SKU=CYBER465.
8. Dans Moodle, cours CYBER465 -> Participants : etudiant-test avec role Student
   et statut Active ; se connecter comme etudiant et ouvrir pages/activites.
9. Capturer les preuves: rootless, healthy, paiement, workflow, inscrits,
   dashboard et pages etudiant, Grafana/Loki, securite des reseaux.

CONTROLES TECHNIQUES (SHELL UBUNTU, SANS SUDO POUR DOCKER)
sudo cloud-init status --wait --long
docker context show
docker info --format '{{json .SecurityOptions}}'
loginctl show-user ubuntu -p Linger
docker ps --format 'table {{.Names}}\t{{.Status}}'
docker inspect --format '{{.State.Health.Status}}' groupec-drupal-drupal-1
docker network inspect GroupeC-donnees --format '{{.Internal}}'
docker network inspect GroupeC-observabilite --format '{{.Internal}}'
sudo ufw status verbose
sudo tail -n 80 /var/log/cloud-init-output.log

LIMITES A DOCUMENTER
- Un seul SKU/formation est traite par commande dans le workflow V5.4.
- Le paiement manuel doit etre CONFIRME par un administrateur; un simple
  passage en caisse ne donne pas automatiquement acces a la formation.
- Le compte n8n-service ne doit pas etre expose; proteger l'interface n8n.
- Le labo utilise un certificat TLS autosigne ; il faut installer une vraie
  CA/certificat pour une application de production.
- Le workflow de demo n'a pas ete audite pour des achats simultanes a grande
  echelle, rejouements ou support du paiement reel.
- Une inscription deja presente ne prouve pas un nouvel achat; sur une VM neuve,
  capturer les participants AVANT et APRES l'achat.
- Bien verifier la presence des questions du quiz (l'ajout est non bloquant).
- Ne pas deployer le YAML sur la VM precedente: les secrets et volumes sont
  crees au PREMIER lancement et doivent rester coherents.
