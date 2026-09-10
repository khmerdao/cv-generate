# CVTailor — conventions du dépôt

CVTailor adapte le CV d'un utilisateur à une offre d'emploi (URL) avec un LLM, puis l'exporte en PDF, DOCX ou texte.

## Documents de référence

- Architecture globale (9 sections validées, découpage en 4 sous-projets) : `docs/superpowers/specs/2026-09-10-cvtailor-architecture.md`
- Spec du sous-projet 1 (Socle) : `docs/superpowers/specs/2026-09-10-cvtailor-foundation-design.md`
- Plan d'implémentation du Socle (15 tâches, TDD) : `docs/superpowers/plans/2026-09-10-cvtailor-foundation.md`

Lire le spec et le plan avant toute modification. Les sous-projets suivants (Master CV, Pipeline, SaaS) auront chacun leur spec et leur plan dans les mêmes dossiers.

## Stack et exécution

- Symfony 7.3, PHP 8.4, Doctrine ORM 3, MySQL 8, Redis 7, Mercure, Gotenberg, Mailpit.
- Tout tourne dans Docker Compose. PHP : `docker compose exec php ...`. Node : `docker compose run --rm node ...`. Raccourcis dans le `Makefile` (`make up`, `make db-reset`, `make test`, `make lint`).
- Ne pas utiliser le PHP 7.4 présent dans le PATH Windows. Hors Docker, PHP 8.4 est dans `C:\wamp64\bin\php\php8.4.21`.

## Organisation du code

- `src/` est organisé par domaine, pas par couche : `Auth`, `Billing`, `Resume`, `JobOffer`, `Tailoring`, `Shared` (puis `Llm`, `Export` dans les sous-projets suivants).
- Les entités vivent dans `src/<Domaine>/Entity` et sont mappées dans `config/packages/doctrine.yaml`. Ne jamais créer `src/Entity`.
- Un domaine n'importe que les services publics d'un autre domaine (repositories, services nommés), jamais ses classes internes.
- Identifiants : UUID v7 (`symfony/uid`), colonnes `BINARY(16)`.
- Relations vers `User` en `onDelete: CASCADE` ; `Tailoring.jobOffer` en `SET NULL`.

## Routes et i18n

- Toutes les routes applicatives sont préfixées par `/{_locale}` (`fr|en`). Les URL sans préfixe sont redirigées par `LocaleSubscriber`.
- Traductions dans `translations/messages+intl-icu.{fr,en}.yaml`. Toujours ajouter les deux langues dans le même commit.
- Les chaînes destinées aux composants React sont passées en props depuis Twig, sans bibliothèque i18n côté client.

## Front

- Twig + Turbo Drive/Frames + Stimulus par défaut. React (`assets/react/controllers`) uniquement pour les îlots à état riche : éditeur de CV, progression d'adaptation, revue des modifications.
- Composants Twig réutilisables dans `templates/components` (`Button`, `Input`, `Alert`, `Card`).
- Tailwind avec les tokens définis dans `tailwind.config.js` (`accent`, `ink`, `surface`). Ne pas coder de couleurs en dur.
- TypeScript partout dans `assets/`.

## Tests et qualité

- `make test` : PHPUnit (base SQLite reconstruite dans `tests/bootstrap.php`, transactions annulées par DAMA) puis Vitest.
- Les tests fonctionnels étendent `App\Tests\Support\AuthenticatedWebTestCase` (`createUser()`, `loginAs()`).
- Les tests dépendant d'un LLM utilisent `FakeLlmClient` ; les tests live sont derrière `LLM_LIVE_TESTS=1`.
- `make lint` avant chaque commit : PHP-CS-Fixer (`@Symfony` + `@PHP84Migration`), PHPStan niveau 8 sans baseline, ESLint + Prettier.
- Workflow : test qui échoue, implémentation minimale, test vert, commit. Un commit par tâche du plan.

## Règles métier à ne pas contourner

- Les retries Messenger sont désactivés volontairement ; les relances se font dans les services (client LLM).
- Le LLM ne doit jamais inventer une entreprise, un poste, une date ou un diplôme ; `TailoringGuard` rejette toute donnée absente du master CV.
- Le fetch d'URL passe par le validateur anti-SSRF ; aucun HTML distant n'est rendu, seulement extrait en texte.
- Les clés API et secrets ne vivent que dans `.env` (jamais commité) ; `.env.example` documente les variables.

## Git

- Commits en anglais, préfixés `feat|fix|chore|docs|test(scope):`.
- Terminer chaque message de commit par `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
