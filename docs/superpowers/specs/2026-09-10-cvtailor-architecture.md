# CVTailor — Architecture globale

Date : 2026-09-10
Statut : validé (référence pour tous les sous-projets)

CVTailor est une plateforme web qui adapte automatiquement le CV d'un utilisateur à une offre d'emploi fournie par URL, en optimisant mots-clés ATS, vocabulaire et compétences, puis exporte le résultat en PDF, DOCX ou texte.

## Découpage en sous-projets

Chaque sous-projet a son propre spec, plan et cycle d'implémentation, dans cet ordre :

1. **Socle** — Symfony 7.3 / PHP 8.4, Docker Compose, auth, entités, React + Turbo + Stimulus, i18n FR/EN, design system. Spec : `2026-09-10-cvtailor-foundation-design.md`.
2. **Master CV** — schéma JSON versionné, import PDF/DOCX via LLM, éditeur React, aperçu Twig, thèmes.
3. **Pipeline d'adaptation** — ingestion d'offre par URL, analyse LLM, génération du CV adapté, garde-fous, score, diff, exports PDF/DOCX/TXT.
4. **SaaS** — plans Free/Pro, quotas, Stripe Checkout, Customer Portal, webhooks.

Hors V1 (idées retenues pour la suite) : lettre de motivation générée, historique et comparaison de versions, extension navigateur, score ATS avant/après.

## 1. Architecture

### Stack

- Symfony 7.3, PHP 8.4, Doctrine ORM, MySQL 8, Redis (cache, sessions, transport Messenger), Mercure (temps réel), Gotenberg (PDF), Mailpit (mails en dev).
- Front : Webpack Encore, `symfony/ux-react` pour les îlots React (TypeScript), `symfony/ux-turbo` et Stimulus pour le reste, Tailwind CSS.
- Docker Compose : `php` (FPM), `nginx`, `worker` (`messenger:consume`, même image que `php`), `mysql`, `redis`, `mercure`, `gotenberg`, `mailpit`, `node` (watch en dev).

### Organisation du code (par domaine)

```
src/
  Auth/          inscription, login, reset mdp, vérification email
  Resume/        master CV : entité, schéma JSON, import, éditeur, rendu
  JobOffer/      ingestion d'offres : fetch, extraction, normalisation
  Tailoring/     pipeline d'adaptation : états, handlers Messenger, prompts
  Export/        PDF / DOCX / TXT
  Llm/           LlmClientInterface + adaptateurs Anthropic / OpenAI / Ollama
  Billing/       plans, quotas, Stripe
  Shared/        kernel, helpers, value objects communs
assets/
  react/         ResumeEditor, TailoringProgress, TailoringReview
  controllers/   Stimulus
templates/       Twig, dont templates/resume/themes/{modern,classic,compact}
```

Chaque domaine expose des services publics ; les autres domaines n'importent que ceux-là.

### Flux principal

1. L'utilisateur colle une URL → `POST /app/tailorings` crée un `Tailoring` en `pending`, vérifie le quota, dispatche `FetchJobOfferMessage`.
2. Le worker enchaîne `FetchJobOffer` → `AnalyzeJobOffer` → `GenerateTailoredResume`, chaque handler changeant l'état et publiant sur Mercure.
3. `/app/tailorings/{id}` affiche `TailoringProgress` (React, abonné à Mercure) ; à `done`, la vue résultat est rendue.
4. Exports générés à la demande et cachés sur disque (`var/exports`), invalidés si le CV adapté change.

Si le fetch échoue, l'état passe à `needs_manual_input` ; l'utilisateur colle le texte de l'offre et le pipeline repart à l'analyse.

## 2. Modèle de données

- **User** : email, password, locale, `emailVerifiedAt`, roles. 1-1 `Subscription`.
- **Resume** : `user`, `title`, `data` (JSON), `schemaVersion`, `language`, `sourceFile` (nullable), timestamps. Plusieurs par utilisateur.
- **JobOffer** : `url` (nullable), `urlHash` (unique, URL normalisée), `rawText`, `title`, `company`, `location`, `language`, `analysis` (JSON), `fetchStatus`, `fetchedAt`. Partagée entre utilisateurs.
- **Tailoring** : `user`, `resume`, `jobOffer`, `status` (pending, fetching, needs_manual_input, analyzing, generating, done, failed), `tailoredData` (JSON), `matchScore`, `changes` (JSON), `theme`, `errorMessage`, `llmUsage` (JSON), timestamps.
- **Subscription** : `user`, `plan` (free/pro), `stripeCustomerId`, `stripeSubscriptionId`, `status`, `currentPeriodEnd`.
- **UsageCounter** : `user`, `periodKey` (YYYY-MM), `tailoringsCount`.

### Schéma JSON du CV (`schemaVersion: 1`)

```
{ basics: {fullName, headline, email, phone, location, links: [{label, url}]},
  summary: string,
  experiences: [{id, company, role, startDate, endDate, current, location, bullets: [string], skills: [string]}],
  education: [{id, school, degree, field, startDate, endDate}],
  skills: [{name, level?, category}],
  languages: [{name, level}],
  projects: [{id, name, description, url, bullets: [string]}],
  certifications: [{name, issuer, date}] }
```

Les `id` (UUID) sur experiences, education et projects sont stables et servent au diff et aux garde-fous.

## 3. Couche LLM

```php
interface LlmClientInterface {
    public function completeJson(LlmRequest $request, array $jsonSchema): LlmResult;
}
```

- `LlmRequest` : `systemPrompt`, `userPrompt`, `model` (nullable), `maxTokens`, `temperature`.
- `LlmResult` : `data`, `inputTokens`, `outputTokens`, `provider`, `model`, `durationMs`.
- Adaptateurs : `AnthropicClient` (tool use avec `input_schema`), `OpenAiClient` (`response_format: json_schema`), `OllamaClient` (`format: json`). Sélection par `LLM_PROVIDER`, modèles par défaut dans `config/packages/llm.yaml`.
- Décorateurs (extérieur → intérieur) : `RetryingLlmClient` (2 relances sur JSON invalide, renvoie l'erreur du validateur au modèle ; relance sur 429/réseau), `LoggingLlmClient` (canal Monolog `llm`, dump des prompts dans `var/llm/` en dev), `CachingLlmClient` (Redis, analyse d'offre uniquement).
- Validation JSON Schema avec `opis/json-schema`, schémas dans `src/Llm/Schema/*.json`.
- Prompts : templates Twig `templates/prompts/*.txt.twig`, rendus par `PromptRenderer`.

## 4. Pipeline d'adaptation

Trois handlers Messenger (transport `async` Redis), idempotents, workflow Symfony `TailoringStateMachine`, publication Mercure sur `/tailorings/{id}` à chaque transition.

**FetchJobOfferHandler** (`fetching`) : réutilise une `JobOffer` déjà analysée pour le même `urlHash` ; sinon fetch HTTP (UA navigateur, timeout 15 s, 2 Mo max), extraction en cascade JSON-LD `JobPosting` → `og:*` + Readability → texte brut. Moins de 300 caractères utiles ou HTTP ≠ 200 → `needs_manual_input`.

**AnalyzeJobOfferHandler** (`analyzing`) : un appel LLM, `temperature 0`, schéma `job_analysis.json` : `title, company, location, contractType, seniority, language, hardSkills[{name, weight 1-5, required}], softSkills[{name, weight}], atsKeywords[{term, weight, variants[]}], responsibilities[], tone, cultureSignals[]`.

**GenerateTailoredResumeHandler** (`generating`) : un appel LLM, `temperature 0.3`, schéma `tailored_resume.json` : `resume (schéma CV), changes[{path, type: reworded|reordered|added|removed|emphasized, reason}], addedSkills[{name, justification, evidence}], matchScore, missingRequirements[]`. Règles : pas d'invention d'entreprise, poste, date, diplôme ; compétences ajoutées justifiées par une preuve du master ; langue de l'offre ; intégration des `atsKeywords` ; 1 à 2 pages.

**TailoringGuard** (PHP, post-génération) : rejette tout `id` absent du master, vérifie dates inchangées, marque `addedSkills` sans preuve comme `pending`. Une relance avec les violations, sinon `failed`.

Quotas : décrément à la création en transaction, remboursement si `failed` avant génération. Max 2 tailorings en cours par utilisateur. Pas de retry Messenger ; bouton « Relancer » depuis l'étape échouée.

## 5. Front

Twig + Turbo Drive partout, Turbo Frames pour listes et formulaires, Stimulus pour les petites interactions, React pour trois îlots via `symfony/ux-react`.

Pages : `/`, `/login`, `/register`, `/reset-password`, `/app` (dashboard), `/app/resumes/new`, `/app/resumes/{id}` (éditeur + aperçu live), `/app/tailorings/new`, `/app/tailorings/{id}`, `/app/account`. Locale en préfixe d'URL (`/fr/...`, `/en/...`).

Îlots React (TypeScript) :

1. `ResumeEditor` : édition par sections, dnd-kit, sauvegarde auto `PATCH /api/resumes/{id}`, événement DOM `resume:saved` qui recharge la frame d'aperçu.
2. `TailoringProgress` : `EventSource` Mercure, 3 étapes, gestion de `needs_manual_input`, `Turbo.visit` à `done`.
3. `TailoringReview` : onglets Aperçu / Modifications (changes, addedSkills à accepter ou refuser, missingRequirements) / Comparer (diff côte à côte). Modifications via `PATCH /api/tailorings/{id}`.

Endpoints `/api/*` : contrôleurs Symfony JSON, session + CSRF, pas d'API Platform en V1. i18n : `symfony/translation`, chaînes React passées en props.

## 6. Rendu et exports

`ResumeRenderer::render(array $data, string $theme): string` rend `templates/resume/themes/{theme}/resume.html.twig` avec CSS inliné orienté impression. Le même HTML sert à l'aperçu et au PDF. Thèmes : `modern`, `classic`, `compact`.

`ExporterInterface { supports(string $format): bool; export(array $data, string $theme): ExportedFile }` :

- `PdfExporter` : Gotenberg `POST /forms/chromium/convert/html`, A4.
- `DocxExporter` : PhpWord, styles nommés, structure lisible ATS.
- `TxtExporter` : texte brut structuré.

Cache `var/exports/{tailoringId}/{hash}.{ext}`, `BinaryFileResponse`, nom `Prenom-Nom-CV-Entreprise.ext`.

Import : `ResumeImporter` (`smalot/pdfparser`, PhpWord) puis LLM avec le schéma CV, en asynchrone avec Mercure.

## 7. Sécurité

- Symfony Security, form login, remember-me, vérification d'email obligatoire avant la première génération, reset password, rate limiting sur login/register/reset.
- Voters `ResumeVoter`, `TailoringVoter`. Topics Mercure privés via JWT d'abonnement signé côté serveur.
- Anti-SSRF sur le fetch : `http(s)` seulement, refus des IP privées/loopback/link-local/metadata après résolution DNS, redirections manuelles (max 3) re-vérifiées, taille et timeout bornés.
- Injection de prompt : texte de l'offre délimité et déclaré comme données ; garde-fous PHP comme défense réelle.
- Uploads : MIME via `finfo`, 5 Mo max, hors webroot, nom aléatoire.
- Suppression de compte purgeant toutes les données. Clés API en env. CSRF partout, CSP/HSTS via `nelmio/security-bundle`.

## 8. Billing et quotas

- Plans dans `config/packages/billing.yaml` via `PlanCatalog` : `free` (3 générations/mois, 1 CV, thème `classic`, PDF/TXT) et `pro` (100/mois, CV illimités, tous thèmes, DOCX).
- Stripe Checkout + Customer Portal. Webhooks `POST /webhooks/stripe` signés, idempotents par `event.id` : `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_failed`.
- `QuotaChecker::assertCanCreateTailoring(User)` avec verrou pessimiste sur `UsageCounter`.
- Dégradation vers Free en fin de période ; données existantes toujours lisibles et exportables en PDF.

## 9. Tests et qualité

- PHPUnit : garde-fous, quotas, extracteurs sur fixtures HTML réelles, validateur SSRF, exporteurs, snapshots des thèmes.
- `FakeLlmClient` pour les handlers ; adaptateurs testés avec `HttpClient` mocké ; tests live derrière `LLM_LIVE_TESTS=1`.
- `WebTestCase` : auth, CRUD CV, tailoring avec transport `in-memory`, API, voters, webhooks Stripe.
- Vitest + Testing Library sur les composants React ; Playwright pour un parcours e2e manuel.
- PHPStan niveau 8, PHP-CS-Fixer, ESLint + Prettier, `composer audit`, GitHub Actions.
- Monolog canaux `llm`, `tailoring`, `stripe` ; Sentry optionnel.
