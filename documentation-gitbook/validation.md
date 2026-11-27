# Validation et tests

L’équipe indique que les tests de l’infrastructure ont réussi. Les résultats détaillés et captures ne sont pas inclus dans cette copie Markdown ; ils sont à joindre si disponibles.

## Contrôles techniques
```bash
sudo cloud-init status --wait --long
docker context show
docker info --format '{{json .SecurityOptions}}'
loginctl show-user ubuntu -p Linger
docker ps
docker network inspect GroupeC-donnees --format '{{.Internal}}'
docker network inspect GroupeC-observabilite --format '{{.Internal}}'
sudo ufw status verbose
```

## Scénario fonctionnel
1. Se connecter via Keycloak avec `etudiant-test`.
2. Vérifier l’absence de CYBER465 avant achat.
3. Commander CYBER465 (600 CAD, paiement fictif) avec le même email.
4. Confirmer le paiement dans Drupal.
5. Vérifier l’exécution n8n puis l’inscription Moodle (Student, Active).
6. Accéder au cours et consulter les journaux dans Grafana.

## Contrôles de sécurité attendus
Jeton invalide : 403 ; utilisateur ou cours introuvable : 422 ; réseaux internes : `true` ; accès direct externe PostgreSQL et SSH par mot de passe : refusés. Ne publier aucune capture contenant des secrets.

[Retour à la documentation](../README.md)
