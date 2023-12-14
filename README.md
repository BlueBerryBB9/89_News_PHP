# Ecole89 News

Lisez bien toutes les consignes et respectez bien les étapes de ce projet. Les livrables doivent être rendus dans l'ordre défini à la fin de ce fichier...
Vous devrez rendre votre travail dans un repo contenant vos nom et prénom, ainsi que l'énoncé du devoir `php-devoir_final`. La création du repo est automatique quand vous cliquez sur [`use this template`](https://github.com/new?owner=Ecole89-B2-2023-2024&template_name=b2-projet_final_php&template_owner=Ecole89-B2-2023-2024).

Le journal de l'école est de retour. L'équipe des développeur "front" a terminé son travail et vous fournit le projet dans sa version html/css. Le projet comprend donc :
<ul>
<li>la page d'accueil</li>
<li>la page des mentions légales</li>
<li>la page de contact</li>
<li>les pages correspondantes aux articles dans leur intégralité</li>
</ul>

## Découpages des templates
La première tâche va être de découper tout ça en regroupant ``header`` et ``footer`` dans un dossier ``templates``. Profitez en pour vous inspirer du travail réalisé et créer des pages pour :
<ul>
<li>la connexion</li>
<li>l'inscription'</li>
<li>la création d'articles</li>
</ul>

Prévoyez les liens nécessaire dans le menu du `header`.

## Mise en place de la BDD
La base de données comprendra 2 tables : `utilisateur` et `article`.

Un utilisateur aura un login, un email, un mot de passe et un rôle pouvant être au choix : `auteur` ou `admin`.

Un article aura un `titre`, un `contenu`, une `catégorie`, une `date de publication` et un `auteur`.

La colonne `auteur` contiendra une `clé étrangère` reliée à la `clé primaire` de la table `utilisateur`.

## Mise en place de la gestion utilisateur
Mettez en place l'inscription et la connexion.

Vous devrez donc créer une session si la connexion est réussie et rendre possible l'accès à la page de création d'un article uniquement si l'utilisateur est connecté.

## Création d'un article
Pour la création d'un article, l'utilisateur aura juste à renseigner le ``titre`` et le ``contenu``. La ``date de publication`` sera renseignée automatiquement, ainsi que l'``auteur`` grâce à l'``id`` de l'utilisateur connecté que vous récupèrerez dans sa session.

## Affichage des articles
Vous devrez dynamiser la page d'accueil afin de récupérer l'intégralité des articles et les afficher dans le html déjà en place que vous intégrerez dans une boucle en `php`.

Pour les catégories, vous proposerez à l'auteur de choisir entre `news`, `team`, et `work`.

La page d'accueil ne contiendra qu'un extrait du `contenu` de chaque article. Vous devrez donc "tronquer" le contenu avant de l'afficher. Pour afficher la catégorie vous pouvez essayer d'associer une couleur à chaque catégorie (rouge, vert, bleu).

Par contre, vous pourrez afficher l'intégralité du `contenu` sur une page dédiée, vers laquelle l'utilisateur est redirigé quand il clique sur "continue reading".

La difficulté réside dans la redirection entre ces 2 pages : souvenez de la superglobale `$_GET` qui utilise ce qui est indiqué dans l'`url`. Par exemple `localhost/article.php?id=1` redirigera vers le template `article.php`, et vous pourrez récupérer dans ce fichier la superglobale `$_GET` dont l'index `id` vaudra 1. Après, à vous d'utiliser cet id à bon escient...

## Livrables
### MVP (la moyenne)
Les templates découpés et la gestion utilisateur

### Bonus
La création d'un article

### Super bonus
L'affichage des articles sur la page d'accueil

### Note maximale
La redirection pour lire l'intégralité d'un article