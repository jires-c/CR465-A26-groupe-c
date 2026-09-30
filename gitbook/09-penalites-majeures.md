# 9. Pénalités majeures

Les situations suivantes entraîneront une pénalité importante, voire l'échec de certains critères :

- secrets réels committés dans GitHub (-3 points sur Sécurité des images, -2 points sur Qualité des livrables);
- conteneurs applicatifs exécutés en mode privilégié sans justification sérieuse (-3 points sur Sécurité à l'exécution);
- exposition directe de PostgreSQL, Redis, n8n ou d'interfaces d'administration sensibles sur Internet (-3 points sur Segmentation réseau, -2 points sur Architecture et schéma);
- incapacité d'expliquer les réseaux, les volumes, les flux ou les dépendances (pénalité sur les critères concernés);
- absence réelle de cloud-init malgré une revendication de VM as Code (0 à 1 point sur VM as Code);
- vidéo dépassant 15 min 59 s (-2 points sur Qualité des livrables);
- dépôt incomplet ou non exploitable (-3 points sur Déploiement conteneurisé, -2 points sur Qualité des livrables);
- contributions individuelles non identifiables (-1 à -2 points sur Qualité des livrables).

Le WAF (OpenAppSec) étant un bonus optionnel, une équipe qui ne l'implémente pas ne subit aucune pénalité. Si une équipe tente le WAF mais le configure en mode cloud, le bonus de +2 points n'est simplement pas accordé, sans pénalité additionnelle — voir [6. Bonus — WAF](06-bonus-waf-openappsec.md).
