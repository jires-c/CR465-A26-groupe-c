# 10. Conseils de réalisation

## Orientations pédagogiques

L'objectif n'est pas de construire la plateforme la plus complexe possible. Une plateforme partiellement plus simple, mais bien comprise, bien sécurisée et bien documentée, sera mieux évaluée qu'une architecture trop ambitieuse et instable.

Vous êtes encouragés à :

- justifier vos choix plutôt que d'empiler des outils;
- privilégier la configuration, l'intégration visuelle et les outils graphiques lorsque cela suffit;
- expliquer les limites des images ou produits choisis;
- démontrer au moins un scénario simple d'investigation ou de diagnostic;
- expliciter vos hypothèses de sécurité;
- montrer ce qui a été automatisé et ce qui reste manuel.

Vous n'êtes pas pénalisés pour ne pas atteindre un niveau de production industriel complet. En revanche, vous serez pénalisés si vous exposez inutilement des services sensibles, si vous laissez des secrets dans le dépôt Git, ou si vous êtes incapables d'expliquer le fonctionnement de votre propre architecture.

L'usage d'outils d'IA générative est autorisé pour vous appuyer dans la configuration, la rédaction et le dépannage. Chaque membre de l'équipe doit toutefois être en mesure d'expliquer et de justifier tout élément produit avec cette aide.

## Étapes suggérées

Pour réussir ce travail, il est conseillé de procéder par étapes :

1. initialiser la VM avec cloud-init;
2. valider Docker et l'environnement hôte;
3. démarrer une version minimale de la stack;
4. ajouter progressivement Traefik, Drupal Commerce, Keycloak, n8n et Moodle;
5. intégrer ensuite les protections et l'observabilité (le WAF bonus, si tenté, vient à cette étape — voir [6. Bonus — WAF](06-bonus-waf-openappsec.md));
6. documenter au fur et à mesure;
7. préparer la vidéo comme une démonstration argumentée et non comme une simple visite d'écrans.

Un projet simple, stable et bien expliqué sera mieux noté qu'un projet trop ambitieux, incomplet ou peu maîtrisé.

## Rappel important

Le but de ce travail n'est pas uniquement de faire fonctionner des conteneurs. Il s'agit de démontrer votre capacité à **penser l'architecture**, à **automatiser le socle**, à **réduire les risques**, à **justifier vos choix** et à **communiquer clairement un système conteneurisé sécurisé**.
