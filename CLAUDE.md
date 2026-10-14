# CLAUDE.md

Guide for coding agents working on this repository. It complements, without repeating them:

- [`README.md`](README.md) - what the plugin does and does not do, installation, production notes
- [`CONTRIBUTING.md`](CONTRIBUTING.md) - bringing up the environment, the QA checks, PR
  conventions
- [`docs/`](docs/README.md) - by audience: the back-office guide, installation, assets,
  permissions and theming for integrators, the architecture overview, the tests, and
  `docs/architecture/public-contract.md`, which lists the public surface (keep it and
  `docs/integration/theming.md` in step with the hooks, grids, service ids and routes)

This file only holds what reading the code will not tell you.

The plugin targets Sylius 2.1, 2.2 and 2.3, Symfony 7.4 and 8 (8 with Sylius 2.3 only), PHP 8.3+.
It is the Sylius back office of
[`cyllene-digital/ai-translation-bundle`](https://github.com/CylleneDigital/AiTranslationBundle)
(`^1.0`, from Packagist): the engine, its entities and its `cyllene_digital_ai_translation`
configuration tree live there, not here.

## Where things live

| Path | Role |
|---|---|
| `src/Controller/Admin/` | One invokable controller per route, under `Translation/` and `Suggestion/`; `Screen` is what the actions of a screen share (CSRF intention, flashes, the way back to the list), `Input/` the objects their requests are mapped to |
| `src/Override/ManualOverrideEditor.php` | The rules of a hand edit (unchanged, revert to the baseline, empty refusal, orphan refusal, syntax, placeholders), returned as a `ManualEdit`: the controller only words each outcome |
| `src/Security/` | The permissions (`Permission`) and the voter granting them from the roles |
| `src/Bridge/` | The Sylius adapters of the engine's host-facing interfaces: locales, admin author, channel scopes |
| `src/Grid/` | Sylius grids built in PHP over the engine's data (`CatalogueGrid`, `SuggestionGrid`) and their rendering helpers |
| `src/Scope/ChannelScopeResolver.php` | Which channel (or the global scope) a request works on |
| `src/Transfer/` | Import staging: a file is previewed, then confirmed, before anything is written |
| `src/DependencyInjection/CylleneDigitalSyliusAiTranslationExtension.php` | Loads `config/services.php` (which imports `config/services/*.php`) and **prepends** the Twig hooks (read from `config/twig_hooks/`), the grid action templates, the generation bus, the import staging cache pool and the theme translation roots |
| `config/routes.yaml`, `config/routes/admin.yaml` | What the host imports, and every admin route it puts under `/%sylius_admin.path_name%` |
| `config/twig_hooks/admin.yaml` | The Twig hooks of both screens and of the channel form section |
| `assets/admin/entrypoint.js` | The admin entrypoint a host imports: the stylesheet (`css/admin.css`) and the Stimulus controllers (`js/*-controller.js`), registered as `ait-admin-*` on the plugin's own Stimulus application |
| `tests/Unit/`, `tests/Integration/`, `tests/Functional/` | No kernel / the real container and database, for the services that read the engine / the back office through the HTTP kernel. `tests/SyncsTestSchema.php` is what the last two share |
| `features/`, `tests/Behat/` | Behat scenarios, contexts (`Setup`, `Ui/Admin`, `Ui/Shop`) and pages; configuration in `behat.dist.php`, one suite per feature in `tests/Behat/Resources/suites.php` (a new feature file is added to its list, and carries the tag of its file name) |
| `tests/TestApplication/` | Throwaway Sylius application - test configuration, not plugin code. Its `translations/` and `themes/` directories are the "host project" catalogues the tests browse; `config/packages/sylius_fixtures.yaml` and `src/Fixture/` give the dev shop two channels and translations in every state (the tests load no fixture) |

## Commands

The QA checks are in [`CONTRIBUTING.md`](CONTRIBUTING.md). What it does not say:

- The console is the test app's proxy: `vendor/bin/console` (no `bin/` at the project root).
- DB: the versioned `tests/TestApplication/.env` / `.env.test` carry a generic `DATABASE_URL`
  (`root@127.0.0.1/cyllene_digital_sylius_ai_translation_plugin_%kernel.environment%`, with the
  password `root` in `.env.test`); each
  workstation overrides it in the gitignored `.env.dev.local` / `.env.test.local`. In CI,
  BuildTestAppAction exports its own `DATABASE_URL`, which wins over every `.env` file.
- The public directory served by the test app is `vendor/sylius/test-application/public`.
- `composer.lock` is not versioned: CI resolves each job with its real PHP, Sylius and Symfony
  versions.
- The repo ships an ephemeral Docker stack for contributors (`compose.yml` +
  `compose.override.dist.yml` + `Makefile`): MySQL, PHP, nginx and a headless Chrome on the PHP
  container's network, in the test environment. PHP runs as the host user (`DOCKER_USER`), so that PHP-FPM and the console write `var/`
  with one owner.
- PHPUnit suites: `unit`, `integration`, `functional`, `non-unit` (what CI runs after `unit`) and `all`, the
  default. `phpunit.xml.dist` forces `APP_ENV=test`: a shell exporting `dev` would otherwise run
  the tests on the dev database.

## Flex recipe

The recipe is not in this repository: it lives in `symfony/recipes-contrib`, under
`cyllene-digital/sylius-ai-translation-plugin/<major.minor>/`. It registers the plugin and imports
`config/routes.yaml`; the engine's own recipe registers the engine and copies its
configuration. Update it when the bundle class, the routes or an installation step changes. The
README `Installation` section gives the same steps by hand, for a project without Flex.

## Tests

Four levels: `tests/Unit/` (no kernel), `tests/Integration/` (kernel and real database, no HTTP),
`tests/Functional/` (the same through the HTTP kernel) and Behat (`features/`, `tests/Behat/`: one
suite per feature, selected by the tag of its file name, pages under `Page/Admin` and `Page/Shop`, a
setup context and UI contexts for the admin and the shop, using PHP attributes). The `@javascript` scenarios are the only thing that runs the
Stimulus controllers: they need a Chrome on `127.0.0.1:9222` and the test application served in
the test environment at `BEHAT_BASE_URL`.

The Behat setup context empties `cache.app` before each scenario: Sylius' purge truncates the
tables behind the engine's back, and the overrides of the previous scenario would still be served
from its cache. Anything else that deletes override rows directly has the same effect.

Integration and functional tests sync the schema via `SchemaTool::updateSchema` (no migrations to run before
them) and create the data they need - through entities, never an `INSERT`: how an id is
generated depends on the database, and CI runs MySQL, MariaDB and PostgreSQL. The one database
they leave as they find it is a MariaDB declared as a MySQL, which is what a workstation does to
run the Sylius migrations (`CONTRIBUTING.md`): the engine would ask it for MySQL's collation. CI
declares MariaDB as such and builds its schema from the mapping. The migrations under `tests/TestApplication/src/Migrations/`
version the engine tables for the dev site, as a host project would: the engine ships its Doctrine
mapping, not migrations. They are written for MySQL and MariaDB (the binary collation is chosen
from the server's real version) and abort on another database: the PostgreSQL and MariaDB jobs of
CI create the schema from the mapping (`database_setup: schema`), so only the MySQL jobs run them.

## Known pitfalls

- Do not set the `TRUSTED_PROXIES` env variable: the test app's `public/index.php` uses the
  removed constant `Request::HEADER_X_FORWARDED_ALL` → fatal error. Trusted proxies are set in
  `framework` config (`tests/TestApplication/config/config.yaml`).
- The admin "notifications" widget calls gus.sylius.com on every page, which a test environment
  has no use for (and may not reach): disabled via `sylius_twig_hooks` in the same config.
- Never set `DATABASE_URL` in the environment before running the tests: an external env variable
  would override `.env.test(.local)` and run the tests against the wrong database.
- The catalogue screen costs what the engine costs: for each page, every translation file of
  the view is parsed again (the engine caches them per process, not across requests) and each
  catalogue takes two queries (`getTranslationsForCatalogue()`). The lever is in the engine (a
  persistent file cache, a batch read), not in a plugin-side cache of its rows, which would
  duplicate the engine's inheritance rules.
- Front build (required by the functional tests: admin pages render the Webpack Encore
  entrypoints): in `vendor/sylius/test-application`, `yarn install && yarn build`. The test
  application bundles `assets/admin/entrypoint.js` as `plugin-admin-entry`: rebuild after any
  change under `assets/`, nothing reloads it.
- Sylius needs `ext-sodium`: a PHP without it cannot run `composer install` as is. The image of
  `compose.yml` has it.
- In a Symfony 7.4 resolution, pin the stack (`extra.symfony.require`): Composer otherwise mixes
  Symfony 8 components (`symfony/var-exporter`) into a 7.4 framework and Doctrine proxy generation
  fails on boot. CI restricts Symfony per Sylius line for that reason.

## Invariants not to break

- **Generation runs go through `cyllene_digital_ai_translation.bus`, never `sylius.command_bus`.**
  The Sylius bus wraps handlers in `doctrine_transaction`: a failing chunk would roll back the
  suggestions already billed, and replaying the message would bill the run again.
- **One prefix for everything the plugin names**: `cyllene_digital_sylius_ai_translation` - service
  ids, routes, Twig hooks, grids, translation keys, menu entries, permission attributes, CSRF
  intentions. `cyllene_digital_ai_translation` is the engine's: its configuration key, and the
  generation bus the plugin declares for it (`cyllene_digital_ai_translation.bus`);
  `cyllene_ai_translation` is the engine's Messenger transport.
- **Services are explicit**: one file per area in `config/services/`, ids prefixed with
  `cyllene_digital_sylius_ai_translation.` (the controllers keep their class as id, the routes
  name them that way), no autowiring nor autoconfiguration (the Symfony
  recommendation for a reusable bundle). So a new constructor argument is added to its definition, a class
  implementing `ResetInterface` needs the `kernel.reset` tag by hand, and so do
  `kernel.event_listener`, `twig.extension`, `form.type_extension`, `sylius.grid` and
  `sylius.grid_data_provider`. No `#[Autowire]`, `#[AsEventListener]` or `#[AsDecorator]` in
  `src/`: they would do nothing. `#[AsGrid]` stays, `AbstractGrid` reads it itself.
- **Controller routes stay in `config/routes/admin.yaml`**, not in attributes: a controller
  tagged `routing.controller` would have its routes registered a second time by the host's own
  import, outside the admin prefix. Their input comes through `#[MapQueryString]`,
  `#[MapRequestPayload]` (`Controller/Admin/Input/`) and `#[MapEntity]`; those objects carry no
  validation constraint on purpose - a well-typed value the plugin refuses answers with a flash
  and a redirect, where a constraint would answer 422. A value of the wrong type (`key[]=x`,
  `all=maybe`), which only a hand-built request sends, gets Symfony's own answer: 422 for a
  payload, 404 for a query string. The estimate's JavaScript shows its generic error then.
- **CSRF is checked by `Screen::validateCsrf()`, not `#[IsCsrfTokenValid]`**: the attribute throws an
  authentication exception, which sends a logged-in admin to the login page instead of a 403.
  One request is answered before that check, on purpose: an import PHP emptied for its size (over
  `post_max_size`, the token goes with the file). No override is read nor written there: it tells
  the admin why, and the only state it touches is the import that was waiting, which it withdraws
  like any refused upload - the most a forged request could do on that path.
- **The engine interfaces are re-aliased with plain aliases** in `config/services/bridge.php`, not
  `#[AsAlias]`: the engine already aliases them to its defaults and the plugin, loaded after it,
  must win deterministically. The engine bundle is therefore registered **before** the plugin.
- **The menu entry is added at priority `-1024`**, after the usual host listeners: a host that
  reorders its root menu with a fixed list would otherwise hit KnpMenu's "order does not contain
  all children".
- **One permission per kind of consequence** (`Security/Permission`): view, edit, import, review,
  generate, each granted by its own role and all of them by `ROLE_AI_TRANSLATION_MANAGE`
  (`Security/PermissionVoter`). A new action is a new controller class, which takes the
  permission of what it does (`#[IsGranted(Permission::X->value)]`); every permission lets its
  holder read. What the admin may not do is not rendered either: grids, menu and templates ask
  `is_granted('cyllene_digital_sylius_ai_translation.<permission>')`.
- **An open Sylius modal is not where its template put it**: the confirmation modal component is
  moved out of its parent when shown, so a Stimulus controller on an ancestor reaches neither its
  targets nor its actions there. The generation controller recognises the modal's confirm button
  as the `submitter` of the form instead (`data-generation-confirmed`). The edit and review
  modals carry their controller on the modal element itself.
- **No script in the templates**: no `<script>`, no `on*=` attribute. A behaviour is a Stimulus
  controller registered in `assets/admin/entrypoint.js` (`tests/Unit/AdminAssetsTest.php` checks
  both, the suite having no browser). The modals are filled from the `data-*` attributes of
  their trigger, through Bootstrap's `show.bs.modal` - listened to in `connect()`, since the
  event name has dots.
- **No file is ever written, nothing AI-generated is applied without a human**: overrides are
  database deltas, and a suggestion only becomes one through an explicit approval.
- **Every engine import is listed** in `tests/Unit/EngineContractTest.php`; the test fails on a
  new one and on a stale one. Only use what the engine's `docs/public-contract.md` names, method
  by method: the rest is internal and changes in minor versions. The objects its services
  return, read without an import, are in `RETURNED_AND_READ`, checked by reflection. One
  container parameter of the engine is read too, `cyllene_digital_ai_translation.default_provider`
  (the provider the generation form presets): the container fails to compile if it goes.
- **No test builds an engine service by hand**: its constructors are not contract. A test that
  needs the real engine takes it from the container (`tests/Integration/`, `tests/Functional/`, with the
  `SyncsTestSchema` trait); one that does not stubs `TranslationManagerInterface`.
- The public surface - the bundle class and its namespace, the admin routes, the Twig hooks and
  templates, the roles, the menu entry and the generation bus - only breaks in a major version,
  with an entry in `UPGRADE.md`.

## Keeping the docs in step

When a behaviour changes, these say it too:

- the compatibility (Sylius, Symfony, PHP, databases): `composer.json`, the README,
  `docs/integration/installation.md`, the support policy of `docs/architecture/public-contract.md`, `CONTRIBUTING.md` and the
  matrix of `.github/workflows/build.yaml`;
- the public surface (routes, hooks, grids, replaceable services, roles): `docs/architecture/public-contract.md`,
  `docs/integration/theming.md`, `docs/integration/permissions.md`, and `UPGRADE.md` when it breaks;
- the layers and the flows: `docs/architecture/overview.md`; the test levels: `docs/testing/tests.md`;
- the screens: `docs/admin/user-guide.md`, and the README screenshots (`docs/assets/`) for a visible
  change;
- the checks a contributor runs: `CONTRIBUTING.md`, in the order of `build.yaml`, and the
  scenario counts it quotes (also in the README).

## What does not belong here

The translation engine (providers, overrides, caches, console commands, configuration tree) lives
in `cyllene-digital/ai-translation-bundle`. Entity content (product names, descriptions) is Sylius
translation data with its own tooling.
