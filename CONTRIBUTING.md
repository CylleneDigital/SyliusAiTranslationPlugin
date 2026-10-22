# Contributing

Thanks for your interest in this plugin. Issues and pull requests are welcome.

**A security flaw is not reported through an issue** - see [SECURITY.md](SECURITY.md).

## Setting up the development environment

Fork `CylleneDigital/SyliusAiTranslationPlugin` on GitHub, then clone your fork and keep the
original repository as `upstream`:

```bash
git clone git@github.com:<your-account>/SyliusAiTranslationPlugin.git
cd SyliusAiTranslationPlugin
git remote add upstream git@github.com:CylleneDigital/SyliusAiTranslationPlugin.git
composer install
```

The plugin is exercised against Sylius through [`sylius/test-application`](https://github.com/Sylius/TestApplication)
(configured under `tests/TestApplication/`, with the engine bundle, its providers and the
"host project" translations the tests browse).

Unit tests do not need a database. Integration, functional and Behat tests boot the test-application kernel and
need a database.

With PHP, a database and Node on the host, one command prepares the test application for the
environment in `APP_ENV`: it **drops and recreates the database**, runs the migrations, loads the
fixtures, builds the front end and runs `assets:install`:

```bash
composer run test-app-init
```

`make test-app-init` (Docker stack, below) is lighter: it creates the database if missing, runs the
migrations and `assets:install`, without fixtures or front-end build (`make test-app-frontend`).

### Database on the host

`tests/TestApplication/.env` points at `mysql://root@127.0.0.1/…` and `tests/TestApplication/.env.test`
(test environment) at `mysql://root:root@127.0.0.1/…`, both on the default port 3306. To use the
Compose MySQL below instead (published on host port **3307**, user `root`, empty password), create
`tests/TestApplication/.env.test.local` (gitignored):

```dotenv
DATABASE_URL=mysql://root@127.0.0.1:3307/cyllene_digital_sylius_ai_translation_plugin_%kernel.environment%
```

The integration and functional tests also create the schema themselves, from the mapping: on MySQL and PostgreSQL
an empty database is enough for them.

On MariaDB, declare MariaDB as such (`serverVersion=11.4.0-MariaDB`) and run Sylius 2.3.1 or
later: with DBAL 4, Sylius 2.3.0 marks its core migrations as executed but skips them there
(Sylius#19272). On PostgreSQL, skip the migrations - the ones of
the engine tables (`tests/TestApplication/src/Migrations/`) are written for MySQL and MariaDB - and
create the schema from the mapping:

```bash
vendor/bin/console doctrine:schema:update --force
```

### AI providers

`tests/TestApplication/.env` carries dummy provider keys: the back office works without real ones
and a generation fails cleanly. To exercise a real generation, set `DEEPL_API_KEY` in
`tests/TestApplication/.env.dev.local`, or declare a provider of your own (a private gateway, a
paid account) in `tests/TestApplication/config/local.yaml`, copied from `local.yaml.dist`: neither
file is versioned, and no key or private URL ever goes into one that is.

A generation is queued: run the worker for the suggestions to arrive.

```bash
vendor/bin/console messenger:consume cyllene_ai_translation
```

### Docker stack

Ephemeral stack: MySQL + PHP + nginx + a headless Chrome sharing the PHP container's network.
`compose.override.yml` is gitignored: copy it from the dist file (or let `make docker-up` create
it). PHP runs as your user (`DOCKER_USER`, set by the Makefile), so `var/` stays yours.

```bash
composer install                 # on the host, or after docker-up: docker compose exec php composer install
make docker-up                   # copies compose.override.dist.yml if missing, then up -d
                                 # MySQL is published on host port 3307 (3306 is often taken)
make test-app-init               # database + migrations + assets:install (into test-application/public)
make test-app-frontend           # yarn build in vendor/sylius/test-application (admin and shop @ui)
make behat-docker                # every scenario, the @javascript ones included
make docker-down                 # stop and remove volumes when finished
```

The test application is then served in the test environment at <http://127.0.0.1:8080/>. Outside
`make`, pass your user yourself: `DOCKER_USER="$(id -u):$(id -g)" docker compose up -d`.

### Dev shop

The test application loads the Sylius `default` fixture suite plus, from
`tests/TestApplication/config/packages/sylius_fixtures.yaml`, what the plugin needs to be looked
at: two channels that do not expose the same locales, and translations in every state.

| Channel | Locales | Reached on |
|---|---|---|
| `FASHION_WEB` | `en_US` (default), `fr_FR`, `de_DE` | `SYLIUS_FIXTURES_HOSTNAME` (default: `localhost`) |
| `B2B_WEB` | `en_US` (default), `fr_FR` | `b2b.` + that host, or `?_channel_code=B2B_WEB` in debug mode |

```bash
vendor/bin/console sylius:fixtures:load default -n   # empties the database first
```

On top of the Sylius sample data, the `ai_translation` fixture
(`tests/TestApplication/src/Fixture/AiTranslationFixture.php`) writes overrides for every channel
and for one, pending suggestions (a failed one included), a context for the AI on `B2B_WEB` and a
few runs in the journal. So the channel selector of both screens has something to show in each
position: the French welcome is common, `B2B_WEB` has its own, German only exists - and is only
suggested - on `FASHION_WEB`.

The translations themselves are the test application's (`tests/TestApplication/translations`, and
the `SeasonalTheme` theme under `tests/TestApplication/themes`), written in English and partly in
French.

### Behat

46 scenarios under `features/`, one suite per feature (`tests/Behat/Resources/suites.php`): 34 on the back
office (`features/admin/`) and 12 on what the shop ends up showing (`features/shop/`). 32 use
the Mink `symfony` session (no browser); 14 are tagged `@javascript` and drive a real Chrome: the
edit and review modals, the cost estimate, the confirmation of a run on every catalogue, the bulk
approval, the channel selector and the confirmation of the orphan purge - everything a Stimulus
controller does. That last confirmation is a native dialog, which would block the click: its
scenarios answer it ahead, by replacing `window.confirm`.

The [Docker stack](#docker-stack) runs them all with `make behat-docker`. On the host, the
scenarios without a browser:

```bash
vendor/bin/behat --strict --no-interaction -f progress --tags='~@javascript'
```

`@javascript` scenarios need, as configured in `behat.dist.php`:

1. Chrome headless with remote debugging on `127.0.0.1:9222`:

   ```bash
   google-chrome --headless=new --remote-debugging-port=9222 --no-sandbox
   ```

2. the test application served **in the test environment** at `BEHAT_BASE_URL`
   (`http://127.0.0.1:8080/` in `tests/TestApplication/.env`), after `make test-app-init` /
   `make test-app-frontend` or their host equivalents:

   ```bash
   APP_ENV=test php -d variables_order=EGPCS -S 127.0.0.1:8080 -t vendor/sylius/test-application/public
   ```

   The Compose nginx also publishes port 8080: stop the Docker stack first, or serve on another
   port and set the same URL as `BEHAT_BASE_URL` in `tests/TestApplication/.env.test.local`.

Then run the whole suite:

```bash
vendor/bin/behat --strict --no-interaction -f progress
```

Failure screenshots and pages land in `etc/build/`. CI runs the scenarios without a browser on
every job and the `@javascript` ones on the primary job
([`SyliusLabs/BuildTestAppAction`](https://github.com/SyliusLabs/BuildTestAppAction) with
`e2e_js: "yes"` starts Chrome and the web server).

The scenarios read the test application's translations (`tests/TestApplication/translations`):
`app.dashboard` exists in English and French, `app.welcome` in English only. The test application
renders `app.welcome` on its shop homepage (`tests/TestApplication/templates/shop/welcome.html.twig`):
that is the line the shop scenarios read to check that an override, an approved suggestion or an
import reaches the storefront - and that a pending or rejected suggestion does not. The scenarios
on several channels give each one a hostname (`web.test`, `b2b.test`) and read the shop on it: with
more than one channel, the host is what picks the channel.

A step argument named `:channel` is turned into a channel by Sylius' transformer: the plugin's
steps take a channel **code**, as `:channelCode`.

The administrators are created by the plugin's own steps ("I am logged in as an administrator managing the
translations", "... who may only "review" the translations"): Sylius' usual step gives no role of
the plugin, and both screens would answer 403.

## Proposing a change

`main` is protected. Every change goes through a pull request from a fork, a one-line documentation
fix included, and the maintainers work the same way.

1. Branch off an up-to-date `main`, one topic per branch:

   ```bash
   git fetch upstream
   git switch -c fix/short-description upstream/main
   ```

2. Make the change, with its test (see [Conventions](#conventions)), and run the
   [checks](#what-has-to-pass-before-a-pull-request): they are the ones the CI runs.
3. Commit in **English**, conventional form (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `ci:`,
   `chore:`), imperative mood, then push the branch to your fork:

   ```bash
   git commit -m "fix: keep the channel when a suggestion is approved"
   git push -u origin fix/short-description
   ```

4. Open the pull request against `main` of `CylleneDigital/SyliusAiTranslationPlugin` (GitHub
   offers *Compare & pull request* on your fork). Use the commit message as the title, and fill in
   the template: what the pull request does (why, what it changes for an admin, how you checked it)
   and the points to watch. Add a before / after screenshot for a visible change to the back
   office, and `Closes #<issue>` when it fixes an issue.
5. If `main` moves before the merge, rebase rather than merge it into your branch:

   ```bash
   git fetch upstream
   git rebase upstream/main
   git push --force-with-lease
   ```

Pushing to your fork runs nothing: the workflows start when the pull request is opened. On a first
contribution they also wait for a maintainer to approve the run, GitHub's default on public
repositories, not something you did wrong.

Two checks have to be green, and the branch up to date with `main`, before a pull request can be
merged:

| Check | What it covers |
|---|---|
| **`Build complete`** | Every job of the matrix (Sylius ~2.1 / ~2.2 / ~2.3 × PHP 8.3 to 8.5 on MySQL 8.4, Symfony within what each Sylius supports, plus MySQL 8.0, MariaDB 10.11 / 11.4 and PostgreSQL 15 to 17 on Sylius 2.3) and the oldest engine release the constraint allows passed the CI checks below |
| **`Composer audit`** | Known vulnerabilities in dependencies, on the newest resolution and on the Sylius 2.1 one |

## What has to pass before a pull request

The CI checks, in the order CI runs them (`.github/workflows/build.yaml`), with `APP_ENV=test` and a
database built from the migrations (`make test-app-init`):

```bash
composer validate --ansi --strict
vendor/bin/console lint:container --env=test
vendor/bin/console doctrine:schema:validate --skip-sync
vendor/bin/phpstan analyse --ansi --no-progress --memory-limit=1G
vendor/bin/ecs check --ansi --no-progress-bar
vendor/bin/console lint:twig templates --env=test
vendor/bin/console lint:yaml translations --env=test
vendor/bin/phpunit --colors=always --testsuite=unit
vendor/bin/phpunit --colors=always --testsuite=non-unit
vendor/bin/behat --colors --strict --no-interaction -f progress --tags='~@javascript'
vendor/bin/behat --colors --strict --no-interaction -f progress --tags='@javascript'
```

PHPStan, ECS, the template and translation lints, the `@javascript` scenarios and the code coverage only run on the primary
job (Sylius 2.3, PHP 8.4, MySQL 8.4). Before Sylius 2.3, the CI keeps `doctrine/orm` below 3.7
(Sylius 2.1 mappings fail its validation) and Behat on 3 (Sylius 2.1 and 2.2 declare their steps in
annotations, which Behat 4 no longer reads). The
PostgreSQL jobs create the schema from the mapping instead of running the migrations. Behat
prerequisites: [Behat](#behat).

Make targets: `make phpunit`, `make test-unit`, `make test-integration`, `make test-functional`, `make behat`,
`make behat-docker`, `make docker-up`, `make docker-down`, `make test-app-init`,
`make test-app-frontend`, `make lint-container`, `make phpstan`, `make ecs`.

`vendor/bin/ecs check --fix` fixes the style automatically. The standard is
[`sylius-labs/coding-standard`](https://github.com/Sylius-Labs/CodingStandard), the one used by
Sylius plugins, do not add personal rules to it.

## Conventions

- **PHPStan level `max`** on `src/` and `tests/` (`phpstan.neon`, test application sources
  included), with its Doctrine and Webmozart Assert extensions, loaded by
  `phpstan/extension-installer`. Lowering the level or adding `ignoreErrors` needs a justification
  in the pull request.
- **ECS** via `sylius-labs/coding-standard` (`ecs.php`), on `src/`, `tests/` and the test
  application sources.
- **Tests are mandatory** for bug fixes: the test must fail before the fix. A change to a Stimulus
  controller comes with a `@javascript` scenario: nothing else runs that code. Prefer a Behat
  scenario when the bug is visible in the back office or in the shop.
- Test data is created through the entities, never an `INSERT`: how an id is generated depends on
  the database, and CI runs MySQL, MariaDB and PostgreSQL.
- Services are defined explicitly in `config/services/`, without autowiring
  ([why](docs/adr/0003-explicit-services-no-autowiring.md)).
- Update **`UPGRADE.md`** when the public surface changes; the change itself is described in the
  pull request, which feeds the release notes. The public surface is listed in
  [docs/architecture/public-contract.md](docs/architecture/public-contract.md); it only breaks in a major version.
- Do not commit API keys, customer data or pre-production URLs.
- The codebase is written in **English** (code, comments, commits).
- The layers and the main flows are in [docs/architecture/overview.md](docs/architecture/overview.md);
  the test strategy in [docs/testing/tests.md](docs/testing/tests.md).
- Why the plugin is built the way it is, and what was left as it is on purpose, is in the
  [decision records](docs/adr/README.md): read the one a change touches first. A pull request
  that takes a decision, or reverses one, adds a record - an accepted record is superseded, not
  edited.

## Depending on the engine

The plugin's whole dependency on `cyllene-digital/ai-translation-bundle` is listed in
`tests/Unit/EngineContractTest.php`: a pull request that imports another engine class has to add
it there, which is the moment to check that the engine's
[public contract](https://github.com/CylleneDigital/AiTranslationBundle/blob/main/docs/public-contract.md)
names it, method by method. What that page does not list is internal and may change in a minor
version: ask the engine to list it rather than rely on it.

Two CI jobs watch the two ends of the `^2.0` constraint:

- `Oldest supported engine` (part of `Build complete`) runs the container lint, PHPStan and
  PHPUnit on the lowest release the constraint allows. A pull request that needs a newer engine raises the constraint.
- `Engine drift` (weekly, and on every push to `main`) runs the same on the engine's unreleased `main`
  and opens an issue when the scheduled run fails. It is not a pull request check.

To run either locally:

```bash
composer update cyllene-digital/ai-translation-bundle --prefer-lowest --no-interaction --no-progress
composer require --no-update --no-interaction "cyllene-digital/ai-translation-bundle:dev-main" && composer update cyllene-digital/ai-translation-bundle --no-interaction --no-progress
```

## What does not belong here

The translation engine - providers, overrides, caches, console commands, configuration tree -
lives in [`cyllene-digital/ai-translation-bundle`](https://github.com/CylleneDigital/AiTranslationBundle):
this plugin only carries its Sylius back office. Entity content (product names, descriptions) is
Sylius translation data with its own tooling, and stays out of scope.
