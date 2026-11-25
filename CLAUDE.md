# Fork Koillection de Nicolas (nbourre/koillection)

Fork de [benjaminjonard/koillection](https://github.com/benjaminjonard/koillection) (Symfony, Doctrine, Twig, FrankenPHP), déployé sur un VPS à collection.nibotek.com. Nicolas l'utilise surtout pour sa collection de pièces de monnaie, mais **toute modification doit rester générique** : elle doit marcher pour n'importe quelle collection et n'importe quel gabarit, pas seulement les pièces.

Réponds en français québécois informel, sans tirets cadratin.

## Branches : la règle la plus importante

| Branche | Base | Rôle |
|---|---|---|
| `nibotek` | tag upstream **1.8.6** (version stable) | Ce qui roule en prod. Toutes nos fonctionnalités. |
| `1.9` | branche de **développement** upstream | Ne pas déployer. Sert seulement de base aux PR. |
| `pr/item-duplicate` | `1.9` upstream | PR de la duplication vers l'upstream (issue #1168). |

- **Ne jamais fusionner `1.9` (ni rien basé dessus) dans `nibotek`.** `1.9` contient des migrations Doctrine qui s'appliquent toutes seules au démarrage du conteneur et rendent le retour à 1.8 risqué. Ça a déjà causé une panne (502) le 23 novembre 2026.
- **Ne jamais utiliser « Sync fork » de GitHub sur `nibotek`** : cette branche n'existe pas en upstream, GitHub la comparerait au `1.9` de l'upstream.
- Pour suivre l'upstream : quand une nouvelle version stable est taguée (ex. 1.8.7, 1.9.0), rebaser `nibotek` sur ce tag. Les conflits sont surtout dans les fichiers de traduction (ajouts en fin de fichier).
- Une fonctionnalité destinée à l'upstream se développe sur une branche partant de `1.9`, puis est portée (cherry-pick) sur `nibotek`. **Vérifier que chaque méthode/propriété utilisée existe dans 1.8.6** : certaines n'existent qu'en 1.9 (ex. `Item::description`, qui a fait planter le CI).

## Tester

- Le CI upstream (`.github/workflows/ci.yml`) roule toute la suite PHPUnit sur MySQL, MariaDB et PostgreSQL à chaque push. C'est la référence : une modif n'est pas finie tant qu'il n'est pas vert.
- Tests fonctionnels dans `tests/App/...`, avec les factories Foundry de `tests/Factory/`. Les fichiers de test viennent de `assets/fixtures/` via `AppTestCase::createFile()`.
- En test, les uploads vont dans `/tmp` (chemins qui commencent par `/tmp`).
- Le filtre Doctrine de propriétaire cache aussi les entités des autres usagers dans les assertions de test (`Factory::assert()->count()`).
- Ajouter des tests pour chaque nouvelle fonctionnalité, sur le modèle de `tests/App/Item/ItemDuplicateTest.php`.

## Image Docker et déploiement

- `.github/workflows/fork-image.yml` construit le `Dockerfile` de prod et pousse `ghcr.io/nbourre/koillection:latest` (et `:<sha>`), amd64 seulement.
- Il se déclenche après un CI réussi sur `nibotek` (`workflow_run`), ce qui ne marche que si `nibotek` est la branche par défaut du fork. Sinon, le lancer à la main (« Run workflow »).
- Sur le VPS (`~/containers/koillection`) : `image: ghcr.io/nbourre/koillection:latest`, puis `docker compose pull && docker compose up -d`.
- `APP_SECRET` et `JWT_PASSPHRASE` doivent être définies dans le compose (obligatoires à partir de 1.9).
- Retour à l'image officielle (`koillection/koillection`) possible tant que `nibotek` n'ajoute aucune migration. **Toute migration ajoutée dans le fork doit être signalée clairement à Nicolas avant le déploiement**, avec une sauvegarde de la base au préalable.

## Conventions du code

- Traductions : fichiers XLIFF `translations/<langue>/messages+intl-icu.<langue>.xlf` (14 langues, format ICU). L'upstream les gère avec Crowdin. Ajouter chaque nouvelle clé dans toutes les langues, avec un `unit id` unique de 7 caractères.
- Actions qui modifient des données : POST avec jeton CSRF (`csrf_token()` / `isCsrfTokenValid()`), jamais un lien GET.
- Uploads : propriétés marquées `#[Upload]`, gérées par `UploadListener` et `ImageHandler` (déplacement, nom, miniatures). Pour copier un fichier existant, le recopier dans un fichier temporaire et l'envoyer comme `UploadedFile` en mode test (voir `src/Service/ItemDuplicator.php`).

## Fonctionnalités ajoutées dans le fork

- **Duplication d'item** (`src/Service/ItemDuplicator.php`, route `app_item_duplicate` dans `ItemController`, bouton dans `templates/App/Item/_content.html.twig`) : copie la collection, les tags, la visibilité, la quantité et tous les champs, et recopie physiquement les fichiers. Ne copie pas les prêts ni les items liés. Le nom devient « Nom (copie) ». PR proposée à l'upstream (issue #1168), en attente de la réponse de l'auteur.

## Idées en suspens

Intégration Numista native (champ N° Numista qui remplit tirage, composition, photos, avec cache pour respecter la limite de 2000 requêtes API/mois), vue checklist année par année par dénomination, échelle Sheldon triable, emplacement physique (classeur, capsule), valeur de la collection.
