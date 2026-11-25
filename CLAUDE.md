# CLAUDE.md (fork nbourre/koillection)

Contexte pour les sessions Claude. Lis ce fichier avant d'explorer le code : il évite de tout réexplorer.
Ce fichier est propre au fork. Il ne doit jamais apparaître dans une PR vers l'upstream.

## L'application
Koillection : app web auto-hébergée pour cataloguer des collections (ici, surtout des pièces de monnaie).
Upstream : `benjaminjonard/koillection`, branche par défaut `1.9`. Fork : `nbourre/koillection`.
Échanges upstream (issues, PR, commits) en anglais. Réponses à Nicolas en français, sans tirets cadratin.

## Règle de départ
Toujours partir de la dernière version upstream :
```
git remote add upstream https://github.com/benjaminjonard/koillection.git  # si absent
git fetch upstream 1.9
git switch -c feature/<nom> upstream/1.9
```
Une branche destinée à une PR upstream part de `upstream/1.9`, jamais d'une branche du fork qui contient ce fichier.

## Stack
- PHP 8.6, Symfony 8.1, Doctrine ORM 3, API Platform 5 (code dans `api/`, pas `src/`)
- Twig, Stimulus, Vite (`assets/vite.config.mjs`), Materialize 2.4, yarn dans `assets/`
- Docker : image FrankenPHP en mode worker (attention aux états qui fuient entre requêtes)
- BD : PostgreSQL, MySQL et MariaDB supportés tous les trois
- Traductions : `translations/<lang>/*.xlf` (ICU). Modifier seulement `en` (et `fr` si utile) ; Crowdin gère le reste

## Anatomie d'une fonctionnalité
| Couche | Emplacement |
|---|---|
| Contrôleur | `src/Controller/<Module>Controller.php` (hérite de `AbstractController` local) |
| Entité | `src/Entity/<Module>.php`, dépôt dans `src/Repository/` |
| Formulaire | `src/Form/Type/Entity/<Module>Type.php` |
| Vues | `templates/App/<Module>/` (`index`, `show`, `add`, `edit`, `_form`...) |
| JS | `assets/controllers/<nom>_controller.js` (Stimulus) |
| Services | `src/Service/`, événements dans `src/EventListener/` |
| API | `api/` (ressources, sérialiseurs, filtres Doctrine) |
| Tests | `tests/App/`, `tests/Api/`, fabriques Foundry dans `tests/Factory/` |

Migrations : une par moteur, dans `migrations/Postgresql/` ET `migrations/Mysql/` (MySQL et MariaDB partagent la même).
Le choix se fait par plateforme (`src/Doctrine/Migrations/`). Ne jamais en écrire une seule.

## Champs dynamiques (Datum)
Les champs d'un item ou d'une collection sont des `Datum` (`src/Entity/Datum.php`) typés par
`src/Enum/DatumTypeEnum.php` (text, textarea, markdown, country, date, rating, number, price, link,
list, choice-list, checkbox, image, file, sign, video, blank-line, section).
Les types sont codés en dur à plusieurs endroits. Ajouter un type demande de toucher au moins :
- `DatumTypeEnum` (constante + `TYPES` + listes associées dans le même fichier)
- `src/Form/Type/Entity/DatumType.php`, `FieldType.php`, `SearchFilterType.php`
- `templates/App/Datum/_<type>.html.twig` et `templates/App/Datum/_display_value/`
- `templates/App/Item/_form_data_block.html.twig`, `templates/App/Collection/_form_data_block.html.twig`
- `src/Twig/AppExtension.php`, `src/Service/AdvancedItemSearcher.php`, `src/Enum/AdvancedItemSearch/OperatorEnum.php`
- Scrapers (`src/Service/Scraper/`), `ImportHandler.php`, `ArraySorter.php`, `DatumRepository.php`
Vérifier avec `grep -rn "DatumTypeEnum::TYPE_" src api templates assets`.

## Commandes
Dev local : `cp docker-compose.dist.yml docker-compose.yml && docker compose up -d` (app sur le port 81).
Dans le conteneur (`docker exec -it koillection bash`, dossier `/app/public`) :
```
bin/console doctrine:migrations:migrate          # schéma
bin/console doctrine:schema:validate --skip-sync # vérifié en CI
bin/console app:translations:dump                # traductions JS
cd assets && yarn install && yarn build          # ou yarn watch
```
Tests (base de test à créer une fois) :
```
bin/console doctrine:database:create --env=test
bin/console doctrine:migrations:migrate --env=test
bin/console lexik:jwt:generate-keypair
composer test:paratest          # parallèle, rapide
composer test:phpunit -- --filter <Classe>   # ciblé
```
Lint : `./vendor/bin/php-cs-fixer fix` (règles dans `.php-cs-fixer.dist.php`).
CI (`.github/workflows/ci.yml`) : build Docker, smoke test, PHPUnit sur MySQL 8/9, MariaDB et PostgreSQL, validation du schéma.

## Conventions du mainteneur
- Discuter d'abord : ouvrir une issue `[FR] ...` (feature request) avant de coder (voir `CONTRIBUTING.md`)
- Branches `feature/...` ou `fix/...`, une PR par sujet, titre court à l'impératif en anglais
- `declare(strict_types=1);`, typage complet, php-cs-fixer appliqué avant chaque commit
- Ne pas toucher aux traductions autres que `en`, ni aux commits Crowdin ou Dependabot

## Système de plugins (en conception)
Une proposition existe : plugins = bundles Symfony dans `plugins/<Nom>/`, couche de coutures minimale
dans `src/Plugin/`. Issue upstream : https://github.com/benjaminjonard/koillection/issues/1721 .
Ne rien coder avant la réponse du mainteneur. Les futures fonctionnalités (historique de valeur,
étiquettes QR, Numista, MCP) visent ce modèle de plugin.

## Production
Serveur `nibotek` (SSH depuis le Mac mini), dossier `~/containers/koillection`. Aucune action en prod sans accord explicite.
