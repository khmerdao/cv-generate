# CVTailor

CVTailor adapte automatiquement votre CV à une offre d'emploi. Vous collez l'URL de l'offre, l'application analyse les mots-clés ATS, les compétences et le vocabulaire attendus, puis génère un CV personnalisé exportable en PDF, DOCX ou texte.

Stack : Symfony 7.3 / PHP 8.4 en fullstack, îlots React via Symfony UX, Turbo et Stimulus, MySQL 8, Redis, Mercure, Gotenberg. Le tout dans Docker Compose.

## État du projet

Le projet est découpé en quatre sous-projets livrés dans cet ordre :

| # | Sous-projet | Contenu | État |
|---|---|---|---|
| 1 | Socle | Docker, auth, entités, React/Turbo/Stimulus, i18n FR/EN, Messenger, Mercure | Spec et plan validés, implémentation à venir |
| 2 | Master CV | Schéma JSON, import PDF/DOCX via LLM, éditeur React, thèmes | À spécifier |
| 3 | Pipeline d'adaptation | Ingestion d'offre, analyse LLM, génération, garde-fous, exports | À spécifier |
| 4 | SaaS | Plans Free/Pro, quotas, Stripe | À spécifier |

Documents :

- Architecture globale : `docs/superpowers/specs/2026-09-10-cvtailor-architecture.md`
- Spec du Socle : `docs/superpowers/specs/2026-09-10-cvtailor-foundation-design.md`
- Plan du Socle : `docs/superpowers/plans/2026-09-10-cvtailor-foundation.md`
- Conventions pour les contributeurs et agents : `CLAUDE.md`

## Prérequis

- Docker Desktop (Compose v2). Sous Windows, cloner le dépôt dans WSL2 pour des performances de synchronisation de fichiers acceptables.
- Aucun PHP ni Node local n'est nécessaire : tout s'exécute dans les conteneurs.

## Démarrage

```bash
cp .env.example .env
make up
make db-reset
```

| Service | URL |
|---|---|
| Application | http://localhost:8080 |
| Mailpit (mails de dev) | http://localhost:8025 |
| Hub Mercure | http://localhost:3000 |
| MySQL (depuis l'hôte) | `localhost:3307`, base `app`, utilisateur `app` / `app` |

Le conteneur `node` lance `npm run watch` automatiquement en développement. Le conteneur `worker` consomme la file Messenger.

## Commandes du quotidien

```bash
make sh          # shell dans le conteneur php
make test        # PHPUnit puis Vitest
make lint        # php-cs-fixer, phpstan (niveau 8), eslint
make db-reset    # recrée la base et rejoue les migrations
make down        # arrête la stack
```

Autres commandes utiles :

```bash
docker compose exec php php bin/console make:migration
docker compose exec php php bin/console app:ping hello      # vérifie le worker Messenger
docker compose run --rm node npm run build                  # build de production des assets
```

Pages de vérification en développement :

- `http://localhost:8080/fr/app/mercure-check` : publie et reçoit un message Mercure sans rechargement.

## Configuration

Toutes les variables sont documentées dans `.env.example`. Les principales :

| Variable | Rôle |
|---|---|
| `DATABASE_URL` | connexion MySQL |
| `REDIS_URL` | cache et sessions |
| `MESSENGER_TRANSPORT_DSN` | file asynchrone (Redis) |
| `MAILER_DSN`, `MAILER_FROM` | envoi des e-mails |
| `MERCURE_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_JWT_SECRET` | temps réel |
| `GOTENBERG_URL` | génération PDF (sous-projet 3) |
| `LLM_PROVIDER`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `OLLAMA_URL` | fournisseur LLM (sous-projet 3) |
| `STRIPE_*` | facturation (sous-projet 4) |

Ne jamais commiter `.env`.

## Architecture en bref

- Code organisé par domaine dans `src/` : `Auth`, `Billing`, `Resume`, `JobOffer`, `Tailoring`, `Shared`, puis `Llm` et `Export`.
- Toutes les routes sont préfixées par la locale (`/fr/...`, `/en/...`).
- Pipeline d'adaptation asynchrone : `fetching → analyzing → generating → done`, exécuté par le worker Messenger, suivi en temps réel via Mercure et Turbo.
- Couche LLM multi-fournisseur (Anthropic, OpenAI, Ollama) derrière une interface unique, sorties JSON validées par schéma.
- Garde-fous en PHP après génération : le LLM ne peut pas inventer d'expérience, de date ou de diplôme.
- Un seul rendu Twig par thème de CV, partagé entre l'aperçu et le PDF (Gotenberg). DOCX via PhpWord, TXT structuré.

## Tests

```bash
make test
```

PHPUnit tourne sur SQLite (schéma reconstruit au démarrage, chaque test dans une transaction annulée) ; la CI GitHub Actions rejoue la suite sur MySQL 8. Les composants React sont testés avec Vitest et Testing Library.

## Licence

À définir.
