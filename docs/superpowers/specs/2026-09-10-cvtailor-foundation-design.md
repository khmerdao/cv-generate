# CVTailor — Sous-projet 1 : Socle

Date : 2026-09-10
Statut : en revue
Référence : `2026-09-10-cvtailor-architecture.md`

## Objectif

Livrer une application Symfony fonctionnelle, dockerisée, avec authentification complète, les entités du domaine, l'intégration React/Turbo/Stimulus, l'i18n FR/EN et un design system minimal, de sorte que les sous-projets Master CV, Pipeline et SaaS n'aient plus qu'à ajouter de la logique métier.

Ce sous-projet ne contient aucun appel LLM, aucun scraping, aucun export et aucun paiement.

## Périmètre

### Inclus

1. Projet Symfony 7.3 (skeleton `webapp`), PHP 8.4, structure par domaines.
2. Docker Compose complet : `php`, `nginx`, `worker`, `mysql`, `redis`, `mercure`, `gotenberg`, `mailpit`, `node`. Gotenberg et Mercure sont démarrés mais non utilisés par le code de ce sous-projet.
3. Authentification : inscription, login, logout, remember-me, vérification d'email, reset password, rate limiting.
4. Entités et migrations : `User`, `Resume`, `JobOffer`, `Tailoring`, `Subscription`, `UsageCounter`, avec enums PHP pour `TailoringStatus`, `Plan`, `SubscriptionStatus`, `FetchStatus`.
5. Voters `ResumeVoter` et `TailoringVoter`.
6. Front : Webpack Encore, TypeScript, Tailwind, `symfony/ux-turbo`, `symfony/stimulus-bundle`, `symfony/ux-react`, un composant React `HelloIsland` de validation de la chaîne de build, deux contrôleurs Stimulus (`dropdown`, `confirm`).
7. Layouts : `base.html.twig`, `layout/marketing.html.twig` (landing, auth), `layout/app.html.twig` (sidebar, header avec utilisateur et sélecteur de langue, zone de flash messages).
8. Pages : landing `/`, `/login`, `/register`, `/verify-email`, `/reset-password/*`, `/app` (dashboard vide avec placeholders), `/app/account` (profil, changement de langue, changement de mot de passe, suppression de compte).
9. i18n : locale en préfixe d'URL (`/fr`, `/en`), détection depuis `Accept-Language` à la première visite, persistée sur `User.locale`, fichiers `translations/messages+intl-icu.{fr,en}.yaml`.
10. Messenger configuré : transport `async` sur Redis, transport `failed` en base, worker Docker, un message de démonstration `SendEmailMessage` (déjà fourni par Mailer) qui prouve le circuit.
11. Mercure configuré : hub Docker, `MercureTopicAuthorizer` qui signe un JWT d'abonnement pour `/tailorings/{id}` d'un utilisateur ; une route de test `/app/mercure-check` en dev uniquement qui publie et affiche un message.
12. Sécurité : CSRF, `nelmio/security-bundle` (CSP, HSTS, X-Frame-Options), cookies `Secure`/`SameSite=Lax`.
13. Qualité : PHPStan niveau 8, PHP-CS-Fixer, ESLint + Prettier, PHPUnit, Vitest, GitHub Actions.
14. Documentation : `README.md` (démarrage en trois commandes), `CLAUDE.md` (conventions du dépôt), `.env.example` documenté.

### Exclus (sous-projets suivants)

Éditeur de CV, import, rendu des thèmes, couche LLM, pipeline, exports, Stripe, quotas réels.

## Décisions de conception

### Structure des dossiers

```
src/
  Auth/
    Controller/  RegistrationController, SecurityController, VerifyEmailController, ResetPasswordController
    Form/        RegistrationType, ChangePasswordType
    Security/    LoginFormAuthenticator (ou form_login natif), EmailVerifier
    Entity/      User
  Resume/Entity/Resume, Resume/Security/ResumeVoter, Resume/Repository/ResumeRepository
  JobOffer/Entity/JobOffer, JobOffer/Enum/FetchStatus
  Tailoring/Entity/Tailoring, Tailoring/Enum/TailoringStatus, Tailoring/Security/TailoringVoter
  Billing/Entity/Subscription, Billing/Entity/UsageCounter, Billing/Enum/Plan, Billing/Enum/SubscriptionStatus
  Shared/
    Controller/  HomeController, DashboardController, AccountController
    Mercure/     MercureTopicAuthorizer
    Locale/      LocaleSubscriber, LocaleSwitcher
    Twig/        extensions éventuelles
  Kernel.php
```

Doctrine est configuré avec un mapping par domaine (`App\Auth\Entity`, `App\Resume\Entity`, etc.) au lieu du `App\Entity` par défaut.

### Entités

Identifiants : UUID v7 (`symfony/uid`) partout, colonnes `BINARY(16)`.

- **User** : `id`, `email` (unique), `password`, `roles` (json), `locale` (`fr`|`en`), `emailVerifiedAt` (nullable datetime), `createdAt`. Implémente `UserInterface`, `PasswordAuthenticatedUserInterface`. Relation `OneToOne` vers `Subscription` (créée avec `plan=free` à l'inscription), `OneToMany` vers `Resume`, `Tailoring`.
- **Resume** : `id`, `user`, `title`, `data` (json), `schemaVersion` (int, défaut 1), `language` (string 5), `sourceFile` (nullable string), `createdAt`, `updatedAt`. `data` est un tableau vide par défaut ; la validation contre le schéma arrive au sous-projet 2.
- **JobOffer** : `id`, `url` (nullable text), `urlHash` (nullable, unique, sha256 hex), `rawText` (text), `title`, `company`, `location`, `language` (nullables), `analysis` (json nullable), `fetchStatus` (enum), `fetchedAt` (nullable), `createdAt`.
- **Tailoring** : `id`, `user`, `resume`, `jobOffer` (nullable jusqu'au fetch), `status` (enum), `tailoredData` (json nullable), `matchScore` (smallint nullable), `changes` (json nullable), `theme` (string, défaut `classic`), `errorMessage` (nullable text), `llmUsage` (json nullable), `createdAt`, `updatedAt`, `completedAt` (nullable).
- **Subscription** : `id`, `user`, `plan` (enum), `stripeCustomerId`, `stripeSubscriptionId` (nullables), `status` (enum, défaut `active`), `currentPeriodEnd` (nullable), `updatedAt`.
- **UsageCounter** : `id`, `user`, `periodKey` (string 7), `tailoringsCount` (int), unique (`user`, `periodKey`).

Suppression : `onDelete: CASCADE` sur toutes les relations vers `User`. `Tailoring → JobOffer` en `SET NULL` (l'offre est partagée).

### Authentification

- `form_login` natif de Symfony 7 avec `LoginFormAuthenticator` uniquement si une logique custom est nécessaire ; sinon configuration YAML.
- Inscription : email + mot de passe (8 caractères min, vérification `NotCompromisedPassword`), envoi du mail de vérification via `symfonycasts/verify-email-bundle`, login automatique après inscription.
- `User.emailVerifiedAt` exposé via `User::isVerified()` ; les futures actions de génération exigeront `IS_VERIFIED` (attribut custom vérifié par un `VerifiedUserVoter`, livré ici).
- Reset password via `symfonycasts/reset-password-bundle`.
- Rate limiting : `login` (5 tentatives / 15 min par IP+email), `register` (10 / heure par IP), `reset` (3 / heure par email).
- Remember-me 30 jours, cookie `Secure`, `SameSite=Lax`.

### Locale

- Toutes les routes applicatives sous le préfixe `{_locale}` avec `requirements: fr|en`, sauf `/webhooks/*` (futur). La route de test Mercure est donc `/{_locale}/app/mercure-check`, chargée uniquement quand `kernel.environment == dev`.
- `LocaleSubscriber` (priorité haute sur `kernel.request`) : si l'URL n'a pas de locale, redirige vers `/{locale}/...` en choisissant `User.locale` si connecté, sinon `Accept-Language`, sinon `fr`.
- Sélecteur de langue dans le header : `POST /{_locale}/locale/{new}` qui met à jour `User.locale` si connecté et redirige vers la page courante dans la nouvelle locale.

### Front

- Encore avec `@symfony/webpack-encore`, entrée `app.ts`, Tailwind via PostCSS, `enableReactPreset()`, `enableTypeScriptLoader()`, `enableStimulusBridge()`.
- `symfony/ux-react` : composants dans `assets/react/controllers/`, montés par `{{ react_component('HelloIsland', {name: app.user.email}) }}`.
- Stimulus controllers en TypeScript dans `assets/controllers/`.
- Turbo Drive activé globalement ; les formulaires d'auth renvoient des statuts 422 sur erreur pour rester compatibles.
- Design system minimal en Tailwind : tokens de couleur dans `tailwind.config.js` (une couleur d'accent, une échelle de gris), composants Twig réutilisables dans `templates/components/` (`button`, `input`, `alert`, `card`) via `symfony/ux-twig-component`.
- Le rendu visuel détaillé (typographie, palette définitive) est décidé au moment de l'implémentation avec le skill `frontend-design` ; le spec impose seulement la présence des tokens et des composants.

### Docker Compose

| Service | Image | Rôle |
|---|---|---|
| php | build `docker/php/Dockerfile` (php:8.4-fpm-alpine + intl, pdo_mysql, redis, gd, zip, opcache) | application |
| worker | même image, commande `php bin/console messenger:consume async --time-limit=3600` | jobs asynchrones |
| nginx | nginx:alpine, conf `docker/nginx/default.conf` | serveur web, port 8080 |
| node | node:22-alpine, `npm run watch` | build front en dev |
| mysql | mysql:8.0 | base, volume persistant |
| redis | redis:7-alpine | cache, sessions, Messenger |
| mercure | dunglas/mercure | temps réel, port 3000, JWT dev connus |
| gotenberg | gotenberg/gotenberg:8 | PDF (utilisé au sous-projet 3) |
| mailpit | axllent/mailpit | mails de dev, UI port 8025 |

Un `Makefile` fournit `make up`, `make down`, `make sh`, `make db-reset`, `make test`, `make lint`. `compose.override.yaml` pour le dev (volumes montés, Xdebug optionnel), `compose.prod.yaml` esquissé (sans `node`, sans `mailpit`, assets buildés dans l'image).

### Configuration

`.env.example` documente : `APP_SECRET`, `DATABASE_URL`, `REDIS_URL`, `MESSENGER_TRANSPORT_DSN`, `MAILER_DSN`, `MERCURE_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_JWT_SECRET`, `GOTENBERG_URL`, `DEFAULT_LOCALE`, et déjà en placeholders commentés `LLM_PROVIDER`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `OLLAMA_URL`, `STRIPE_*` pour que les sous-projets suivants n'aient qu'à les remplir.

## Tests

- **PHPUnit** (base SQLite en mémoire pour la vitesse, MySQL dans la CI via service Docker) :
  - inscription → mail de vérification envoyé (Mailer `test` transport) → lien vérifie `emailVerifiedAt` ;
  - login OK / mot de passe faux / rate limit atteint ;
  - reset password bout en bout ;
  - `ResumeVoter` et `TailoringVoter` : propriétaire OK, autre utilisateur 403, anonyme redirigé ;
  - `LocaleSubscriber` : redirections selon utilisateur / `Accept-Language` / défaut ;
  - suppression de compte purge Resume, Tailoring, Subscription, UsageCounter ;
  - `MercureTopicAuthorizer` produit un JWT dont le claim `subscribe` ne contient que les topics de l'utilisateur ;
  - smoke test : toutes les routes GET publiques et `/app` connecté renvoient 200.
- **Vitest** : `HelloIsland` rend le nom passé en props.
- **PHPStan** niveau 8 sans baseline ; **PHP-CS-Fixer** règles `@Symfony` + `@PHP84Migration`.
- **CI GitHub Actions** : job `php` (composer install, lint, phpstan, phpunit avec MySQL service) et job `js` (npm ci, eslint, vitest, build Encore).

## Critères d'acceptation

1. `git clone` puis `make up` puis `make db-reset` donne une app accessible sur `http://localhost:8080` en moins de 5 minutes sur une machine avec Docker.
2. Un nouvel utilisateur peut s'inscrire, recevoir le mail dans Mailpit, vérifier son email, se déconnecter, se reconnecter, changer de langue, changer de mot de passe et supprimer son compte.
3. `/en/app` et `/fr/app` affichent le dashboard traduit avec le composant React monté.
4. Un message Messenger dispatché en dev est consommé par le conteneur `worker` et visible dans ses logs.
5. `/fr/app/mercure-check` en dev affiche un message reçu via Mercure dans la page sans rechargement.
6. `make lint` et `make test` passent sans erreur ; la CI est verte.

## Risques et points d'attention

- **Windows + Docker** : les volumes montés peuvent ralentir Composer et Encore ; documenter l'usage de WSL2 dans le README.
- **Mapping Doctrine par domaine** : plusieurs préfixes de namespace à déclarer dans `doctrine.yaml` ; s'assurer que `make:migration` les voit tous.
- **Locale en préfixe** : les bundles tiers (verify-email, reset-password) génèrent des URLs ; vérifier que `_locale` est bien propagé dans les mails via le contexte du routeur.
