CR465 Groupe C - TEST FONCTIONNEL V5.4 (VM NEUVE)
====================================================
Etat : fichier cloud-init syntactiquement valide. Validation en conditions
reelles requise sur une nouvelle VM avant de declarer le deploiement final.

PARCOURS ACHAT (simulation, pas de carte bancaire)
1. Premier login etudiant-test dans Moodle via le bouton Keycloak Groupe C.
   User: etudiant-test ; secret: /opt/GroupeC/secrets/etudiant_test_password.
   Confirmer absence de demande de changement de mot de passe Moodle.
2. Confirmer que le cours CYBER465 n'est pas inscrit avant achat.
3. Sur Drupal /formations, utiliser etudiant-test@groupec.test, achat CYBER465.
4. Dans Drupal admin, receptionner le paiement manuel simule (completed).
5. Observer n8n -> Executions : chemin inscription Moodle, succes, commande ID.
6. Dans Moodle admin -> CYBER465 -> Participants : Etudiant Test = Student Active.
7. Dans une session privee etudiant, ouvrir le Dashboard et le contenu CYBER465.

SECURITE ET VERIFICATIONS
- Aucun vrai paiement; le webhook n8n est authentifie par X-GroupeC-Token.
- Tester token invalide -> HTTP 403; etudiant inexistant avec token valide -> 422.
- Ne JAMAIS afficher le token dans la capture n8n (en-tetes de Webhook).
- Ne pas publier secrets, .env, export credentials ni la cle de chiffrement.
- Docker Rootless (ubuntu): options SecurityOptions incluent rootless.
- Docker healthy: Drupal, Moodle et PostgreSQL.
- Traefik HTTPS 4443 / routes internes; reseaux de donnees et observabilite.
- Les essais manuels ci-dessus ne sont PAS executes par cloud-init a la place
  du professeur : il faut une transaction de demonstration et des captures.
- Projet pedagogique local et certificat TLS autosigne (non production).

LIMITES
- Une seule formation / commande dans le workflow (premier SKU).
- Confirmation paiement manuel par admin requise.
- L'etudiant doit se connecter au moins une fois au SSO avant l'inscription.
- Application Opencast, Redis et WAF OpenAppSec : non deployes.
