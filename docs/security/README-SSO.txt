SSO Moodle / Keycloak Groupe C — AUTOMATISE
============================================
Sur VM neuve, cloud-init configure Keycloak, Moodle OAuth2, les 3 endpoints,
les 3 mappings etudiant, ports cURL et le blocage SSRF restant, ainsi que le
compte etudiant-test et un administrateur permanent groupec-admin.
Credentials sur VM sous /opt/GroupeC/secrets (ne pas committer).
Le compte bootstrap admin est supprime APRES verification API des privileges.
Le bouton Log in with Keycloak Groupe C doit apparaitre sur Moodle.
Le test de navigateur Windows est indispensable: ./README-FINAL-CR465.txt.
