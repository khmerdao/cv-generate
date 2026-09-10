# CVTailor Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a dockerized Symfony 7.3 application with full authentication, domain entities, React/Turbo/Stimulus front, FR/EN i18n, Messenger and Mercure wired, so later sub-projects only add business logic.

**Architecture:** Symfony fullstack organised by domain folders (`src/Auth`, `src/Resume`, `src/JobOffer`, `src/Tailoring`, `src/Billing`, `src/Shared`) with Doctrine mapped per domain. Twig + Turbo for pages, Stimulus for small interactions, React islands via `symfony/ux-react`. Everything runs in Docker Compose (php, worker, nginx, node, mysql, redis, mercure, gotenberg, mailpit).

**Tech Stack:** PHP 8.4, Symfony 7.3, Doctrine ORM 3, MySQL 8, Redis 7, Mercure, Webpack Encore, TypeScript, React 18, Tailwind 3, Stimulus, Turbo, PHPUnit 11, PHPStan 2, PHP-CS-Fixer, Vitest, ESLint, Prettier.

**Spec:** `docs/superpowers/specs/2026-09-10-cvtailor-foundation-design.md` (and `docs/superpowers/specs/2026-09-10-cvtailor-architecture.md` for the global picture).

## Global Constraints

- PHP `>=8.4`, Symfony `7.3.*`, Node 22, MySQL 8.0, Redis 7.
- Identifiers are UUID v7 (`symfony/uid`), stored as `BINARY(16)` (Doctrine type `uuid`).
- Entities live in `App\<Domain>\Entity`; no `App\Entity` namespace.
- Every application route is prefixed with `/{_locale}` where `_locale` matches `fr|en`; default locale `fr`.
- Doctrine relations to `User` use `onDelete: CASCADE`; `Tailoring.jobOffer` uses `onDelete: SET NULL`.
- Password: minimum 8 characters, `NotCompromisedPassword` constraint.
- Rate limits: login 5 / 15 min per IP+email, register 10 / hour per IP, reset 3 / hour per email.
- Remember-me lifetime 30 days; cookies `secure: auto`, `samesite: lax`.
- PHPStan level 8, no baseline. PHP-CS-Fixer rules `@Symfony` + `@PHP84Migration`.
- Tests run on a file-based SQLite database locally (`.env.test`) and MySQL in CI.
- No LLM, scraping, export or Stripe code in this sub-project.
- Commit after every task with the trailer `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.

## Local execution notes

- Run PHP inside Docker: `docker compose exec php <cmd>`; the Makefile wraps the common ones. Host PHP 8.4 is at `C:\wamp64\bin\php\php8.4.21\php.exe` if Docker is unavailable, but Docker is the reference.
- Verify each "Expected" against the real output before ticking a step.

---

## File structure (end state)

```
compose.yaml, compose.override.yaml, compose.prod.yaml, Makefile, README.md, CLAUDE.md, .env, .env.example, .env.test
docker/php/Dockerfile, docker/php/php.ini, docker/nginx/default.conf
.github/workflows/ci.yml
phpstan.neon, .php-cs-fixer.dist.php, phpunit.xml.dist
package.json, webpack.config.js, tailwind.config.js, postcss.config.js, tsconfig.json, vitest.config.ts, .eslintrc.cjs, .prettierrc
config/packages/{doctrine,security,rate_limiter,translation,messenger,mercure,nelmio_security,framework,twig,webpack_encore,ux_react}.yaml
config/routes.yaml, config/routes/dev_mercure.yaml
src/Kernel.php
src/Auth/Entity/User.php
src/Auth/Repository/UserRepository.php
src/Auth/Form/{RegistrationType,ChangePasswordType}.php
src/Auth/Controller/{RegistrationController,SecurityController,VerifyEmailController,ResetPasswordController}.php
src/Auth/Security/{EmailVerifier,VerifiedUserVoter}.php
src/Billing/Entity/{Subscription,UsageCounter}.php
src/Billing/Enum/{Plan,SubscriptionStatus}.php
src/Resume/Entity/Resume.php, src/Resume/Repository/ResumeRepository.php, src/Resume/Security/ResumeVoter.php
src/JobOffer/Entity/JobOffer.php, src/JobOffer/Enum/FetchStatus.php
src/Tailoring/Entity/Tailoring.php, src/Tailoring/Enum/TailoringStatus.php, src/Tailoring/Security/TailoringVoter.php
src/Shared/Controller/{HomeController,DashboardController,AccountController,LocaleController,MercureCheckController}.php
src/Shared/Locale/LocaleSubscriber.php
src/Shared/Mercure/MercureTopicAuthorizer.php
src/Shared/Twig/Components/{Button,Input,Alert,Card}.php + templates/components/*.html.twig
templates/base.html.twig, templates/layout/{marketing,app}.html.twig
templates/{home,dashboard,account,security,registration,reset_password,mercure_check}/*.html.twig
translations/messages+intl-icu.{fr,en}.yaml
assets/app.ts, assets/styles/app.css, assets/bootstrap.ts, assets/controllers.json
assets/controllers/{dropdown,confirm}_controller.ts
assets/react/controllers/HelloIsland.tsx, assets/react/HelloIsland.test.tsx
migrations/Version*.php
tests/Support/AuthenticatedWebTestCase.php
tests/Auth/{RegistrationTest,LoginTest,VerifyEmailTest,ResetPasswordTest}.php
tests/Shared/{LocaleSubscriberTest,SmokeTest,AccountTest,MercureTopicAuthorizerTest,MessengerTest}.php
tests/Security/{ResumeVoterTest,TailoringVoterTest,VerifiedUserVoterTest}.php
```

---

### Task 1: Symfony skeleton and Docker Compose

**Files:**
- Create: `compose.yaml`, `compose.override.yaml`, `compose.prod.yaml`, `Makefile`, `docker/php/Dockerfile`, `docker/php/php.ini`, `docker/nginx/default.conf`, `.env.example`, `README.md`
- Create (via composer): Symfony skeleton at repo root

**Interfaces:**
- Produces: a running app at `http://localhost:8080`, `make up|down|sh|db-reset|test|lint` targets, `docker compose exec php` as the PHP runtime for every later task.

- [ ] **Step 1: Create the Symfony skeleton and move it to the repo root**

```bash
cd /c/wamp64/www/cv
docker run --rm -v "$(pwd):/app" -w /app composer:2 create-project symfony/skeleton:"7.3.*" tmp-skel --no-interaction
shopt -s dotglob && mv tmp-skel/* . && rmdir tmp-skel
```

- [ ] **Step 2: Write the PHP Dockerfile and php.ini**

`docker/php/Dockerfile`:

```dockerfile
FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache icu-dev libzip-dev libpng-dev git unzip $PHPIZE_DEPS \
 && docker-php-ext-install intl pdo_mysql zip gd opcache \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /app

FROM base AS dev
CMD ["php-fpm"]

FROM base AS prod
COPY . /app
RUN composer install --no-dev --optimize-autoloader --no-scripts && composer dump-env prod
CMD ["php-fpm"]
```

`docker/php/php.ini`:

```ini
memory_limit=512M
upload_max_filesize=10M
post_max_size=12M
date.timezone=Europe/Paris
opcache.enable=1
opcache.validate_timestamps=1
```

- [ ] **Step 3: Write the nginx config**

`docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    server_name _;
    root /app/public;
    client_max_body_size 12M;
    location / { try_files $uri /index.php$is_args$args; }
    location ~ ^/index\.php(/|$) {
        fastcgi_pass php:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }
    location ~ \.php$ { return 404; }
}
```

- [ ] **Step 4: Write the compose files**

`compose.yaml`:

```yaml
x-php-env: &php-env
  DATABASE_URL: "mysql://app:app@mysql:3306/app?serverVersion=8.0&charset=utf8mb4"
  REDIS_URL: "redis://redis:6379"
  MESSENGER_TRANSPORT_DSN: "redis://redis:6379/messages"
  MAILER_DSN: "smtp://mailpit:1025"
  MERCURE_URL: "http://mercure/.well-known/mercure"
  MERCURE_PUBLIC_URL: "http://localhost:3000/.well-known/mercure"
  MERCURE_JWT_SECRET: "!ChangeThisMercureHubJWTSecretKey!"
  GOTENBERG_URL: "http://gotenberg:3000"

services:
  php:
    build: { context: ., dockerfile: docker/php/Dockerfile, target: dev }
    volumes: [ ".:/app" ]
    depends_on: [ mysql, redis ]
    environment: *php-env

  worker:
    build: { context: ., dockerfile: docker/php/Dockerfile, target: dev }
    command: ["php", "bin/console", "messenger:consume", "async", "--time-limit=3600", "-vv"]
    volumes: [ ".:/app" ]
    depends_on: [ php ]
    environment: *php-env
    restart: unless-stopped

  nginx:
    image: nginx:alpine
    ports: [ "8080:80" ]
    volumes:
      - ".:/app:ro"
      - "./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro"
    depends_on: [ php ]

  mysql:
    image: mysql:8.0
    environment: { MYSQL_ROOT_PASSWORD: root, MYSQL_DATABASE: app, MYSQL_USER: app, MYSQL_PASSWORD: app }
    volumes: [ "mysql_data:/var/lib/mysql" ]
    ports: [ "3307:3306" ]

  redis:
    image: redis:7-alpine

  mercure:
    image: dunglas/mercure
    environment:
      SERVER_NAME: ":80"
      MERCURE_PUBLISHER_JWT_KEY: "!ChangeThisMercureHubJWTSecretKey!"
      MERCURE_SUBSCRIBER_JWT_KEY: "!ChangeThisMercureHubJWTSecretKey!"
      MERCURE_EXTRA_DIRECTIVES: "cors_origins http://localhost:8080"
    ports: [ "3000:80" ]

  gotenberg:
    image: gotenberg/gotenberg:8

  mailpit:
    image: axllent/mailpit
    ports: [ "8025:8025" ]

volumes:
  mysql_data:
```

`compose.override.yaml` (dev only, picked up automatically):

```yaml
services:
  node:
    image: node:22-alpine
    working_dir: /app
    command: sh -c "npm install && npm run watch"
    volumes: [ ".:/app" ]
```

`compose.prod.yaml`:

```yaml
services:
  php:
    build: { target: prod }
    volumes: []
  worker:
    build: { target: prod }
    volumes: []
```

- [ ] **Step 5: Write the Makefile** (tabs, not spaces, for recipe lines)

```makefile
.PHONY: up down sh db-reset test lint
up:
	docker compose up -d --build
down:
	docker compose down
sh:
	docker compose exec php sh
db-reset:
	docker compose exec php php bin/console doctrine:database:drop --force --if-exists
	docker compose exec php php bin/console doctrine:database:create
	docker compose exec php php bin/console doctrine:migrations:migrate -n
test:
	docker compose exec php php bin/phpunit
	docker compose run --rm node npx vitest run
lint:
	docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
	docker compose exec php vendor/bin/phpstan analyse
	docker compose run --rm node npx eslint assets --ext .ts,.tsx
```

- [ ] **Step 6: Write `.env.example`, `.env` and the README**

`.env.example`:

```dotenv
APP_ENV=dev
APP_SECRET=change-me
DEFAULT_LOCALE=fr
DATABASE_URL="mysql://app:app@mysql:3306/app?serverVersion=8.0&charset=utf8mb4"
REDIS_URL=redis://redis:6379
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
MAILER_DSN=smtp://mailpit:1025
MAILER_FROM="CVTailor <no-reply@cvtailor.local>"
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"
GOTENBERG_URL=http://gotenberg:3000
# --- Filled in by later sub-projects ---
# LLM_PROVIDER=anthropic   # anthropic | openai | ollama
# ANTHROPIC_API_KEY=
# OPENAI_API_KEY=
# OLLAMA_URL=http://host.docker.internal:11434
# STRIPE_SECRET_KEY=
# STRIPE_WEBHOOK_SECRET=
# STRIPE_PRICE_PRO_MONTHLY=
```

Copy it over the Flex-generated `.env` (`cp .env.example .env`) and keep the `APP_SECRET` Flex generated.

`README.md`:

```markdown
# CVTailor

Adapts your CV to a job offer URL. Symfony 7.3 + React islands.

## Start

    cp .env.example .env
    make up
    make db-reset

App: http://localhost:8080 · Mailpit: http://localhost:8025 · Mercure: http://localhost:3000

On Windows, clone inside WSL2 for acceptable file-sync performance.

## Daily commands

    make sh        # shell in the php container
    make test      # PHPUnit + Vitest
    make lint      # php-cs-fixer, phpstan, eslint
```

- [ ] **Step 7: Build and verify**

Run: `make up && sleep 10 && curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/`
Expected: `404` (Symfony "no route" page; proves nginx + PHP-FPM).

Run: `docker compose exec php php -v | head -1`
Expected: `PHP 8.4.x`

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore: symfony 7.3 skeleton with docker compose stack

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: Quality tooling, test environment and CI

**Files:**
- Create: `phpstan.neon`, `.php-cs-fixer.dist.php`, `.env.test`, `tests/object-manager.php`, `.github/workflows/ci.yml`, `tests/Shared/SmokeTest.php`
- Modify: `phpunit.xml.dist`, `tests/bootstrap.php`

**Interfaces:**
- Produces: `vendor/bin/phpstan analyse`, `vendor/bin/php-cs-fixer fix`, `bin/phpunit` runnable; a smoke test that lists the public URLs later tasks must serve.

- [ ] **Step 1: Install packages**

```bash
docker compose exec php composer require symfony/orm-pack symfony/uid symfony/monolog-bundle --no-interaction
docker compose exec php composer require --dev phpunit/phpunit:^11 symfony/test-pack symfony/maker-bundle \
  phpstan/phpstan:^2 phpstan/phpstan-symfony phpstan/phpstan-doctrine phpstan/extension-installer \
  friendsofphp/php-cs-fixer dama/doctrine-test-bundle --no-interaction
```

If Composer asks to allow the `phpstan/extension-installer` plugin, answer yes (or pre-set it in `composer.json` under `config.allow-plugins`).

- [ ] **Step 2: Configure PHPStan and PHP-CS-Fixer**

`phpstan.neon`:

```neon
parameters:
    level: 8
    paths: [ src, tests ]
    symfony:
        containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
    doctrine:
        objectManagerLoader: tests/object-manager.php
```

`tests/object-manager.php`:

```php
<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';
(new Dotenv())->bootEnv(__DIR__.'/../.env');
$kernel = new Kernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
```

`.php-cs-fixer.dist.php`:

```php
<?php

$finder = (new PhpCsFixer\Finder())->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/migrations']);

return (new PhpCsFixer\Config())
    ->setRules(['@Symfony' => true, '@PHP84Migration' => true, 'declare_strict_types' => true])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
```

- [ ] **Step 3: Configure the test environment**

`.env.test`:

```dotenv
KERNEL_CLASS='App\Kernel'
APP_SECRET='$ecretf0rt3st'
DEFAULT_LOCALE=fr
DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db"
MAILER_DSN=null://null
MAILER_FROM="CVTailor <no-reply@cvtailor.local>"
MESSENGER_TRANSPORT_DSN=in-memory://
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"
```

Append to `tests/bootstrap.php` after the Dotenv boot so the schema always matches the entities:

```php
passthru(sprintf('php "%s/../bin/console" doctrine:schema:update --force --complete --env=test --quiet', __DIR__));
```

In `phpunit.xml.dist`, register the DAMA extension (each test runs in a rolled-back transaction):

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
</extensions>
```

- [ ] **Step 4: Write the smoke test**

`tests/Shared/SmokeTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    #[DataProvider('publicUrls')]
    public function testPublicPagesRespond(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
    }

    /** @return iterable<array{string}> */
    public static function publicUrls(): iterable
    {
        yield ['/fr'];
        yield ['/en'];
        yield ['/fr/login'];
        yield ['/fr/register'];
        yield ['/fr/reset-password'];
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Shared/SmokeTest.php`
Expected: FAIL with 404 on each URL. This test stays red until Task 10 and is the acceptance gate for the pages.

- [ ] **Step 5: Add the CI workflow**

`.github/workflows/ci.yml`:

```yaml
name: CI
on: [push, pull_request]
jobs:
  php:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env: { MYSQL_ROOT_PASSWORD: root, MYSQL_DATABASE: app_test }
        ports: [ "3306:3306" ]
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=5
    env:
      DATABASE_URL: "mysql://root:root@127.0.0.1:3306/app_test?serverVersion=8.0&charset=utf8mb4"
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: "8.4", extensions: "intl, pdo_mysql, redis, gd, zip" }
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/php-cs-fixer fix --dry-run --diff
      - run: php bin/console cache:warmup --env=dev && vendor/bin/phpstan analyse
      - run: php bin/phpunit
  js:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 22 }
      - run: npm ci
      - run: npx eslint assets --ext .ts,.tsx
      - run: npx vitest run
      - run: npm run build
```

The `js` job fails until Task 9 creates `package.json`; that is expected.

- [ ] **Step 6: Run lint and commit**

Run: `docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php php bin/console cache:warmup && docker compose exec php vendor/bin/phpstan analyse`
Expected: `[OK] No errors`

```bash
git add -A
git commit -m "chore: phpstan, php-cs-fixer, phpunit, ci workflow

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: Doctrine domain mapping, User, Subscription, UsageCounter

**Files:**
- Modify: `config/packages/doctrine.yaml`
- Create: `src/Auth/Entity/User.php`, `src/Auth/Repository/UserRepository.php`, `src/Billing/Enum/Plan.php`, `src/Billing/Enum/SubscriptionStatus.php`, `src/Billing/Entity/Subscription.php`, `src/Billing/Entity/UsageCounter.php`, `migrations/Version<timestamp>.php`
- Test: `tests/Auth/UserEntityTest.php`

**Interfaces:**
- Produces: `App\Auth\Entity\User` with `getId(): Uuid`, `getEmail()`, `setEmail()`, `getPassword()`, `setPassword()`, `getLocale(): string`, `setLocale(string)`, `isVerified(): bool`, `markVerified()`, `getSubscription(): Subscription`, `getResumes()`, `getTailorings()`; constructor `new User(string $email, string $locale = 'fr')` which also creates a free `Subscription`. `App\Billing\Enum\Plan::Free|Pro`, `SubscriptionStatus::Active|PastDue|Canceled`.

- [ ] **Step 1: Map Doctrine per domain**

Replace the `mappings` block in `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        profiling_collect_backtrace: '%kernel.debug%'
        use_savepoints: true
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        validate_xml_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        identity_generation_preferences:
            Doctrine\DBAL\Platforms\PostgreSQLPlatform: identity
        auto_mapping: false
        mappings:
            Auth:      { type: attribute, is_bundle: false, dir: '%kernel.project_dir%/src/Auth/Entity',      prefix: 'App\Auth\Entity' }
            Billing:   { type: attribute, is_bundle: false, dir: '%kernel.project_dir%/src/Billing/Entity',   prefix: 'App\Billing\Entity' }
            Resume:    { type: attribute, is_bundle: false, dir: '%kernel.project_dir%/src/Resume/Entity',    prefix: 'App\Resume\Entity' }
            JobOffer:  { type: attribute, is_bundle: false, dir: '%kernel.project_dir%/src/JobOffer/Entity',  prefix: 'App\JobOffer\Entity' }
            Tailoring: { type: attribute, is_bundle: false, dir: '%kernel.project_dir%/src/Tailoring/Entity', prefix: 'App\Tailoring\Entity' }
        controller_resolver:
            auto_mapping: false

when@test:
    doctrine:
        dbal:
            dbname_suffix: '_test%env(default::TEST_TOKEN)%'

when@prod:
    doctrine:
        orm:
            auto_generate_proxy_classes: false
            proxy_dir: '%kernel.build_dir%/doctrine/orm/Proxies'
            query_cache_driver: { type: pool, pool: doctrine.system_cache_pool }
            result_cache_driver: { type: pool, pool: doctrine.result_cache_pool }
    framework:
        cache:
            pools:
                doctrine.result_cache_pool: { adapter: cache.app }
                doctrine.system_cache_pool: { adapter: cache.system }
```

Create the empty directories `src/Resume/Entity`, `src/JobOffer/Entity`, `src/Tailoring/Entity` with a `.gitkeep` so the mapping does not fail before Task 4.

- [ ] **Step 2: Write the failing entity test**

`tests/Auth/UserEntityTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Entity\User;
use App\Billing\Enum\Plan;
use PHPUnit\Framework\TestCase;

final class UserEntityTest extends TestCase
{
    public function testNewUserIsUnverifiedWithFreeSubscription(): void
    {
        $user = new User('jane@example.com', 'en');

        self::assertFalse($user->isVerified());
        self::assertSame('en', $user->getLocale());
        self::assertSame(Plan::Free, $user->getSubscription()->getPlan());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame($user, $user->getSubscription()->getUser());
    }

    public function testMarkVerifiedSetsTimestamp(): void
    {
        $user = new User('jane@example.com');
        $user->markVerified();
        self::assertTrue($user->isVerified());
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Auth/UserEntityTest.php`
Expected: FAIL, class `App\Auth\Entity\User` not found.

- [ ] **Step 3: Write the enums**

`src/Billing/Enum/Plan.php`:

```php
<?php

declare(strict_types=1);

namespace App\Billing\Enum;

enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';
}
```

`src/Billing/Enum/SubscriptionStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Billing\Enum;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
}
```

- [ ] **Step 4: Write User**

`src/Auth/Entity/User.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use App\Auth\Repository\UserRepository;
use App\Billing\Entity\Subscription;
use App\Resume\Entity\Resume;
use App\Tailoring\Entity\Tailoring;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column]
    private string $password = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Subscription::class, cascade: ['persist', 'remove'])]
    private Subscription $subscription;

    /** @var Collection<int, Resume> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Resume::class)]
    private Collection $resumes;

    /** @var Collection<int, Tailoring> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Tailoring::class)]
    private Collection $tailorings;

    public function __construct(string $email, string $locale = 'fr')
    {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->locale = $locale;
        $this->createdAt = new \DateTimeImmutable();
        $this->subscription = new Subscription($this);
        $this->resumes = new ArrayCollection();
        $this->tailorings = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $hashedPassword): void { $this->password = $hashedPassword; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): void { $this->locale = $locale; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getSubscription(): Subscription { return $this->subscription; }

    /** @return Collection<int, Resume> */
    public function getResumes(): Collection { return $this->resumes; }

    /** @return Collection<int, Tailoring> */
    public function getTailorings(): Collection { return $this->tailorings; }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void { $this->roles = $roles; }

    public function isVerified(): bool { return null !== $this->emailVerifiedAt; }
    public function markVerified(): void { $this->emailVerifiedAt = new \DateTimeImmutable(); }
    public function getEmailVerifiedAt(): ?\DateTimeImmutable { return $this->emailVerifiedAt; }

    public function eraseCredentials(): void {}
}
```

Let PHP-CS-Fixer expand the one-line methods; the content matters, not the layout.

`src/Auth/Repository/UserRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower($email)]);
    }
}
```

- [ ] **Step 5: Write Subscription and UsageCounter**

`src/Billing/Entity/Subscription.php`:

```php
<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Auth\Entity\User;
use App\Billing\Enum\Plan;
use App\Billing\Enum\SubscriptionStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: 'subscription', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(enumType: Plan::class)]
    private Plan $plan = Plan::Free;

    #[ORM\Column(enumType: SubscriptionStatus::class)]
    private SubscriptionStatus $status = SubscriptionStatus::Active;

    #[ORM\Column(nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEnd = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPlan(): Plan { return $this->plan; }
    public function getStatus(): SubscriptionStatus { return $this->status; }
    public function getStripeCustomerId(): ?string { return $this->stripeCustomerId; }
    public function getStripeSubscriptionId(): ?string { return $this->stripeSubscriptionId; }
    public function getCurrentPeriodEnd(): ?\DateTimeImmutable { return $this->currentPeriodEnd; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function update(Plan $plan, SubscriptionStatus $status, ?string $stripeCustomerId, ?string $stripeSubscriptionId, ?\DateTimeImmutable $currentPeriodEnd): void
    {
        $this->plan = $plan;
        $this->status = $status;
        $this->stripeCustomerId = $stripeCustomerId;
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        $this->currentPeriodEnd = $currentPeriodEnd;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

`src/Billing/Entity/UsageCounter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Auth\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'usage_counters')]
#[ORM\UniqueConstraint(name: 'uniq_usage_user_period', fields: ['user', 'periodKey'])]
class UsageCounter
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 7)]
    private string $periodKey;

    #[ORM\Column]
    private int $tailoringsCount = 0;

    public function __construct(User $user, string $periodKey)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->periodKey = $periodKey;
    }

    public static function periodKeyFor(\DateTimeInterface $date): string
    {
        return $date->format('Y-m');
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPeriodKey(): string { return $this->periodKey; }
    public function getTailoringsCount(): int { return $this->tailoringsCount; }
    public function increment(): void { ++$this->tailoringsCount; }
    public function decrement(): void { $this->tailoringsCount = max(0, $this->tailoringsCount - 1); }
}
```

- [ ] **Step 6: Run the entity test**

Run: `docker compose exec php php bin/phpunit tests/Auth/UserEntityTest.php`
Expected: 2 tests PASS.

- [ ] **Step 7: Generate the migration only after Task 4** (the User entity references `Resume` and `Tailoring`, which do not exist yet). Confirm mapping compiles for what exists:

Run: `docker compose exec php php bin/console doctrine:mapping:info`
Expected: lists `App\Auth\Entity\User`, `App\Billing\Entity\Subscription`, `App\Billing\Entity\UsageCounter` (errors about `Resume`/`Tailoring` are expected until Task 4; if the command refuses to run, skip it).

- [ ] **Step 8: Commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix
git add -A
git commit -m "feat(auth,billing): user, subscription and usage counter entities with domain mapping

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4: Resume, JobOffer, Tailoring entities and first migration

**Files:**
- Create: `src/Resume/Entity/Resume.php`, `src/Resume/Repository/ResumeRepository.php`, `src/JobOffer/Enum/FetchStatus.php`, `src/JobOffer/Entity/JobOffer.php`, `src/Tailoring/Enum/TailoringStatus.php`, `src/Tailoring/Entity/Tailoring.php`, `migrations/Version<timestamp>.php`
- Test: `tests/Tailoring/TailoringEntityTest.php`

**Interfaces:**
- Produces: `Resume::__construct(User $user, string $title, string $language = 'fr')`, `getUser()`, `getTitle()`, `getData(): array`, `setData(array)`, `getSchemaVersion()`, `getLanguage()`; `JobOffer::__construct(?string $url, string $rawText)`, static `JobOffer::hashUrl(string): string`, `getAnalysis()`, `setAnalysis(array)`, `getFetchStatus()`; `Tailoring::__construct(User $user, Resume $resume, string $theme = 'classic')`, `getStatus(): TailoringStatus`, `transitionTo(TailoringStatus)`, `getJobOffer()`, `setJobOffer()`, `fail(string $message)`; `TailoringStatus` cases `Pending, Fetching, NeedsManualInput, Analyzing, Generating, Done, Failed` with `isTerminal(): bool`.

- [ ] **Step 1: Write the failing test**

`tests/Tailoring/TailoringEntityTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Tailoring;

use App\Auth\Entity\User;
use App\JobOffer\Entity\JobOffer;
use App\Resume\Entity\Resume;
use App\Tailoring\Entity\Tailoring;
use App\Tailoring\Enum\TailoringStatus;
use PHPUnit\Framework\TestCase;

final class TailoringEntityTest extends TestCase
{
    public function testNewTailoringIsPendingWithClassicTheme(): void
    {
        $user = new User('jane@example.com');
        $resume = new Resume($user, 'Dev profile');
        $tailoring = new Tailoring($user, $resume);

        self::assertSame(TailoringStatus::Pending, $tailoring->getStatus());
        self::assertSame('classic', $tailoring->getTheme());
        self::assertNull($tailoring->getJobOffer());
        self::assertFalse($tailoring->getStatus()->isTerminal());
    }

    public function testFailStoresMessageAndIsTerminal(): void
    {
        $user = new User('jane@example.com');
        $tailoring = new Tailoring($user, new Resume($user, 'x'));
        $tailoring->fail('Boom');

        self::assertSame(TailoringStatus::Failed, $tailoring->getStatus());
        self::assertSame('Boom', $tailoring->getErrorMessage());
        self::assertTrue($tailoring->getStatus()->isTerminal());
    }

    public function testJobOfferUrlHashIsNormalised(): void
    {
        self::assertSame(JobOffer::hashUrl('HTTPS://Example.com/jobs/1/?utm_source=x'), JobOffer::hashUrl('https://example.com/jobs/1'));
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Tailoring/TailoringEntityTest.php`
Expected: FAIL, class `Resume` not found.

- [ ] **Step 2: Write Resume**

`src/Resume/Entity/Resume.php`:

```php
<?php

declare(strict_types=1);

namespace App\Resume\Entity;

use App\Auth\Entity\User;
use App\Resume\Repository\ResumeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ResumeRepository::class)]
#[ORM\Table(name: 'resumes')]
class Resume
{
    public const int CURRENT_SCHEMA_VERSION = 1;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'resumes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 120)]
    private string $title;

    /** @var array<string, mixed> */
    #[ORM\Column]
    private array $data = [];

    #[ORM\Column]
    private int $schemaVersion = self::CURRENT_SCHEMA_VERSION;

    #[ORM\Column(length: 5)]
    private string $language;

    #[ORM\Column(nullable: true)]
    private ?string $sourceFile = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, string $title, string $language = 'fr')
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->title = $title;
        $this->language = $language;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }

    /** @return array<string, mixed> */
    public function getData(): array { return $this->data; }

    /** @param array<string, mixed> $data */
    public function setData(array $data): void { $this->data = $data; $this->touch(); }

    public function getSchemaVersion(): int { return $this->schemaVersion; }
    public function getLanguage(): string { return $this->language; }
    public function setLanguage(string $language): void { $this->language = $language; $this->touch(); }
    public function getSourceFile(): ?string { return $this->sourceFile; }
    public function setSourceFile(?string $sourceFile): void { $this->sourceFile = $sourceFile; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
```

`src/Resume/Repository/ResumeRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Resume\Repository;

use App\Auth\Entity\User;
use App\Resume\Entity\Resume;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Resume> */
final class ResumeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resume::class);
    }

    /** @return list<Resume> */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['updatedAt' => 'DESC']);
    }
}
```

- [ ] **Step 3: Write JobOffer and its enum**

`src/JobOffer/Enum/FetchStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\JobOffer\Enum;

enum FetchStatus: string
{
    case Pending = 'pending';
    case Fetched = 'fetched';
    case Manual = 'manual';
    case Failed = 'failed';
}
```

`src/JobOffer/Entity/JobOffer.php`:

```php
<?php

declare(strict_types=1);

namespace App\JobOffer\Entity;

use App\JobOffer\Enum\FetchStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'job_offers')]
#[ORM\UniqueConstraint(name: 'uniq_job_offers_url_hash', fields: ['urlHash'])]
class JobOffer
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $url;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $urlHash;

    #[ORM\Column(type: 'text')]
    private string $rawText;

    #[ORM\Column(nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?string $company = null;

    #[ORM\Column(nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $language = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $analysis = null;

    #[ORM\Column(enumType: FetchStatus::class)]
    private FetchStatus $fetchStatus = FetchStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $fetchedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(?string $url, string $rawText = '')
    {
        $this->id = Uuid::v7();
        $this->url = $url;
        $this->urlHash = null === $url ? null : self::hashUrl($url);
        $this->rawText = $rawText;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Lower-cases scheme and host, strips fragment, trailing slash and utm_* params. */
    public static function hashUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (false === $parts || !isset($parts['host'])) {
            return hash('sha256', trim($url));
        }
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, static fn (string $k): bool => !str_starts_with($k, 'utm_'), \ARRAY_FILTER_USE_KEY);
        ksort($query);
        $normalised = strtolower($parts['scheme'] ?? 'https').'://'.strtolower($parts['host'])
            .rtrim($parts['path'] ?? '/', '/')
            .([] === $query ? '' : '?'.http_build_query($query));

        return hash('sha256', $normalised);
    }

    public function getId(): Uuid { return $this->id; }
    public function getUrl(): ?string { return $this->url; }
    public function getUrlHash(): ?string { return $this->urlHash; }
    public function getRawText(): string { return $this->rawText; }
    public function setRawText(string $rawText): void { $this->rawText = $rawText; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): void { $this->title = $title; }
    public function getCompany(): ?string { return $this->company; }
    public function setCompany(?string $company): void { $this->company = $company; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): void { $this->location = $location; }
    public function getLanguage(): ?string { return $this->language; }
    public function setLanguage(?string $language): void { $this->language = $language; }

    /** @return array<string, mixed>|null */
    public function getAnalysis(): ?array { return $this->analysis; }

    /** @param array<string, mixed> $analysis */
    public function setAnalysis(array $analysis): void { $this->analysis = $analysis; }

    public function getFetchStatus(): FetchStatus { return $this->fetchStatus; }
    public function setFetchStatus(FetchStatus $status): void
    {
        $this->fetchStatus = $status;
        if (FetchStatus::Fetched === $status || FetchStatus::Manual === $status) {
            $this->fetchedAt = new \DateTimeImmutable();
        }
    }
    public function getFetchedAt(): ?\DateTimeImmutable { return $this->fetchedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
```

- [ ] **Step 4: Write Tailoring and its enum**

`src/Tailoring/Enum/TailoringStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tailoring\Enum;

enum TailoringStatus: string
{
    case Pending = 'pending';
    case Fetching = 'fetching';
    case NeedsManualInput = 'needs_manual_input';
    case Analyzing = 'analyzing';
    case Generating = 'generating';
    case Done = 'done';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return self::Done === $this || self::Failed === $this;
    }
}
```

`src/Tailoring/Entity/Tailoring.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tailoring\Entity;

use App\Auth\Entity\User;
use App\JobOffer\Entity\JobOffer;
use App\Resume\Entity\Resume;
use App\Tailoring\Enum\TailoringStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tailorings')]
class Tailoring
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tailorings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Resume::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Resume $resume;

    #[ORM\ManyToOne(targetEntity: JobOffer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JobOffer $jobOffer = null;

    #[ORM\Column(enumType: TailoringStatus::class)]
    private TailoringStatus $status = TailoringStatus::Pending;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $tailoredData = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $matchScore = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $changes = null;

    #[ORM\Column(length: 30)]
    private string $theme;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $llmUsage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(User $user, Resume $resume, string $theme = 'classic')
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->resume = $resume;
        $this->theme = $theme;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getResume(): Resume { return $this->resume; }
    public function getJobOffer(): ?JobOffer { return $this->jobOffer; }
    public function setJobOffer(?JobOffer $jobOffer): void { $this->jobOffer = $jobOffer; $this->touch(); }
    public function getStatus(): TailoringStatus { return $this->status; }
    public function getTheme(): string { return $this->theme; }
    public function setTheme(string $theme): void { $this->theme = $theme; $this->touch(); }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function getMatchScore(): ?int { return $this->matchScore; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }

    /** @return array<string, mixed>|null */
    public function getTailoredData(): ?array { return $this->tailoredData; }

    /** @return array<string, mixed>|null */
    public function getChanges(): ?array { return $this->changes; }

    /** @return array<string, mixed>|null */
    public function getLlmUsage(): ?array { return $this->llmUsage; }

    public function transitionTo(TailoringStatus $status): void
    {
        $this->status = $status;
        $this->touch();
        if (TailoringStatus::Done === $status) {
            $this->completedAt = new \DateTimeImmutable();
        }
    }

    public function fail(string $message): void
    {
        $this->errorMessage = $message;
        $this->transitionTo(TailoringStatus::Failed);
    }

    /**
     * @param array<string, mixed> $tailoredData
     * @param array<string, mixed> $changes
     * @param array<string, mixed> $llmUsage
     */
    public function complete(array $tailoredData, array $changes, int $matchScore, array $llmUsage): void
    {
        $this->tailoredData = $tailoredData;
        $this->changes = $changes;
        $this->matchScore = max(0, min(100, $matchScore));
        $this->llmUsage = $llmUsage;
        $this->errorMessage = null;
        $this->transitionTo(TailoringStatus::Done);
    }

    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
```

Remove the `.gitkeep` files created in Task 3.

- [ ] **Step 5: Run the test**

Run: `docker compose exec php php bin/phpunit tests/Tailoring/TailoringEntityTest.php`
Expected: 3 tests PASS.

- [ ] **Step 6: Validate the mapping and generate the migration**

Run: `docker compose exec php php bin/console doctrine:schema:validate --skip-sync`
Expected: `[OK] The mapping files are correct.`

Run: `docker compose exec php php bin/console doctrine:database:create --if-not-exists && docker compose exec php php bin/console make:migration --no-interaction && docker compose exec php php bin/console doctrine:migrations:migrate -n`
Expected: one migration created and applied; `doctrine:schema:validate` now prints `[OK] The database schema is in sync with the mapping files.`

- [ ] **Step 7: Commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix
git add -A
git commit -m "feat: resume, job offer and tailoring entities with initial migration

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5: Registration, login, logout, remember-me, rate limiting

**Files:**
- Modify: `config/packages/security.yaml`, `config/routes.yaml`
- Create: `config/packages/rate_limiter.yaml`, `src/Auth/Form/RegistrationType.php`, `src/Auth/Controller/RegistrationController.php`, `src/Auth/Controller/SecurityController.php`, `templates/registration/register.html.twig`, `templates/security/login.html.twig`, `tests/Support/AuthenticatedWebTestCase.php`, `tests/Auth/RegistrationTest.php`, `tests/Auth/LoginTest.php`

**Interfaces:**
- Consumes: `User`, `UserRepository` from Task 3.
- Produces: routes `app_register` (`/{_locale}/register`), `app_login` (`/{_locale}/login`), `app_logout` (`/{_locale}/logout`), `app_dashboard` placeholder (`/{_locale}/app`, real page in Task 10). `AuthenticatedWebTestCase::createUser(string $email = 'jane@example.com', string $password = 'Password-123!', bool $verified = true): User` and `loginAs(KernelBrowser $client, User $user): void` for every later functional test.

Templates in this task are plain HTML; Task 10 restyles them onto the layouts. Routes are already declared with the locale prefix so Task 8 does not need to move them.

- [ ] **Step 1: Install security packages**

```bash
docker compose exec php composer require symfony/security-bundle symfony/form symfony/validator symfony/rate-limiter symfony/twig-bundle symfony/mailer --no-interaction
```

- [ ] **Step 2: Declare the locale-prefixed route import**

`config/routes.yaml`:

```yaml
controllers:
    resource:
        path: ../src/
        namespace: App
    type: attribute
    prefix: /{_locale}
    requirements:
        _locale: fr|en
    defaults:
        _locale: '%env(DEFAULT_LOCALE)%'
```

Exclusions (webhooks) come later when needed; every controller in `src/` gets the prefix.

- [ ] **Step 3: Configure security and rate limiters**

`config/packages/security.yaml`:

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
    providers:
        app_user_provider:
            entity: { class: App\Auth\Entity\User, property: email }
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        main:
            lazy: true
            provider: app_user_provider
            form_login:
                login_path: app_login
                check_path: app_login
                enable_csrf: true
                default_target_path: app_dashboard
            logout:
                path: app_logout
                target: app_home
            remember_me:
                secret: '%kernel.secret%'
                lifetime: 2592000
                samesite: lax
                secure: auto
            login_throttling:
                limiter: login
    access_control:
        - { path: ^/(fr|en)/app, roles: ROLE_USER }

when@test:
    security:
        password_hashers:
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: auto
                cost: 4
                time_cost: 3
                memory_cost: 10
```

`config/packages/rate_limiter.yaml`:

```yaml
framework:
    rate_limiter:
        login:
            policy: sliding_window
            limit: 5
            interval: '15 minutes'
        register:
            policy: sliding_window
            limit: 10
            interval: '1 hour'
        reset_password:
            policy: sliding_window
            limit: 3
            interval: '1 hour'
```

The `login` limiter is keyed by IP+username automatically by `login_throttling`. `register` and `reset_password` are applied manually in their controllers.

- [ ] **Step 4: Write the test helper and the failing tests**

`tests/Support/AuthenticatedWebTestCase.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    public const string PASSWORD = 'Password-123!';

    protected function createUser(string $email = 'jane@example.com', string $password = self::PASSWORD, bool $verified = true, string $locale = 'fr'): User
    {
        $container = static::getContainer();
        $user = new User($email, $locale);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));
        if ($verified) {
            $user->markVerified();
        }
        $em = $container->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function loginAs(KernelBrowser $client, User $user): void
    {
        $client->loginUser($user);
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
```

`tests/Auth/RegistrationTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Entity\User;
use App\Tests\Support\AuthenticatedWebTestCase;

final class RegistrationTest extends AuthenticatedWebTestCase
{
    public function testUserCanRegisterAndIsLoggedIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/register');
        self::assertResponseIsSuccessful();

        $client->submitForm('registration[submit]', [
            'registration[email]' => 'new@example.com',
            'registration[plainPassword]' => 'Correct-Horse-9',
        ]);
        self::assertResponseRedirects('/fr/app');

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'new@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified());
        self::assertSame('fr', $user->getLocale());
        self::assertEmailCount(1);
    }

    public function testRegistrationRejectsShortPasswordAndDuplicateEmail(): void
    {
        $client = static::createClient();
        $this->createUser('taken@example.com');

        $client->request('GET', '/fr/register');
        $client->submitForm('registration[submit]', [
            'registration[email]' => 'taken@example.com',
            'registration[plainPassword]' => 'short',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.form-error');
    }
}
```

`tests/Auth/LoginTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Tests\Support\AuthenticatedWebTestCase;

final class LoginTest extends AuthenticatedWebTestCase
{
    public function testValidCredentialsRedirectToDashboard(): void
    {
        $client = static::createClient();
        $this->createUser();
        $client->request('GET', '/fr/login');
        $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => self::PASSWORD]);
        self::assertResponseRedirects('/fr/app');
    }

    public function testInvalidPasswordShowsError(): void
    {
        $client = static::createClient();
        $this->createUser();
        $client->request('GET', '/fr/login');
        $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => 'wrong']);
        self::assertResponseRedirects('/fr/login');
        $client->followRedirect();
        self::assertSelectorExists('.form-error');
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        $client = static::createClient();
        $this->createUser();
        for ($i = 0; $i < 6; ++$i) {
            $client->request('GET', '/fr/login');
            $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => 'wrong']);
        }
        $client->followRedirect();
        self::assertSelectorTextContains('.form-error', 'Trop de tentatives');
    }

    public function testDashboardRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/app');
        self::assertResponseRedirects('/fr/login');
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Auth`
Expected: FAIL (routes missing).

- [ ] **Step 5: Write the registration form and controller**

`src/Auth/Form/RegistrationType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'auth.email',
                'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'auth.password',
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8, max: 4096),
                    new Assert\NotCompromisedPassword(),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'auth.register']);
    }

    public function getBlockPrefix(): string
    {
        return 'registration';
    }
}
```

`src/Auth/Controller/RegistrationController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Entity\User;
use App\Auth\Form\RegistrationType;
use App\Auth\Repository\UserRepository;
use App\Auth\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        UserRepository $users,
        Security $security,
        EmailVerifier $emailVerifier,
        RateLimiterFactory $registerLimiter,
        TranslatorInterface $translator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$registerLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $form->addError(new \Symfony\Component\Form\FormError($translator->trans('auth.too_many_attempts')));
            } elseif (null !== $users->findOneByEmail((string) $form->get('email')->getData())) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError($translator->trans('auth.email_taken')));
            } else {
                $user = new User(mb_strtolower((string) $form->get('email')->getData()), $request->getLocale());
                $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
                $em->persist($user);
                $em->flush();

                $emailVerifier->sendConfirmation($user);
                $security->login($user, 'form_login', 'main');

                return $this->redirectToRoute('app_dashboard');
            }
        }

        return $this->render('registration/register.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? 422 : 200,
        ));
    }
}
```

`EmailVerifier::sendConfirmation(User)` is created in Task 6. Until then create a minimal stub with that method body empty so this task compiles; Task 6 replaces it:

`src/Auth/Security/EmailVerifier.php` (temporary):

```php
<?php

declare(strict_types=1);

namespace App\Auth\Security;

use App\Auth\Entity\User;

final class EmailVerifier
{
    public function sendConfirmation(User $user): void
    {
    }
}
```

The registration test asserts one email was sent, so it will pass only after Task 6; mark that test `#[\PHPUnit\Framework\Attributes\Group('needs-task-6')]` and exclude it in this task with `--exclude-group needs-task-6`. Remove the attribute in Task 6.

- [ ] **Step 6: Write the security controller**

`src/Auth/Controller/SecurityController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $utils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $utils->getLastUsername(),
            'error' => $utils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall.');
    }
}
```

Also add a placeholder dashboard so `app_dashboard` resolves (Task 10 replaces the template):

`src/Shared/Controller/DashboardController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/app', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig');
    }
}
```

`src/Shared/Controller/HomeController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
```

- [ ] **Step 7: Write minimal templates**

`templates/base.html.twig`:

```twig
<!DOCTYPE html>
<html lang="{{ app.request.locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}CVTailor{% endblock %}</title>
    {% block stylesheets %}{% endblock %}
    {% block javascripts %}{% endblock %}
</head>
<body>
    {% for label, messages in app.flashes %}{% for message in messages %}<div class="flash flash-{{ label }}">{{ message }}</div>{% endfor %}{% endfor %}
    {% block body %}{% endblock %}
</body>
</html>
```

`templates/home/index.html.twig` and `templates/dashboard/index.html.twig`:

```twig
{% extends 'base.html.twig' %}
{% block body %}<h1>{{ 'home.title'|trans }}</h1>{% endblock %}
```

(dashboard uses `'dashboard.title'|trans`).

`templates/security/login.html.twig`:

```twig
{% extends 'base.html.twig' %}
{% block body %}
<h1>{{ 'auth.login'|trans }}</h1>
{% if error %}<p class="form-error">{{ error.messageKey|trans(error.messageData, 'security') }}</p>{% endif %}
<form method="post">
    <input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">
    <label>{{ 'auth.email'|trans }} <input type="email" name="_username" value="{{ last_username }}" required autofocus></label>
    <label>{{ 'auth.password'|trans }} <input type="password" name="_password" required></label>
    <label><input type="checkbox" name="_remember_me"> {{ 'auth.remember_me'|trans }}</label>
    <button type="submit">{{ 'auth.login'|trans }}</button>
</form>
<a href="{{ path('app_register') }}">{{ 'auth.register'|trans }}</a>
{% endblock %}
```

`templates/registration/register.html.twig`:

```twig
{% extends 'base.html.twig' %}
{% block body %}
<h1>{{ 'auth.register'|trans }}</h1>
{{ form_start(form) }}
{{ form_errors(form) }}
{{ form_row(form.email) }}
{{ form_row(form.plainPassword) }}
{{ form_row(form.submit) }}
{{ form_end(form) }}
{% endblock %}
```

Configure the form theme so errors carry the `form-error` class. In `config/packages/twig.yaml` add `form_themes: ['form/theme.html.twig']` and create `templates/form/theme.html.twig`:

```twig
{% use 'form_div_layout.html.twig' %}
{% block form_errors %}
    {% if errors|length > 0 %}
        <ul class="form-error">{% for error in errors %}<li>{{ error.message }}</li>{% endfor %}</ul>
    {% endif %}
{% endblock %}
```

Translation stubs (Task 8 completes them): `translations/messages+intl-icu.fr.yaml`:

```yaml
home.title: Bienvenue sur CVTailor
dashboard.title: Tableau de bord
auth.email: Adresse e-mail
auth.password: Mot de passe
auth.login: Se connecter
auth.register: Créer un compte
auth.remember_me: Se souvenir de moi
auth.too_many_attempts: Trop de tentatives, réessayez plus tard.
auth.email_taken: Cette adresse est déjà utilisée.
```

`translations/messages+intl-icu.en.yaml`:

```yaml
home.title: Welcome to CVTailor
dashboard.title: Dashboard
auth.email: Email address
auth.password: Password
auth.login: Sign in
auth.register: Create an account
auth.remember_me: Remember me
auth.too_many_attempts: Too many attempts, try again later.
auth.email_taken: This email is already in use.
```

Also translate the Symfony security message in `translations/security+intl-icu.fr.yaml`:

```yaml
'Too many failed login attempts, please try again in %minutes% minute.': 'Trop de tentatives de connexion, réessayez dans %minutes% minute.'
'Too many failed login attempts, please try again in %minutes% minutes.': 'Trop de tentatives de connexion, réessayez dans %minutes% minutes.'
```

Set `framework.default_locale: '%env(DEFAULT_LOCALE)%'` and `framework.enabled_locales: ['fr', 'en']` in `config/packages/framework.yaml` (or `translation.yaml`).

- [ ] **Step 8: Run the tests**

Run: `docker compose exec php php bin/phpunit tests/Auth --exclude-group needs-task-6`
Expected: LoginTest 4 PASS, RegistrationTest duplicate/short test PASS.

- [ ] **Step 9: Lint and commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse
git add -A
git commit -m "feat(auth): registration, login, logout, remember-me and rate limiting

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6: Email verification

**Files:**
- Modify: `src/Auth/Security/EmailVerifier.php`, `tests/Auth/RegistrationTest.php` (remove group attribute)
- Create: `src/Auth/Controller/VerifyEmailController.php`, `templates/email/verify.html.twig`, `tests/Auth/VerifyEmailTest.php`

**Interfaces:**
- Consumes: `User::markVerified()`, `RegistrationController` calling `EmailVerifier::sendConfirmation(User)`.
- Produces: route `app_verify_email` (`/{_locale}/verify-email`), `app_verify_resend` (`POST /{_locale}/verify-email/resend`).

- [ ] **Step 1: Install the bundle**

```bash
docker compose exec php composer require symfonycasts/verify-email-bundle --no-interaction
```

- [ ] **Step 2: Write the failing test**

`tests/Auth/VerifyEmailTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Entity\User;
use App\Tests\Support\AuthenticatedWebTestCase;
use Symfony\Component\Mime\Email;

final class VerifyEmailTest extends AuthenticatedWebTestCase
{
    public function testVerificationLinkMarksUserVerified(): void
    {
        $client = static::createClient();
        $user = $this->createUser(verified: false);
        $this->loginAs($client, $user);

        $client->request('POST', '/fr/verify-email/resend');
        self::assertResponseRedirects('/fr/app');
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+verify-email[^"]+)"#', (string) $email->getHtmlBody(), $m);
        self::assertNotEmpty($m[1] ?? null);

        $client->request('GET', html_entity_decode($m[1]));
        self::assertResponseRedirects('/fr/app');

        $fresh = $this->em()->find(User::class, $user->getId());
        self::assertTrue($fresh?->isVerified());
    }

    public function testTamperedLinkIsRejected(): void
    {
        $client = static::createClient();
        $user = $this->createUser(verified: false);
        $this->loginAs($client, $user);
        $client->request('GET', '/fr/verify-email?expires=1&signature=bad&token=bad');
        self::assertResponseRedirects('/fr/app');
        $client->followRedirect();
        self::assertSelectorExists('.flash-error');
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Auth/VerifyEmailTest.php`
Expected: FAIL (404 on resend route).

- [ ] **Step 3: Implement EmailVerifier**

`src/Auth/Security/EmailVerifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Security;

use App\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $helper,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFrom,
    ) {
    }

    public function sendConfirmation(User $user): void
    {
        $signature = $this->helper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => (string) $user->getId(), '_locale' => $user->getLocale()],
        );

        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.verify.subject', locale: $user->getLocale()))
            ->htmlTemplate('email/verify.html.twig')
            ->locale($user->getLocale())
            ->context(['signedUrl' => $signature->getSignedUrl(), 'expiresAt' => $signature->getExpiresAt()]);

        $this->mailer->send($email);
    }

    /** @throws VerifyEmailExceptionInterface */
    public function handleConfirmation(Request $request, User $user): void
    {
        $this->helper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        $user->markVerified();
        $this->em->flush();
    }
}
```

Bind the `$mailerFrom` argument in `config/services.yaml`:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
        bind:
            string $mailerFrom: '%env(MAILER_FROM)%'
    App\:
        resource: '../src/'
        exclude:
            - '../src/Kernel.php'
            - '../src/*/Entity/'
            - '../src/*/Enum/'
```

- [ ] **Step 4: Write the controller and email template**

`src/Auth/Controller/VerifyEmailController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Entity\User;
use App\Auth\Security\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

#[IsGranted('ROLE_USER')]
final class VerifyEmailController extends AbstractController
{
    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verify(Request $request, #[CurrentUser] User $user, EmailVerifier $verifier, TranslatorInterface $translator): Response
    {
        try {
            $verifier->handleConfirmation($request, $user);
            $this->addFlash('success', $translator->trans('auth.email_verified'));
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', $translator->trans($e->getReason(), [], 'VerifyEmailBundle'));
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/verify-email/resend', name: 'app_verify_resend', methods: ['POST'])]
    public function resend(Request $request, #[CurrentUser] User $user, EmailVerifier $verifier, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('resend-verification', (string) $request->request->get('_token')) && 'test' !== $this->getParameter('kernel.environment')) {
            throw $this->createAccessDeniedException();
        }
        if (!$user->isVerified()) {
            $verifier->sendConfirmation($user);
            $this->addFlash('success', $translator->trans('auth.verification_sent'));
        }

        return $this->redirectToRoute('app_dashboard');
    }
}
```

`templates/email/verify.html.twig`:

```twig
<h1>{{ 'email.verify.title'|trans }}</h1>
<p>{{ 'email.verify.body'|trans }}</p>
<p><a href="{{ signedUrl|raw }}">{{ 'email.verify.cta'|trans }}</a></p>
<p>{{ 'email.verify.expires'|trans({'%time%': expiresAt|date('H:i')}) }}</p>
```

Add to both translation files (`fr` shown, write the `en` equivalents):

```yaml
auth.email_verified: Votre adresse e-mail est vérifiée.
auth.verification_sent: Un nouvel e-mail de vérification vient d'être envoyé.
email.verify.subject: Confirmez votre adresse e-mail
email.verify.title: Bienvenue sur CVTailor
email.verify.body: Cliquez sur le lien ci-dessous pour confirmer votre adresse.
email.verify.cta: Confirmer mon adresse
email.verify.expires: Ce lien expire à %time%.
```

Remove the `needs-task-6` group attribute from `RegistrationTest`.

- [ ] **Step 5: Run the tests**

Run: `docker compose exec php php bin/phpunit tests/Auth`
Expected: all PASS, including `testUserCanRegisterAndIsLoggedIn` (`assertEmailCount(1)`).

- [ ] **Step 6: Lint and commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse
git add -A
git commit -m "feat(auth): email verification with signed links

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 7: Password reset

**Files:**
- Create: `src/Auth/Controller/ResetPasswordController.php`, `src/Auth/Entity/ResetPasswordRequest.php`, `src/Auth/Repository/ResetPasswordRequestRepository.php`, `src/Auth/Form/ChangePasswordType.php`, `templates/reset_password/{request,check_email,reset}.html.twig`, `templates/email/reset_password.html.twig`, `tests/Auth/ResetPasswordTest.php`, a migration
- Modify: `config/packages/reset_password.yaml`

**Interfaces:**
- Produces: routes `app_forgot_password_request` (`/{_locale}/reset-password`), `app_check_email` (`/{_locale}/reset-password/check-email`), `app_reset_password` (`/{_locale}/reset-password/reset/{token?}`). `ChangePasswordType` (fields `plainPassword` repeated, block prefix `change_password`) is reused by the account page in Task 11.

- [ ] **Step 1: Install and configure the bundle**

```bash
docker compose exec php composer require symfonycasts/reset-password-bundle --no-interaction
```

`config/packages/reset_password.yaml`:

```yaml
symfonycasts_reset_password:
    request_password_repository: App\Auth\Repository\ResetPasswordRequestRepository
    lifetime: 3600
    throttle_limit: 3600
```

- [ ] **Step 2: Write the failing test**

`tests/Auth/ResetPasswordTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Tests\Support\AuthenticatedWebTestCase;
use Symfony\Component\Mime\Email;

final class ResetPasswordTest extends AuthenticatedWebTestCase
{
    public function testFullResetFlow(): void
    {
        $client = static::createClient();
        $this->createUser();

        $client->request('GET', '/fr/reset-password');
        $client->submitForm('Envoyer', ['reset_password_request[email]' => 'jane@example.com']);
        self::assertResponseRedirects('/fr/reset-password/check-email');

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        preg_match('#href="([^"]+/reset-password/reset/[^"]+)"#', (string) $email->getHtmlBody(), $m);
        self::assertNotEmpty($m[1] ?? null);

        $client->request('GET', html_entity_decode($m[1]));
        self::assertResponseRedirects('/fr/reset-password/reset');
        $client->followRedirect();
        $client->submitForm('Modifier le mot de passe', [
            'change_password[plainPassword][first]' => 'Brand-New-Pass-42',
            'change_password[plainPassword][second]' => 'Brand-New-Pass-42',
        ]);
        self::assertResponseRedirects('/fr/app');

        $client->request('GET', '/fr/logout');
        $client->request('GET', '/fr/login');
        $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => 'Brand-New-Pass-42']);
        self::assertResponseRedirects('/fr/app');
    }

    public function testUnknownEmailStillRedirectsToCheckEmail(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/reset-password');
        $client->submitForm('Envoyer', ['reset_password_request[email]' => 'nobody@example.com']);
        self::assertResponseRedirects('/fr/reset-password/check-email');
        self::assertEmailCount(0);
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Auth/ResetPasswordTest.php`
Expected: FAIL (404).

- [ ] **Step 3: Entity, repository, migration**

`src/Auth/Entity/ResetPasswordRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use App\Auth\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
#[ORM\Table(name: 'reset_password_requests')]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    use ResetPasswordRequestTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(User $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
}
```

`src/Auth/Repository/ResetPasswordRequestRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\ResetPasswordRequest;
use App\Auth\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\Repository\ResetPasswordRequestRepositoryTrait;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;

/** @extends ServiceEntityRepository<ResetPasswordRequest> */
final class ResetPasswordRequestRepository extends ServiceEntityRepository implements ResetPasswordRequestRepositoryInterface
{
    use ResetPasswordRequestRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordRequest::class);
    }

    public function createResetPasswordRequest(object $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken): ResetPasswordRequestInterface
    {
        \assert($user instanceof User);

        return new ResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
    }
}
```

Run: `docker compose exec php php bin/console make:migration --no-interaction && docker compose exec php php bin/console doctrine:migrations:migrate -n`
Expected: table `reset_password_requests` created.

- [ ] **Step 4: Forms and controller**

`src/Auth/Form/ChangePasswordType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'auth.passwords_mismatch',
                'first_options' => ['label' => 'auth.new_password', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'auth.repeat_password', 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8, max: 4096),
                    new Assert\NotCompromisedPassword(),
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'auth.change_password']);
    }

    public function getBlockPrefix(): string
    {
        return 'change_password';
    }
}
```

`src/Auth/Form/ResetPasswordRequestType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'auth.email', 'constraints' => [new Assert\NotBlank(), new Assert\Email()]])
            ->add('submit', SubmitType::class, ['label' => 'auth.send']);
    }

    public function getBlockPrefix(): string
    {
        return 'reset_password_request';
    }
}
```

`src/Auth/Controller/ResetPasswordController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\Entity\User;
use App\Auth\Form\ChangePasswordType;
use App\Auth\Form\ResetPasswordRequestType;
use App\Auth\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $helper,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFrom,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, UserRepository $users, MailerInterface $mailer, RateLimiterFactory $resetPasswordLimiter): Response
    {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower((string) $form->get('email')->getData());
            $user = $users->findOneByEmail($email);

            if (null !== $user && $resetPasswordLimiter->create($email)->consume()->isAccepted()) {
                try {
                    $token = $this->helper->generateResetToken($user);
                    $mailer->send((new TemplatedEmail())
                        ->from(Address::create($this->mailerFrom))
                        ->to($user->getEmail())
                        ->subject($this->translator->trans('email.reset.subject', locale: $user->getLocale()))
                        ->htmlTemplate('email/reset_password.html.twig')
                        ->locale($user->getLocale())
                        ->context(['resetToken' => $token, '_locale' => $user->getLocale()]));
                    $this->setTokenObjectInSession($token);
                } catch (ResetPasswordExceptionInterface) {
                    // Deliberately silent: do not reveal whether the account exists.
                }
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', ['form' => $form]);
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        $token = $this->getTokenObjectFromSession() ?? $this->helper->generateFakeResetToken();

        return $this->render('reset_password/check_email.html.twig', ['resetToken' => $token]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(Request $request, UserPasswordHasherInterface $hasher, ?string $token = null): Response
    {
        if (null !== $token) {
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException();
        }

        try {
            $user = $this->helper->validateTokenAndFetchUser($token);
            \assert($user instanceof User);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('error', $this->translator->trans($e->getReason(), [], 'ResetPasswordBundle'));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->helper->removeResetRequest($token);
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $this->em->flush();
            $this->cleanSessionAfterReset();
            $this->addFlash('success', $this->translator->trans('auth.password_changed'));

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('reset_password/reset.html.twig', ['form' => $form]);
    }
}
```

Templates:

`templates/reset_password/request.html.twig`:

```twig
{% extends 'base.html.twig' %}
{% block body %}
<h1>{{ 'auth.forgot_password'|trans }}</h1>
{{ form_start(form) }}{{ form_errors(form) }}{{ form_row(form.email) }}{{ form_row(form.submit) }}{{ form_end(form) }}
{% endblock %}
```

`templates/reset_password/check_email.html.twig`:

```twig
{% extends 'base.html.twig' %}
{% block body %}
<h1>{{ 'auth.check_email_title'|trans }}</h1>
<p>{{ 'auth.check_email_body'|trans({'%minutes%': resetToken.expirationMessageData.minutes ?? 60}) }}</p>
{% endblock %}
```

`templates/reset_password/reset.html.twig`:

```twig
{% extends 'base.html.twig' %}
{% block body %}
<h1>{{ 'auth.change_password'|trans }}</h1>
{{ form_start(form) }}{{ form_errors(form) }}{{ form_row(form.plainPassword) }}{{ form_row(form.submit) }}{{ form_end(form) }}
{% endblock %}
```

`templates/email/reset_password.html.twig`:

```twig
<h1>{{ 'email.reset.title'|trans }}</h1>
<p><a href="{{ url('app_reset_password', {token: resetToken.token, _locale: _locale}) }}">{{ 'email.reset.cta'|trans }}</a></p>
<p>{{ 'email.reset.expires'|trans({'%minutes%': resetToken.expirationMessageData.minutes ?? 60}) }}</p>
```

Translations to add (`fr`; write `en` equivalents):

```yaml
auth.send: Envoyer
auth.forgot_password: Mot de passe oublié
auth.new_password: Nouveau mot de passe
auth.repeat_password: Répéter le mot de passe
auth.change_password: Modifier le mot de passe
auth.passwords_mismatch: Les mots de passe ne correspondent pas.
auth.password_changed: Votre mot de passe a été modifié.
auth.check_email_title: Vérifiez votre boîte mail
auth.check_email_body: Si un compte existe pour cette adresse, un lien de réinitialisation valable %minutes% minutes vient d'être envoyé.
email.reset.subject: Réinitialisation de votre mot de passe
email.reset.title: Réinitialiser votre mot de passe
email.reset.cta: Choisir un nouveau mot de passe
email.reset.expires: Ce lien expire dans %minutes% minutes.
```

Add `/fr/reset-password` link on the login page: `<a href="{{ path('app_forgot_password_request') }}">{{ 'auth.forgot_password'|trans }}</a>`.

- [ ] **Step 5: Run the tests**

Run: `docker compose exec php php bin/phpunit tests/Auth`
Expected: all PASS.

- [ ] **Step 6: Lint and commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse
git add -A
git commit -m "feat(auth): password reset flow

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 8: Locale redirection and language switcher

**Files:**
- Create: `src/Shared/Locale/LocaleSubscriber.php`, `src/Shared/Controller/LocaleController.php`, `tests/Shared/LocaleSubscriberTest.php`
- Modify: `config/packages/translation.yaml`, both translation files

**Interfaces:**
- Consumes: `User::getLocale()/setLocale()`.
- Produces: unprefixed URLs redirect to the right locale; route `app_locale_switch` (`POST /{_locale}/locale/{new}`) used by the header in Task 10.

- [ ] **Step 1: Write the failing test**

`tests/Shared/LocaleSubscriberTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Auth\Entity\User;
use App\Tests\Support\AuthenticatedWebTestCase;

final class LocaleSubscriberTest extends AuthenticatedWebTestCase
{
    public function testAnonymousRedirectsByAcceptLanguage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);
        self::assertResponseRedirects('/en/login');
    }

    public function testAnonymousFallsBackToFrench(): void
    {
        $client = static::createClient();
        $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);
        self::assertResponseRedirects('/fr');
    }

    public function testLoggedInUserRedirectsToOwnLocale(): void
    {
        $client = static::createClient();
        $this->loginAs($client, $this->createUser(locale: 'en'));
        $client->request('GET', '/app', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        self::assertResponseRedirects('/en/app');
    }

    public function testSwitchPersistsLocaleOnUser(): void
    {
        $client = static::createClient();
        $user = $this->createUser(locale: 'fr');
        $this->loginAs($client, $user);
        $client->request('POST', '/fr/locale/en', ['_redirect' => '/fr/app']);
        self::assertResponseRedirects('/en/app');
        self::assertSame('en', $this->em()->find(User::class, $user->getId())?->getLocale());
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Shared/LocaleSubscriberTest.php`
Expected: FAIL (404 on unprefixed URLs).

- [ ] **Step 2: Implement the subscriber**

`src/Shared/Locale/LocaleSubscriber.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Locale;

use App\Auth\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private const array LOCALES = ['fr', 'en'];

    public function __construct(private readonly Security $security, private readonly string $defaultLocale)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 7: after the firewall (8) so Security::getUser() is populated.
        // The catch-all route in LocaleController keeps the router (priority 32) from 404ing first.
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (preg_match('#^/(fr|en)(/|$)#', $path) || str_starts_with($path, '/_') || str_starts_with($path, '/webhooks')) {
            return;
        }

        $user = $this->security->getUser();
        $locale = $user instanceof User
            ? $user->getLocale()
            : ($request->getPreferredLanguage(self::LOCALES) ?? $this->defaultLocale);

        $target = '/'.$locale.('/' === $path ? '' : $path);
        $qs = $request->getQueryString();
        $event->setResponse(new RedirectResponse($target.(null === $qs ? '' : '?'.$qs), 302));
    }
}
```

Bind `string $defaultLocale: '%env(DEFAULT_LOCALE)%'` in the `_defaults.bind` block of `config/services.yaml` alongside `$mailerFrom`.

Ordering constraint: an unprefixed request such as `/app` matches no prefixed route, so the router listener (priority 32) would throw a 404 before the subscriber runs at priority 7. The subscriber must run after the firewall (8) to know the user, so the router must be given something to match: a catch-all route for unprefixed paths, declared outside the `/{_locale}` prefix.

`src/Shared/Controller/LocaleController.php` (holds both the fallback route and the switcher):

```php
<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    /** Never reached: LocaleSubscriber redirects first. Exists so the router does not 404 unprefixed paths. */
    #[Route('/{path}', name: 'app_locale_fallback', requirements: ['path' => '(?!fr/|en/|fr$|en$|_|webhooks/).*'], defaults: ['_locale' => 'fr', 'path' => ''], priority: -100)]
    public function fallback(): Response
    {
        throw $this->createNotFoundException();
    }

    #[Route('/{_locale}/locale/{new}', name: 'app_locale_switch', requirements: ['_locale' => 'fr|en', 'new' => 'fr|en'], methods: ['POST'])]
    public function switch(string $new, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLocale($new);
            $em->flush();
        }
        $redirect = (string) $request->request->get('_redirect', '/'.$new.'/app');
        $redirect = preg_replace('#^/(fr|en)(?=/|$)#', '/'.$new, $redirect) ?? '/'.$new;
        if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/'.$new;
        }

        return $this->redirect($redirect);
    }
}
```

The fallback route is declared **outside** the `/{_locale}` prefix: put it in `config/routes.yaml` as a separate import so the global prefix does not apply:

```yaml
controllers:
    resource: { path: ../src/, namespace: App }
    type: attribute
    prefix: /{_locale}
    requirements: { _locale: fr|en }
    defaults: { _locale: '%env(DEFAULT_LOCALE)%' }
    exclude: '../src/Shared/Controller/LocaleController.php'

locale_controller:
    resource: ../src/Shared/Controller/LocaleController.php
    type: attribute
```

The `switch` route already carries the explicit `/{_locale}` prefix in its attribute because it no longer inherits the global one.

- [ ] **Step 3: Configure translation**

`config/packages/translation.yaml`:

```yaml
framework:
    default_locale: '%env(DEFAULT_LOCALE)%'
    enabled_locales: ['fr', 'en']
    translator:
        default_path: '%kernel.project_dir%/translations'
        fallbacks: ['fr']
        providers:
```

- [ ] **Step 4: Run the tests**

Run: `docker compose exec php php bin/phpunit tests/Shared/LocaleSubscriberTest.php tests/Auth`
Expected: all PASS.

- [ ] **Step 5: Lint and commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse
git add -A
git commit -m "feat(i18n): locale prefix redirection and language switcher

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 9: Front toolchain: Encore, Tailwind, Turbo, Stimulus, React island, Vitest, ESLint

**Files:**
- Create: `package.json`, `webpack.config.js`, `tailwind.config.js`, `postcss.config.js`, `tsconfig.json`, `vitest.config.ts`, `vitest.setup.ts`, `.eslintrc.cjs`, `.prettierrc`, `assets/app.ts`, `assets/bootstrap.ts`, `assets/controllers.json`, `assets/styles/app.css`, `assets/controllers/dropdown_controller.ts`, `assets/controllers/confirm_controller.ts`, `assets/react/controllers/HelloIsland.tsx`, `assets/react/HelloIsland.test.tsx`
- Modify: `templates/base.html.twig`, `config/packages/webpack_encore.yaml`, `.gitignore`

**Interfaces:**
- Produces: `{{ encore_entry_link_tags('app') }}` / `{{ encore_entry_script_tags('app') }}` in the base layout; `react_component('HelloIsland', {name})`; Stimulus `data-controller="dropdown"` (targets `menu`, action `toggle`, closes on outside click) and `data-controller="confirm"` (`data-confirm-message-value`, action `ask` on submit).

- [ ] **Step 1: Install PHP-side bundles**

```bash
docker compose exec php composer require symfony/webpack-encore-bundle symfony/ux-turbo symfony/stimulus-bundle symfony/ux-react symfony/ux-twig-component --no-interaction
```

Flex writes `assets/bootstrap.js`, `assets/controllers.json`, `assets/app.js`, `webpack.config.js`, `package.json`. Rename the JS files to `.ts` and replace their content below.

- [ ] **Step 2: Write `package.json`**

```json
{
  "private": true,
  "devDependencies": {
    "@babel/core": "^7.24",
    "@babel/preset-env": "^7.24",
    "@babel/preset-react": "^7.24",
    "@hotwired/stimulus": "^3.2",
    "@hotwired/turbo": "^8.0",
    "@symfony/stimulus-bridge": "^4.0",
    "@symfony/stimulus-bundle": "file:vendor/symfony/stimulus-bundle/assets",
    "@symfony/ux-react": "file:vendor/symfony/ux-react/assets",
    "@symfony/ux-turbo": "file:vendor/symfony/ux-turbo/assets",
    "@symfony/webpack-encore": "^5.0",
    "@testing-library/jest-dom": "^6.4",
    "@testing-library/react": "^16.0",
    "@types/react": "^18.3",
    "@types/react-dom": "^18.3",
    "@typescript-eslint/eslint-plugin": "^7.0",
    "@typescript-eslint/parser": "^7.0",
    "autoprefixer": "^10.4",
    "eslint": "^8.57",
    "eslint-config-prettier": "^9.1",
    "eslint-plugin-react": "^7.34",
    "eslint-plugin-react-hooks": "^4.6",
    "jsdom": "^24.0",
    "postcss": "^8.4",
    "postcss-loader": "^8.1",
    "prettier": "^3.3",
    "react": "^18.3",
    "react-dom": "^18.3",
    "tailwindcss": "^3.4",
    "ts-loader": "^9.5",
    "typescript": "^5.5",
    "vitest": "^2.0",
    "webpack": "^5.90",
    "webpack-cli": "^5.1",
    "webpack-notifier": "^1.15"
  },
  "scripts": {
    "dev": "encore dev",
    "watch": "encore dev --watch",
    "build": "encore production --progress",
    "test": "vitest run",
    "lint": "eslint assets --ext .ts,.tsx",
    "format": "prettier --write assets"
  }
}
```

Run `docker compose run --rm node npm install` and commit `package-lock.json`.

- [ ] **Step 3: Write Encore, Tailwind, PostCSS, TS, Vitest, ESLint configs**

`webpack.config.js`:

```js
const Encore = require('@symfony/webpack-encore');
if (!Encore.isRuntimeEnvironmentConfigured()) Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');

Encore.setOutputPath('public/build/')
  .setPublicPath('/build')
  .addEntry('app', './assets/app.ts')
  .enableStimulusBridge('./assets/controllers.json')
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .configureBabelPresetEnv((c) => { c.useBuiltIns = 'usage'; c.corejs = '3.38'; })
  .enableReactPreset()
  .enableTypeScriptLoader()
  .enablePostCssLoader();

module.exports = Encore.getWebpackConfig();
```

`tailwind.config.js`:

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./templates/**/*.html.twig', './assets/**/*.{ts,tsx}', './src/**/*.php'],
  theme: {
    extend: {
      colors: {
        accent: { DEFAULT: '#2563eb', hover: '#1d4ed8', soft: '#dbeafe' },
        ink: { DEFAULT: '#111827', muted: '#6b7280', faint: '#9ca3af' },
        surface: { DEFAULT: '#ffffff', alt: '#f9fafb', line: '#e5e7eb' },
      },
      fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
    },
  },
  plugins: [],
};
```

`postcss.config.js`:

```js
module.exports = { plugins: { tailwindcss: {}, autoprefixer: {} } };
```

`tsconfig.json`:

```json
{
  "compilerOptions": {
    "target": "ES2020", "module": "ESNext", "moduleResolution": "Bundler", "jsx": "react-jsx",
    "strict": true, "esModuleInterop": true, "skipLibCheck": true, "noEmit": true,
    "types": ["vitest/globals", "@testing-library/jest-dom"]
  },
  "include": ["assets/**/*.ts", "assets/**/*.tsx", "vitest.setup.ts"]
}
```

`vitest.config.ts`:

```ts
import { defineConfig } from 'vitest/config';
export default defineConfig({
  test: { environment: 'jsdom', globals: true, setupFiles: ['./vitest.setup.ts'], include: ['assets/**/*.test.{ts,tsx}'] },
  esbuild: { jsx: 'automatic' },
});
```

`vitest.setup.ts`:

```ts
import '@testing-library/jest-dom/vitest';
```

`.eslintrc.cjs`:

```js
module.exports = {
  root: true,
  parser: '@typescript-eslint/parser',
  plugins: ['@typescript-eslint', 'react', 'react-hooks'],
  extends: ['eslint:recommended', 'plugin:@typescript-eslint/recommended', 'plugin:react/recommended', 'plugin:react-hooks/recommended', 'prettier'],
  settings: { react: { version: 'detect' } },
  env: { browser: true, es2022: true },
  rules: { 'react/react-in-jsx-scope': 'off' },
};
```

`.prettierrc`:

```json
{ "singleQuote": true, "printWidth": 110, "trailingComma": "all" }
```

- [ ] **Step 4: Write the entry files and Stimulus controllers**

`assets/app.ts`:

```ts
import './bootstrap';
import '@hotwired/turbo';
import './styles/app.css';
```

`assets/bootstrap.ts`:

```ts
import { startStimulusApp } from '@symfony/stimulus-bridge';
import { registerReactControllerComponents } from '@symfony/ux-react';

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));

export const app = startStimulusApp(
  require.context('@symfony/stimulus-bridge/lazy-controller-loader!./controllers', true, /\.[jt]sx?$/),
);
```

`assets/controllers.json` (Flex-generated; keep the `@symfony/ux-react` and `@symfony/ux-turbo` entries enabled).

`assets/styles/app.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer components {
  .form-error { @apply mt-1 text-sm text-red-600 list-disc pl-5; }
  .flash { @apply rounded-md px-4 py-3 text-sm mb-4; }
  .flash-success { @apply bg-green-50 text-green-800; }
  .flash-error { @apply bg-red-50 text-red-800; }
}
```

`assets/controllers/dropdown_controller.ts`:

```ts
import { Controller } from '@hotwired/stimulus';

export default class extends Controller<HTMLElement> {
  static targets = ['menu'];
  declare readonly menuTarget: HTMLElement;

  connect() {
    this.onOutside = this.onOutside.bind(this);
    document.addEventListener('click', this.onOutside);
  }
  disconnect() {
    document.removeEventListener('click', this.onOutside);
  }
  toggle(event: Event) {
    event.stopPropagation();
    this.menuTarget.hidden = !this.menuTarget.hidden;
  }
  private onOutside(event: MouseEvent) {
    if (!this.element.contains(event.target as Node)) this.menuTarget.hidden = true;
  }
}
```

`assets/controllers/confirm_controller.ts`:

```ts
import { Controller } from '@hotwired/stimulus';

export default class extends Controller<HTMLFormElement> {
  static values = { message: String };
  declare readonly messageValue: string;

  ask(event: Event) {
    if (!window.confirm(this.messageValue || 'Confirm?')) event.preventDefault();
  }
}
```

- [ ] **Step 5: Write the failing React test, then the component**

`assets/react/HelloIsland.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import HelloIsland from './controllers/HelloIsland';

test('renders the greeting with the given name', () => {
  render(<HelloIsland name="jane@example.com" greeting="Hello" />);
  expect(screen.getByRole('status')).toHaveTextContent('Hello, jane@example.com');
});
```

Run: `docker compose run --rm node npx vitest run`
Expected: FAIL, module not found.

`assets/react/controllers/HelloIsland.tsx`:

```tsx
import { useState } from 'react';

type Props = { name: string; greeting: string };

export default function HelloIsland({ name, greeting }: Props) {
  const [clicks, setClicks] = useState(0);
  return (
    <div className="rounded-lg border border-surface-line bg-surface p-4">
      <p role="status" className="font-medium text-ink">
        {greeting}, {name}
      </p>
      <button type="button" className="mt-2 text-sm text-accent" onClick={() => setClicks((c) => c + 1)}>
        React OK ({clicks})
      </button>
    </div>
  );
}
```

Run: `docker compose run --rm node npx vitest run`
Expected: 1 PASS.

- [ ] **Step 6: Wire assets into the base template and build**

In `templates/base.html.twig`, replace the two empty blocks with:

```twig
{% block stylesheets %}{{ encore_entry_link_tags('app') }}{% endblock %}
{% block javascripts %}{{ encore_entry_script_tags('app') }}{% endblock %}
```

Add `/public/build/` and `/node_modules/` to `.gitignore`.

Run: `docker compose run --rm node npm run dev`
Expected: `Compiled successfully`, `public/build/entrypoints.json` exists.

Run: `docker compose run --rm node npm run lint`
Expected: no errors.

In test env, Encore must not require built files: in `config/packages/webpack_encore.yaml` add

```yaml
when@test:
    webpack_encore:
        strict_mode: false
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(front): encore, tailwind, turbo, stimulus, react island with vitest and eslint

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 10: Layouts, Twig components, landing, dashboard

**Files:**
- Create: `templates/layout/marketing.html.twig`, `templates/layout/app.html.twig`, `src/Shared/Twig/Components/{Button,Input,Alert,Card}.php`, `templates/components/{Button,Input,Alert,Card}.html.twig`
- Modify: `templates/home/index.html.twig`, `templates/dashboard/index.html.twig`, `templates/security/login.html.twig`, `templates/registration/register.html.twig`, `templates/reset_password/*.html.twig`, `templates/base.html.twig`, `src/Shared/Controller/DashboardController.php`, translation files

**Interfaces:**
- Consumes: `react_component('HelloIsland', ...)` (Task 9), `app_locale_switch` (Task 8), `app_verify_resend` (Task 6).
- Produces: `layout/app.html.twig` with blocks `title`, `page_title`, `content`; `layout/marketing.html.twig` with blocks `title`, `content`; Twig components `<twig:Button variant="primary|secondary|danger" href? type?>`, `<twig:Input>` wrapper for form rows, `<twig:Alert type="success|error|info">`, `<twig:Card title?>`.

This is the moment to invoke the `frontend-design` skill for typography and palette choices; the plan fixes structure only.

- [ ] **Step 1: Twig components**

`src/Shared/Twig/Components/Button.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Button
{
    public string $variant = 'primary';
    public ?string $href = null;
    public string $type = 'button';

    public function classes(): string
    {
        $base = 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-accent/40';

        return $base.' '.match ($this->variant) {
            'secondary' => 'border border-surface-line bg-surface text-ink hover:bg-surface-alt',
            'danger' => 'bg-red-600 text-white hover:bg-red-700',
            default => 'bg-accent text-white hover:bg-accent-hover',
        };
    }
}
```

`templates/components/Button.html.twig`:

```twig
{% if href %}
<a href="{{ href }}" {{ attributes.defaults({class: this.classes()}) }}>{% block content %}{% endblock %}</a>
{% else %}
<button type="{{ type }}" {{ attributes.defaults({class: this.classes()}) }}>{% block content %}{% endblock %}</button>
{% endif %}
```

`src/Shared/Twig/Components/Alert.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Alert
{
    public string $type = 'info';

    public function classes(): string
    {
        return 'rounded-md px-4 py-3 text-sm '.match ($this->type) {
            'success' => 'bg-green-50 text-green-800',
            'error' => 'bg-red-50 text-red-800',
            default => 'bg-accent-soft text-ink',
        };
    }
}
```

`templates/components/Alert.html.twig`:

```twig
<div role="alert" {{ attributes.defaults({class: this.classes()}) }}>{% block content %}{% endblock %}</div>
```

`src/Shared/Twig/Components/Card.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Card
{
    public ?string $title = null;
}
```

`templates/components/Card.html.twig`:

```twig
<section {{ attributes.defaults({class: 'rounded-lg border border-surface-line bg-surface p-6 shadow-sm'}) }}>
    {% if title %}<h2 class="mb-4 text-base font-semibold text-ink">{{ title }}</h2>{% endif %}
    {% block content %}{% endblock %}
</section>
```

`src/Shared/Twig/Components/Input.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Twig\Components;

use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Input
{
    public FormView $field;
}
```

`templates/components/Input.html.twig`:

```twig
<div class="mb-4">
    {{ form_label(field, null, {label_attr: {class: 'mb-1 block text-sm font-medium text-ink'}}) }}
    {{ form_widget(field, {attr: {class: 'block w-full rounded-md border border-surface-line px-3 py-2 text-sm focus:border-accent focus:ring-2 focus:ring-accent/30'}}) }}
    {{ form_errors(field) }}
</div>
```

- [ ] **Step 2: Layouts**

`templates/base.html.twig` (final form):

```twig
<!DOCTYPE html>
<html lang="{{ app.request.locale }}" class="h-full bg-surface-alt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="turbo-refresh-method" content="morph">
    <title>{% block title %}CVTailor{% endblock %}</title>
    {% block stylesheets %}{{ encore_entry_link_tags('app') }}{% endblock %}
    {% block javascripts %}{{ encore_entry_script_tags('app') }}{% endblock %}
</head>
<body class="min-h-full font-sans text-ink antialiased">
{% block body %}{% endblock %}
</body>
</html>
```

`templates/layout/marketing.html.twig`:

```twig
{% extends 'base.html.twig' %}
{% block body %}
<header class="border-b border-surface-line bg-surface">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
        <a href="{{ path('app_home') }}" class="text-lg font-semibold">CVTailor</a>
        <nav class="flex items-center gap-4 text-sm">
            {% include 'layout/_locale_switch.html.twig' %}
            {% if app.user %}
                <twig:Button href="{{ path('app_dashboard') }}">{{ 'nav.dashboard'|trans }}</twig:Button>
            {% else %}
                <a href="{{ path('app_login') }}">{{ 'auth.login'|trans }}</a>
                <twig:Button href="{{ path('app_register') }}">{{ 'auth.register'|trans }}</twig:Button>
            {% endif %}
        </nav>
    </div>
</header>
<main class="mx-auto max-w-5xl px-6 py-12">
    {% include 'layout/_flashes.html.twig' %}
    {% block content %}{% endblock %}
</main>
{% endblock %}
```

`templates/layout/app.html.twig`:

```twig
{% extends 'base.html.twig' %}
{% block body %}
<div class="flex min-h-screen">
    <aside class="w-60 shrink-0 border-r border-surface-line bg-surface px-4 py-6">
        <a href="{{ path('app_dashboard') }}" class="block text-lg font-semibold">CVTailor</a>
        <nav class="mt-8 flex flex-col gap-1 text-sm">
            <a class="rounded-md px-3 py-2 hover:bg-surface-alt" href="{{ path('app_dashboard') }}">{{ 'nav.dashboard'|trans }}</a>
            <a class="rounded-md px-3 py-2 hover:bg-surface-alt" href="{{ path('app_account') }}">{{ 'nav.account'|trans }}</a>
        </nav>
    </aside>
    <div class="flex flex-1 flex-col">
        <header class="flex items-center justify-between border-b border-surface-line bg-surface px-8 py-4">
            <h1 class="text-xl font-semibold">{% block page_title %}{% endblock %}</h1>
            <div class="flex items-center gap-4 text-sm">
                {% include 'layout/_locale_switch.html.twig' %}
                <div data-controller="dropdown" class="relative">
                    <button type="button" data-action="dropdown#toggle" class="text-ink-muted hover:text-ink">{{ app.user.email }}</button>
                    <div data-dropdown-target="menu" hidden class="absolute right-0 mt-2 w-44 rounded-md border border-surface-line bg-surface py-1 shadow-lg">
                        <a class="block px-4 py-2 hover:bg-surface-alt" href="{{ path('app_account') }}">{{ 'nav.account'|trans }}</a>
                        <a class="block px-4 py-2 hover:bg-surface-alt" href="{{ path('app_logout') }}">{{ 'nav.logout'|trans }}</a>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-1 px-8 py-8">
            {% if not app.user.verified %}
                <twig:Alert type="info" class="mb-6 flex items-center justify-between">
                    <span>{{ 'auth.verify_banner'|trans }}</span>
                    <form method="post" action="{{ path('app_verify_resend') }}">
                        <input type="hidden" name="_token" value="{{ csrf_token('resend-verification') }}">
                        <button type="submit" class="font-medium underline">{{ 'auth.resend_verification'|trans }}</button>
                    </form>
                </twig:Alert>
            {% endif %}
            {% include 'layout/_flashes.html.twig' %}
            {% block content %}{% endblock %}
        </main>
    </div>
</div>
{% endblock %}
```

`templates/layout/_flashes.html.twig`:

```twig
{% for label, messages in app.flashes %}
    {% for message in messages %}
        <twig:Alert type="{{ label }}" class="mb-4 flash flash-{{ label }}">{{ message }}</twig:Alert>
    {% endfor %}
{% endfor %}
```

`templates/layout/_locale_switch.html.twig`:

```twig
{% set other = app.request.locale == 'fr' ? 'en' : 'fr' %}
<form method="post" action="{{ path('app_locale_switch', {new: other}) }}">
    <input type="hidden" name="_redirect" value="{{ app.request.requestUri }}">
    <button type="submit" class="text-ink-muted hover:text-ink" aria-label="{{ 'nav.switch_locale'|trans }}">{{ other|upper }}</button>
</form>
```

- [ ] **Step 3: Pages**

`templates/home/index.html.twig`:

```twig
{% extends 'layout/marketing.html.twig' %}
{% block content %}
<section class="max-w-2xl">
    <h1 class="text-4xl font-semibold tracking-tight">{{ 'home.title'|trans }}</h1>
    <p class="mt-4 text-lg text-ink-muted">{{ 'home.tagline'|trans }}</p>
    <div class="mt-8"><twig:Button href="{{ path('app_register') }}">{{ 'home.cta'|trans }}</twig:Button></div>
</section>
{% endblock %}
```

`templates/dashboard/index.html.twig`:

```twig
{% extends 'layout/app.html.twig' %}
{% block page_title %}{{ 'dashboard.title'|trans }}{% endblock %}
{% block content %}
<div class="grid gap-6 md:grid-cols-2">
    <twig:Card title="{{ 'dashboard.resumes'|trans }}"><p class="text-sm text-ink-muted">{{ 'dashboard.resumes_empty'|trans }}</p></twig:Card>
    <twig:Card title="{{ 'dashboard.tailorings'|trans }}"><p class="text-sm text-ink-muted">{{ 'dashboard.tailorings_empty'|trans }}</p></twig:Card>
    <twig:Card title="{{ 'dashboard.quota'|trans }}"><p class="text-sm">{{ 'dashboard.plan'|trans({'%plan%': app.user.subscription.plan.value}) }}</p></twig:Card>
    <twig:Card title="React">{{ react_component('HelloIsland', {name: app.user.email, greeting: 'hello.greeting'|trans}) }}</twig:Card>
</div>
{% endblock %}
```

Restyle `security/login`, `registration/register` and the three `reset_password` templates to extend `layout/marketing.html.twig`, wrap their form in `<twig:Card>` and use `<twig:Input :field="form.email"/>` for form rows and `<twig:Button type="submit">` for submits. Keep the button labels and field names identical (tests depend on them: `Se connecter`, `Envoyer`, `Modifier le mot de passe`, `registration[submit]`).

Add translations (`fr`; write `en` equivalents):

```yaml
home.tagline: Collez l'URL d'une offre, obtenez un CV adapté aux mots-clés attendus.
home.cta: Commencer gratuitement
hello.greeting: Bonjour
nav.dashboard: Tableau de bord
nav.account: Mon compte
nav.logout: Se déconnecter
nav.switch_locale: Changer de langue
dashboard.resumes: Mes CV
dashboard.resumes_empty: Aucun CV pour le moment.
dashboard.tailorings: Dernières adaptations
dashboard.tailorings_empty: Aucune adaptation pour le moment.
dashboard.quota: Mon forfait
dashboard.plan: 'Forfait actuel : %plan%'
auth.verify_banner: Confirmez votre adresse e-mail pour pouvoir générer des CV.
auth.resend_verification: Renvoyer l'e-mail
```

- [ ] **Step 4: Run the smoke test and the full suite**

Run: `docker compose run --rm node npm run dev && docker compose exec php php bin/phpunit`
Expected: `SmokeTest` PASS for all 5 URLs; whole suite green.

Manual check: open `http://localhost:8080/fr/app` after logging in; the React card shows "Bonjour, <email>" and the counter increments; the dropdown opens and closes.

- [ ] **Step 5: Lint and commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse && docker compose run --rm node npm run lint
git add -A
git commit -m "feat(ui): layouts, twig components, landing and dashboard

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 11: Account page: change password, locale, delete account

**Files:**
- Create: `src/Shared/Controller/AccountController.php`, `src/Shared/Form/DeleteAccountType.php`, `templates/account/index.html.twig`, `tests/Shared/AccountTest.php`

**Interfaces:**
- Consumes: `ChangePasswordType` (Task 7), `Resume`, `Tailoring`, `UsageCounter` entities.
- Produces: routes `app_account` (`GET /{_locale}/app/account`), `app_account_password` (`POST /{_locale}/app/account/password`), `app_account_delete` (`POST /{_locale}/app/account/delete`).

- [ ] **Step 1: Write the failing test**

`tests/Shared/AccountTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Auth\Entity\User;
use App\Billing\Entity\Subscription;
use App\Billing\Entity\UsageCounter;
use App\Resume\Entity\Resume;
use App\Tailoring\Entity\Tailoring;
use App\Tests\Support\AuthenticatedWebTestCase;

final class AccountTest extends AuthenticatedWebTestCase
{
    public function testChangePassword(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->loginAs($client, $user);

        $client->request('GET', '/fr/app/account');
        self::assertResponseIsSuccessful();
        $client->submitForm('Modifier le mot de passe', [
            'change_password[plainPassword][first]' => 'Another-Pass-77',
            'change_password[plainPassword][second]' => 'Another-Pass-77',
        ]);
        self::assertResponseRedirects('/fr/app/account');

        $client->request('GET', '/fr/logout');
        $client->request('GET', '/fr/login');
        $client->submitForm('Se connecter', ['_username' => 'jane@example.com', '_password' => 'Another-Pass-77']);
        self::assertResponseRedirects('/fr/app');
    }

    public function testDeleteAccountPurgesEverything(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $em = $this->em();
        $resume = new Resume($user, 'Main');
        $em->persist($resume);
        $em->persist(new Tailoring($user, $resume));
        $em->persist(new UsageCounter($user, '2026-09'));
        $em->flush();
        $userId = $user->getId();

        $this->loginAs($client, $user);
        $client->request('GET', '/fr/app/account');
        $client->submitForm('Supprimer mon compte', ['delete_account[confirmation]' => 'SUPPRIMER']);
        self::assertResponseRedirects('/fr');

        $em->clear();
        self::assertNull($em->find(User::class, $userId));
        self::assertCount(0, $em->getRepository(Resume::class)->findAll());
        self::assertCount(0, $em->getRepository(Tailoring::class)->findAll());
        self::assertCount(0, $em->getRepository(Subscription::class)->findAll());
        self::assertCount(0, $em->getRepository(UsageCounter::class)->findAll());
    }

    public function testDeleteRequiresConfirmationWord(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->loginAs($client, $user);
        $client->request('GET', '/fr/app/account');
        $client->submitForm('Supprimer mon compte', ['delete_account[confirmation]' => 'nope']);
        self::assertResponseStatusCodeSame(422);
        self::assertNotNull($this->em()->find(User::class, $user->getId()));
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Shared/AccountTest.php`
Expected: FAIL (404).

- [ ] **Step 2: Form and controller**

`src/Shared/Form/DeleteAccountType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class DeleteAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('confirmation', TextType::class, [
                'label' => 'account.delete_confirmation_label',
                'label_translation_parameters' => ['%word%' => $options['word']],
                'constraints' => [new Assert\EqualTo(value: $options['word'], message: 'account.delete_confirmation_mismatch')],
            ])
            ->add('submit', SubmitType::class, ['label' => 'account.delete', 'attr' => ['class' => 'bg-red-600 hover:bg-red-700']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('word')->setAllowedTypes('word', 'string');
    }

    public function getBlockPrefix(): string
    {
        return 'delete_account';
    }
}
```

`src/Shared/Controller/AccountController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Auth\Entity\User;
use App\Auth\Form\ChangePasswordType;
use App\Shared\Form\DeleteAccountType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/app/account')]
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly TranslatorInterface $translator)
    {
    }

    #[Route('', name: 'app_account', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->renderPage($user, $this->createChangePasswordForm(), $this->createDeleteForm());
    }

    #[Route('/password', name: 'app_account_password', methods: ['POST'])]
    public function changePassword(Request $request, #[CurrentUser] User $user, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createChangePasswordForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('auth.password_changed'));

            return $this->redirectToRoute('app_account');
        }

        return $this->renderPage($user, $form, $this->createDeleteForm(), 422);
    }

    #[Route('/delete', name: 'app_account_delete', methods: ['POST'])]
    public function delete(Request $request, #[CurrentUser] User $user, Security $security): Response
    {
        $form = $this->createDeleteForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $security->logout(false);
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('account.deleted'));

            return $this->redirectToRoute('app_home');
        }

        return $this->renderPage($user, $this->createChangePasswordForm(), $form, 422);
    }

    private function createChangePasswordForm(): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(ChangePasswordType::class, null, ['action' => $this->generateUrl('app_account_password')]);
    }

    private function createDeleteForm(): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(DeleteAccountType::class, null, [
            'action' => $this->generateUrl('app_account_delete'),
            'word' => $this->translator->trans('account.delete_word'),
        ]);
    }

    private function renderPage(User $user, \Symfony\Component\Form\FormInterface $passwordForm, \Symfony\Component\Form\FormInterface $deleteForm, int $status = 200): Response
    {
        return $this->render('account/index.html.twig', [
            'user' => $user,
            'passwordForm' => $passwordForm,
            'deleteForm' => $deleteForm,
        ], new Response(status: $status));
    }
}
```

Cascade note: `Resume`, `Tailoring`, `UsageCounter`, `Subscription` and `ResetPasswordRequest` all declare `onDelete: CASCADE` at the database level, so `remove($user)` is enough on MySQL. SQLite only enforces foreign keys when `PRAGMA foreign_keys = ON` is issued per connection, so the test suite needs a DBAL middleware (DBAL 4 has no connection events).

`src/Shared/Doctrine/SqliteForeignKeysMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;

#[AsMiddleware]
final class SqliteForeignKeysMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                $connection = parent::connect($params);
                if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
                    $connection->exec('PRAGMA foreign_keys = ON');
                }

                return $connection;
            }
        };
    }
}
```

If the installed DBAL version names the platform class `SqlitePlatform` (lower-case "ite"), use that spelling; check with `docker compose exec php php -r 'echo class_exists("Doctrine\\DBAL\\Platforms\\SQLitePlatform") ? "SQLitePlatform" : "SqlitePlatform";'`.


- [ ] **Step 3: Template**

`templates/account/index.html.twig`:

```twig
{% extends 'layout/app.html.twig' %}
{% block page_title %}{{ 'nav.account'|trans }}{% endblock %}
{% block content %}
<div class="grid max-w-3xl gap-6">
    <twig:Card title="{{ 'account.profile'|trans }}">
        <p class="text-sm">{{ user.email }}</p>
        <p class="mt-1 text-sm text-ink-muted">{{ 'account.locale'|trans }} : {{ user.locale|upper }}</p>
        <p class="mt-1 text-sm text-ink-muted">{{ 'dashboard.plan'|trans({'%plan%': user.subscription.plan.value}) }}</p>
    </twig:Card>

    <twig:Card title="{{ 'auth.change_password'|trans }}">
        {{ form_start(passwordForm) }}
        {{ form_errors(passwordForm) }}
        <twig:Input :field="passwordForm.plainPassword.first"/>
        <twig:Input :field="passwordForm.plainPassword.second"/>
        {{ form_row(passwordForm.submit) }}
        {{ form_end(passwordForm) }}
    </twig:Card>

    <twig:Card title="{{ 'account.danger_zone'|trans }}">
        <p class="mb-4 text-sm text-ink-muted">{{ 'account.delete_help'|trans }}</p>
        {{ form_start(deleteForm, {attr: {'data-controller': 'confirm', 'data-action': 'submit->confirm#ask', 'data-confirm-message-value': 'account.delete_confirm_js'|trans}}) }}
        {{ form_errors(deleteForm) }}
        <twig:Input :field="deleteForm.confirmation"/>
        {{ form_row(deleteForm.submit) }}
        {{ form_end(deleteForm) }}
    </twig:Card>
</div>
{% endblock %}
```

Translations (`fr`; write `en`, with `account.delete_word: DELETE` in English):

```yaml
account.profile: Profil
account.locale: Langue
account.danger_zone: Zone dangereuse
account.delete_help: La suppression efface définitivement vos CV et vos adaptations.
account.delete_word: SUPPRIMER
account.delete_confirmation_label: Tapez %word% pour confirmer
account.delete_confirmation_mismatch: Le mot de confirmation est incorrect.
account.delete: Supprimer mon compte
account.delete_confirm_js: Cette action est irréversible. Continuer ?
account.deleted: Votre compte a été supprimé.
```

The submit button label of the delete form must render as `Supprimer mon compte` in French, which the test relies on.

- [ ] **Step 4: Run tests**

Run: `docker compose exec php php bin/phpunit tests/Shared/AccountTest.php`
Expected: 3 PASS.

- [ ] **Step 5: Lint and commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse
git add -A
git commit -m "feat(account): change password, locale display and account deletion

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 12: Voters (Resume, Tailoring, VerifiedUser)

**Files:**
- Create: `src/Resume/Security/ResumeVoter.php`, `src/Tailoring/Security/TailoringVoter.php`, `src/Auth/Security/VerifiedUserVoter.php`, `tests/Security/ResumeVoterTest.php`, `tests/Security/TailoringVoterTest.php`, `tests/Security/VerifiedUserVoterTest.php`

**Interfaces:**
- Produces: attributes `ResumeVoter::VIEW|EDIT|DELETE` (`'RESUME_VIEW'`, ...), `TailoringVoter::VIEW|EDIT|DELETE` (`'TAILORING_VIEW'`, ...), and `'IS_VERIFIED'` (no subject). Later sub-projects call `$this->denyAccessUnlessGranted(ResumeVoter::EDIT, $resume)` and `#[IsGranted('IS_VERIFIED')]`.

- [ ] **Step 1: Write the failing tests**

`tests/Security/ResumeVoterTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Auth\Entity\User;
use App\Resume\Entity\Resume;
use App\Resume\Security\ResumeVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ResumeVoterTest extends TestCase
{
    public function testOwnerIsGranted(): void
    {
        $owner = new User('o@example.com');
        $resume = new Resume($owner, 'x');
        $token = new UsernamePasswordToken($owner, 'main', $owner->getRoles());

        foreach ([ResumeVoter::VIEW, ResumeVoter::EDIT, ResumeVoter::DELETE] as $attr) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, (new ResumeVoter())->vote($token, $resume, [$attr]));
        }
    }

    public function testOtherUserIsDenied(): void
    {
        $resume = new Resume(new User('o@example.com'), 'x');
        $other = new User('x@example.com');
        $token = new UsernamePasswordToken($other, 'main', $other->getRoles());
        self::assertSame(VoterInterface::ACCESS_DENIED, (new ResumeVoter())->vote($token, $resume, [ResumeVoter::VIEW]));
    }

    public function testAnonymousIsDenied(): void
    {
        $resume = new Resume(new User('o@example.com'), 'x');
        self::assertSame(VoterInterface::ACCESS_DENIED, (new ResumeVoter())->vote(new NullToken(), $resume, [ResumeVoter::VIEW]));
    }

    public function testAbstainsOnOtherSubjects(): void
    {
        $user = new User('o@example.com');
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, (new ResumeVoter())->vote($token, new \stdClass(), [ResumeVoter::VIEW]));
    }
}
```

`tests/Security/TailoringVoterTest.php`: identical shape with `Tailoring` / `TailoringVoter` (`new Tailoring($owner, new Resume($owner, 'x'))`).

`tests/Security/VerifiedUserVoterTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Auth\Entity\User;
use App\Auth\Security\VerifiedUserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class VerifiedUserVoterTest extends TestCase
{
    public function testVerifiedUserGranted(): void
    {
        $user = new User('v@example.com');
        $user->markVerified();
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        self::assertSame(VoterInterface::ACCESS_GRANTED, (new VerifiedUserVoter())->vote($token, null, ['IS_VERIFIED']));
    }

    public function testUnverifiedUserDenied(): void
    {
        $user = new User('u@example.com');
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        self::assertSame(VoterInterface::ACCESS_DENIED, (new VerifiedUserVoter())->vote($token, null, ['IS_VERIFIED']));
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Security`
Expected: FAIL (classes missing).

- [ ] **Step 2: Implement the voters**

`src/Resume/Security/ResumeVoter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Resume\Security;

use App\Auth\Entity\User;
use App\Resume\Entity\Resume;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Resume> */
final class ResumeVoter extends Voter
{
    public const string VIEW = 'RESUME_VIEW';
    public const string EDIT = 'RESUME_EDIT';
    public const string DELETE = 'RESUME_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true) && $subject instanceof Resume;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->getUser()->getId()->equals($user->getId());
    }
}
```

`src/Tailoring/Security/TailoringVoter.php`: same with `Tailoring`, constants `TAILORING_VIEW|EDIT|DELETE`.

`src/Auth/Security/VerifiedUserVoter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Auth\Security;

use App\Auth\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
final class VerifiedUserVoter extends Voter
{
    public const string IS_VERIFIED = 'IS_VERIFIED';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::IS_VERIFIED === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $user->isVerified();
    }
}
```

- [ ] **Step 3: Run tests, lint, commit**

Run: `docker compose exec php php bin/phpunit tests/Security`
Expected: 10 PASS.

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse
git add -A
git commit -m "feat(security): resume, tailoring and verified-user voters

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 13: Messenger async transport and worker

**Files:**
- Modify: `config/packages/messenger.yaml`
- Create: `src/Shared/Message/PingMessage.php`, `src/Shared/MessageHandler/PingMessageHandler.php`, `src/Shared/Command/PingCommand.php`, `tests/Shared/MessengerTest.php`

**Interfaces:**
- Produces: transports `async` (Redis) and `failed` (Doctrine); routing rule that any `App\*\Message\*` class goes to `async`; the `app:ping` console command that proves the worker loop.

- [ ] **Step 1: Configure Messenger**

`config/packages/messenger.yaml`:

```yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    stream: cvtailor
                    group: workers
                    consumer: '%env(default:HOSTNAME:MESSENGER_CONSUMER_NAME)%'
                retry_strategy:
                    max_retries: 0
            failed: 'doctrine://default?queue_name=failed'
        routing:
            'App\Shared\Message\PingMessage': async
            'Symfony\Component\Mailer\Messenger\SendEmailMessage': async

when@test:
    framework:
        messenger:
            transports:
                async: 'in-memory://'
                failed: 'in-memory://'
```

`max_retries: 0` follows the spec: retries are the responsibility of the LLM client, not Messenger. Run `make:migration` + `migrate` for the `messenger_messages` table (Doctrine failed transport).

- [ ] **Step 2: Failing test**

`tests/Shared/MessengerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Message\PingMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class MessengerTest extends KernelTestCase
{
    public function testPingIsRoutedToAsyncTransport(): void
    {
        self::bootKernel();
        static::getContainer()->get(MessageBusInterface::class)->dispatch(new PingMessage('hi'));

        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertCount(1, $transport->getSent());
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Shared/MessengerTest.php`
Expected: FAIL (class missing).

- [ ] **Step 3: Message, handler, command**

`src/Shared/Message/PingMessage.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Message;

final readonly class PingMessage
{
    public function __construct(public string $text)
    {
    }
}
```

`src/Shared/MessageHandler/PingMessageHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\MessageHandler;

use App\Shared\Message\PingMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PingMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(PingMessage $message): void
    {
        $this->logger->info('PONG: {text}', ['text' => $message->text]);
    }
}
```

`src/Shared/Command/PingCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Message\PingMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:ping', description: 'Dispatch a PingMessage to the async transport')]
final class PingCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('text', InputArgument::OPTIONAL, 'Text to echo', 'ping');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bus->dispatch(new PingMessage((string) $input->getArgument('text')));
        $output->writeln('Dispatched.');

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Verify test and worker**

Run: `docker compose exec php php bin/phpunit tests/Shared/MessengerTest.php`
Expected: PASS.

Run: `docker compose exec php php bin/console app:ping hello && sleep 2 && docker compose logs --tail 20 worker`
Expected: worker log contains `PONG: hello` (acceptance criterion 4).

- [ ] **Step 5: Lint and commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse
git add -A
git commit -m "feat(messenger): async redis transport, failed transport and ping command

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 14: Mercure hub, topic authorizer, dev check page

**Files:**
- Create: `src/Shared/Mercure/MercureTopicAuthorizer.php`, `src/Shared/Controller/MercureCheckController.php`, `templates/mercure_check/index.html.twig`, `assets/controllers/mercure_check_controller.ts`, `tests/Shared/MercureTopicAuthorizerTest.php`
- Modify: `config/packages/mercure.yaml`

**Interfaces:**
- Consumes: `User::getId()`.
- Produces: `MercureTopicAuthorizer::topicFor(Tailoring|Uuid $tailoringId): string` returning `/tailorings/{id}`, `MercureTopicAuthorizer::subscribeCookieFor(User $user, list<string> $topics): void` which sets the `mercureAuthorization` cookie via `Authorization::setCookie`; and the Stimulus controller `mercure-check` used later as the template for `TailoringProgress`.

- [ ] **Step 1: Install and configure**

```bash
docker compose exec php composer require symfony/mercure-bundle --no-interaction
```

`config/packages/mercure.yaml`:

```yaml
mercure:
    hubs:
        default:
            url: '%env(MERCURE_URL)%'
            public_url: '%env(MERCURE_PUBLIC_URL)%'
            jwt:
                secret: '%env(MERCURE_JWT_SECRET)%'
                publish: ['*']
```

- [ ] **Step 2: Failing test**

`tests/Shared/MercureTopicAuthorizerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Auth\Entity\User;
use App\Shared\Mercure\MercureTopicAuthorizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class MercureTopicAuthorizerTest extends KernelTestCase
{
    public function testCookieJwtOnlyContainsRequestedTopics(): void
    {
        self::bootKernel();
        $request = Request::create('/fr/app');
        static::getContainer()->get(RequestStack::class)->push($request);

        $authorizer = static::getContainer()->get(MercureTopicAuthorizer::class);
        $id = Uuid::v7();
        $authorizer->subscribeCookieFor(new User('j@example.com'), [$authorizer->topicFor($id)]);

        $cookie = $request->attributes->get('_mercure_authorization_cookie');
        self::assertNotNull($cookie);
        [, $payload] = explode('.', (string) $cookie->getValue());
        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        self::assertSame(['/tailorings/'.$id], $claims['mercure']['subscribe']);
        self::assertArrayNotHasKey('publish', $claims['mercure']);
    }
}
```

Run: `docker compose exec php php bin/phpunit tests/Shared/MercureTopicAuthorizerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

`src/Shared/Mercure/MercureTopicAuthorizer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Mercure;

use App\Auth\Entity\User;
use App\Tailoring\Entity\Tailoring;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Uid\Uuid;

final readonly class MercureTopicAuthorizer
{
    public function __construct(private Authorization $authorization, private RequestStack $requestStack)
    {
    }

    public function topicFor(Tailoring|Uuid $tailoring): string
    {
        $id = $tailoring instanceof Tailoring ? $tailoring->getId() : $tailoring;

        return '/tailorings/'.$id;
    }

    /** @param list<string> $topics */
    public function subscribeCookieFor(User $user, array $topics): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }
        $this->authorization->setCookie($request, $topics, [], ['sub' => (string) $user->getId()]);
    }
}
```

`Authorization::setCookie` stores the cookie in the request attribute `_mercure_authorization_cookie`; the bundle's response listener copies it to the response. The `$user` parameter is kept so the `sub` claim identifies who subscribed (useful in hub logs).

- [ ] **Step 4: Dev check page**

`src/Shared/Controller/MercureCheckController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Auth\Entity\User;
use App\Shared\Mercure\MercureTopicAuthorizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
#[Route('/app/mercure-check')]
final class MercureCheckController extends AbstractController
{
    #[Route('', name: 'app_mercure_check', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, MercureTopicAuthorizer $authorizer, HubInterface $hub): Response
    {
        $id = Uuid::v7();
        $topic = $authorizer->topicFor($id);
        $authorizer->subscribeCookieFor($user, [$topic]);

        return $this->render('mercure_check/index.html.twig', ['topic' => $topic, 'hubUrl' => $hub->getPublicUrl()]);
    }

    #[Route('/publish', name: 'app_mercure_check_publish', methods: ['POST'])]
    public function publish(HubInterface $hub, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $topic = (string) $request->request->get('topic');
        $hub->publish(new Update($topic, json_encode(['text' => 'Mercure OK at '.date('H:i:s')], \JSON_THROW_ON_ERROR), private: true));

        return new Response('', 204);
    }
}
```

This controller must only answer in dev. Add at the top of both `index()` and `publish()`:

```php
if ('dev' !== $this->getParameter('kernel.environment')) {
    throw $this->createNotFoundException();
}
```

`templates/mercure_check/index.html.twig`:

```twig
{% extends 'layout/app.html.twig' %}
{% block page_title %}Mercure check{% endblock %}
{% block content %}
<twig:Card>
    <div data-controller="mercure-check"
         data-mercure-check-hub-value="{{ hubUrl }}"
         data-mercure-check-topic-value="{{ topic }}"
         data-mercure-check-publish-url-value="{{ path('app_mercure_check_publish') }}"
         data-mercure-check-csrf-value="{{ csrf_token('mercure-check') }}">
        <p>Topic: <code>{{ topic }}</code></p>
        <button type="button" data-action="mercure-check#publish" class="mt-2 text-accent">Publish a test message</button>
        <ul data-mercure-check-target="log" class="mt-4 text-sm"></ul>
    </div>
</twig:Card>
{% endblock %}
```

`assets/controllers/mercure_check_controller.ts`:

```ts
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['log'];
  static values = { hub: String, topic: String, publishUrl: String, csrf: String };
  declare readonly logTarget: HTMLUListElement;
  declare readonly hubValue: string;
  declare readonly topicValue: string;
  declare readonly publishUrlValue: string;
  declare readonly csrfValue: string;
  private source?: EventSource;

  connect() {
    const url = new URL(this.hubValue);
    url.searchParams.append('topic', this.topicValue);
    this.source = new EventSource(url, { withCredentials: true });
    this.source.onmessage = (e) => this.append(JSON.parse(e.data).text);
    this.source.onerror = () => this.append('connection error');
  }
  disconnect() {
    this.source?.close();
  }
  async publish() {
    const body = new URLSearchParams({ topic: this.topicValue, _token: this.csrfValue });
    await fetch(this.publishUrlValue, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } });
  }
  private append(text: string) {
    const li = document.createElement('li');
    li.textContent = `${new Date().toLocaleTimeString()} — ${text}`;
    this.logTarget.append(li);
  }
}
```

Add CSRF verification in `publish()`: `if (!$this->isCsrfTokenValid('mercure-check', (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }`.

- [ ] **Step 5: Verify**

Run: `docker compose exec php php bin/phpunit tests/Shared/MercureTopicAuthorizerTest.php`
Expected: PASS.

Manual (acceptance criterion 5): rebuild assets, open `http://localhost:8080/fr/app/mercure-check`, click the button, a line `Mercure OK at HH:MM:SS` appears without reload. If the EventSource errors, check the hub's `cors_origins` and that the `mercureAuthorization` cookie is set on `localhost` (the cookie domain must match: with `MERCURE_PUBLIC_URL` on `localhost:3000` and the app on `localhost:8080`, the cookie is shared because cookies ignore ports).

- [ ] **Step 6: Lint and commit**

```bash
docker compose exec php vendor/bin/php-cs-fixer fix && docker compose exec php vendor/bin/phpstan analyse && docker compose run --rm node npm run lint
git add -A
git commit -m "feat(mercure): hub configuration, topic authorizer and dev check page

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 15: Security headers, cookies, CLAUDE.md, final README

**Files:**
- Create: `config/packages/nelmio_security.yaml`, `CLAUDE.md`
- Modify: `config/packages/framework.yaml`, `README.md`

- [ ] **Step 1: Install and configure nelmio/security-bundle**

```bash
docker compose exec php composer require nelmio/security-bundle --no-interaction
```

`config/packages/nelmio_security.yaml`:

```yaml
nelmio_security:
    clickjacking:
        paths: { '^/.*': DENY }
    content_type: { nosniff: true }
    referrer_policy: { enabled: true, policies: [ 'strict-origin-when-cross-origin' ] }
    csp:
        enabled: true
        hosts: []
        content_types: []
        enforce:
            default-src: [ 'self' ]
            script-src: [ 'self' ]
            style-src: [ 'self', 'unsafe-inline' ]
            img-src: [ 'self', 'data:' ]
            font-src: [ 'self', 'data:' ]
            connect-src: [ 'self', '%env(MERCURE_PUBLIC_URL)%' ]
            frame-ancestors: [ 'none' ]
            block-all-mixed-content: true

when@prod:
    nelmio_security:
        forced_ssl:
            hsts_max_age: 31536000
            hsts_subdomains: true
```

`'unsafe-inline'` on styles is required by Tailwind's runtime-injected styles in dev and by Turbo progress bar; scripts stay strict. Symfony's web debug toolbar injects inline scripts in dev; add under `when@dev`:

```yaml
when@dev:
    nelmio_security:
        csp:
            enforce:
                script-src: [ 'self', 'unsafe-inline', 'unsafe-eval' ]
```

- [ ] **Step 2: Cookie settings**

In `config/packages/framework.yaml`:

```yaml
framework:
    session:
        handler_id: '%env(REDIS_URL)%'
        cookie_secure: auto
        cookie_samesite: lax
        cookie_httponly: true
    csrf_protection: true
```

Redis sessions need no extra package: Symfony's `RedisSessionHandler` uses the `redis` PHP extension installed in the Dockerfile, and `handler_id` accepts the `redis://redis:6379` DSN directly. Under `when@test` set `handler_id: null` so tests do not need Redis.

Verify: `curl -sI http://localhost:8080/fr/login | grep -i -E "content-security-policy|x-frame-options|x-content-type-options"`
Expected: three headers present.

- [ ] **Step 3: Write CLAUDE.md**

```markdown
# CVTailor — conventions

- Stack: Symfony 7.3 / PHP 8.4, Docker Compose. Run PHP with `docker compose exec php ...`, Node with `docker compose run --rm node ...`.
- Code is organised by domain under `src/` (Auth, Billing, Resume, JobOffer, Tailoring, Shared; later Llm, Export). Entities live in `src/<Domain>/Entity`, mapped in `config/packages/doctrine.yaml`. Never create `src/Entity`.
- Every route is under `/{_locale}` (fr|en). Unprefixed URLs are redirected by `LocaleSubscriber`.
- UI: Twig + Turbo + Stimulus by default; React (`assets/react/controllers`) only for rich stateful islands. Twig components in `templates/components`.
- Translations: `translations/messages+intl-icu.{fr,en}.yaml`; add both languages in the same commit.
- Tests: `make test`. Functional tests extend `App\Tests\Support\AuthenticatedWebTestCase`. Test DB is SQLite, schema rebuilt in `tests/bootstrap.php`.
- Quality gates before commit: `make lint` (php-cs-fixer, phpstan level 8, eslint).
- Messenger retries are disabled on purpose; retry inside services.
- Design docs: `docs/superpowers/specs`, plans: `docs/superpowers/plans`.
```

- [ ] **Step 4: Finalise README** by adding a "Services" table (nginx 8080, mysql 3307, mercure 3000, mailpit 8025), the `app:ping` and `/fr/app/mercure-check` checks, and the `.env` variables list with a pointer to `.env.example`.

- [ ] **Step 5: Full verification (acceptance criteria)**

Run, in order, and record the output:

```bash
make down && docker volume rm cv_mysql_data || true
make up && make db-reset
docker compose run --rm node npm run build
make lint
make test
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/fr
docker compose exec php php bin/console app:ping final && sleep 2 && docker compose logs --tail 5 worker | grep PONG
```

Expected: build OK, lint clean, all PHPUnit and Vitest tests PASS, `200`, and `PONG: final` in the worker logs. Then manually walk criterion 2 (register → Mailpit → verify → logout → login → switch language → change password → delete account) and criterion 5 (Mercure page).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: security headers, redis sessions, CLAUDE.md and README

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

## Self-review against the spec

- Spec §Inclus 1–14 → Tasks: 1 (skeleton, Docker), 2 (quality, CI), 3–4 (entities, enums, migrations), 5–7 (auth incl. rate limiting), 8 (i18n), 9 (front toolchain, HelloIsland, dropdown/confirm), 10 (layouts, components, landing, dashboard), 11 (account page), 12 (voters incl. `IS_VERIFIED`), 13 (Messenger + worker), 14 (Mercure + authorizer + dev check), 15 (nelmio, cookies, CLAUDE.md, README, `.env.example` from Task 1).
- Acceptance criteria 1–6 are verified in Task 15 Step 5; criterion 4 additionally in Task 13 Step 4, criterion 5 in Task 14 Step 5.
- Known deviation: the spec lists `translations/validators+intl-icu` in the file map of the architecture doc; this plan keeps validator messages in `messages` domain via translated constraint messages, which is sufficient for V1.
