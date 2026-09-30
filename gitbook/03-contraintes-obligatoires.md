# 3. Contraintes obligatoires

Les contraintes suivantes s'appliquent à tous les projets :

1. **VM as Code obligatoire.** Votre environnement doit être initialisé au moyen d'un fichier cloud-init. Ce fichier doit permettre de configurer au minimum l'utilisateur d'administration, les accès de base, les mises à jour initiales, l'installation de Docker et la préparation de l'environnement hôte.
2. **Docker Compose obligatoire.** Le déploiement applicatif doit être réalisé à l'aide de Docker Compose, ou de Podman avec sa couche de compatibilité Compose (`podman compose` ou `podman-compose`) comme moteur équivalent reconnu. Kubernetes n'est pas requis dans le cadre de ce travail.
3. **Une seule VM.** Le projet doit être réalisable sur une seule machine virtuelle Linux afin de limiter la complexité d'exploitation.
4. **Équipe de 3 à 5 personnes.** Les contributions individuelles doivent être identifiables (historique de commits Git, section de contribution dans le GitBook et le document de synthèse).
5. **Base de données simplifiée.** Une seule instance PostgreSQL doit être privilégiée lorsque cela est techniquement réaliste pour les services choisis.
6. **Approche sécurité explicite.** Les mécanismes de sécurité doivent être démontrés et expliqués, et non seulement déclarés dans la documentation.
7. **Aucune programmation avancée obligatoire.** Les intégrations orientées produit doivent privilégier les outils graphiques, les connecteurs, les webhooks et les interfaces visuelles.
8. **Paiement simplifié autorisé.** Le paiement peut être fictif, simulé ou en environnement sandbox. Aucune intégration comptable complète n'est requise. Aucune preuve de paiement réelle n'est exigée.
9. **Livrables numériques obligatoires.** La remise doit comprendre une vidéo, un GitBook, un dépôt GitHub et un document de synthèse Word ou PowerPoint.
10. **Souveraineté et absence de dépendance cloud tierce.** La plateforme doit demeurer auto-hébergée et fonctionner sans dépendance à un service cloud externe. Si votre équipe choisit d'implémenter le WAF bonus (OpenAppSec), celui-ci doit être configuré en mode local, sans compte Check Point Infinity. Les mécanismes de scan d'images doivent utiliser des outils locaux ou hors-ligne (ex. Trivy, Grype) plutôt qu'un service cloud.
