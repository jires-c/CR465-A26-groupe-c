# 6. Bonus — WAF (OpenAppSec)

## Pourquoi un bonus ?

L'ajout d'un WAF (Web Application Firewall) en frontal de Traefik est **optionnel**. Certaines équipes pourraient l'implémenter et gagner des points supplémentaires, alors que d'autres préfèrent consacrer leur temps à stabiliser le cœur obligatoire de la plateforme. Pour que ce choix reste équitable, le WAF est traité comme une **bonification**, et non comme un prérequis bloquant pour le livrable.

## Règles

- **+2 points**, ajoutés **au-delà** du total obligatoire de 40 points (total possible : 42/40).
- **Aucune pénalité** si votre équipe n'implémente pas de WAF.
- Pour être admissible au bonus, OpenAppSec doit être configuré **en mode local**, sans dépendance à un compte Check Point Infinity — cohérent avec la contrainte de souveraineté du projet (voir [3. Contraintes obligatoires](03-contraintes-obligatoires.md), point 10).
- Si le WAF est implémenté mais configuré en mode cloud, le bonus n'est simplement **pas accordé** — aucune pénalité additionnelle n'est appliquée sur les autres critères.

## Répartition du bonus

- **+2 points** : WAF fonctionnel, en mode local, bien intégré et clairement expliqué.
- **+1 point** : WAF présent mais partiellement fonctionnel ou peu expliqué.
- **0 point** : WAF absent, ou implémenté en mode cloud.

## À retenir

Ne sacrifiez pas la qualité des critères obligatoires (VM as Code, architecture, sécurité, segmentation, intégration fonctionnelle, IAM/SSO, observabilité, livrables) pour tenter le bonus WAF. Un projet obligatoire solide sans WAF sera toujours mieux noté qu'un projet obligatoire fragile avec un WAF ajouté en dernière minute.
