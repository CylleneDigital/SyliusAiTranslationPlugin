# CLAUDE.md

Guide for coding agents working on this repository. It complements, without repeating them:

- [`README.md`](README.md) - what the plugin does and does not do, installation, production notes
- [`CONTRIBUTING.md`](CONTRIBUTING.md) - bringing up the environment, the QA checks, PR
  conventions
- [`docs/`](docs/README.md) - by audience: the back-office guide, installation, assets,
  permissions and theming for integrators, the architecture overview, the tests, and
  `docs/architecture/public-contract.md`, which lists the public surface (keep it and
  `docs/integration/theming.md` in step with the hooks, grids, service ids and routes)
- [`docs/adr/`](docs/adr/README.md) - the decision records: why each invariant below holds

This file only holds what reading the code will not tell you.

The plugin targets Sylius 2.1, 2.2 and 2.3, Symfony 7.4 and 8 (8 with Sylius 2.3 only), PHP 8.3+.
It is the Sylius back office of
[`cyllene-digital/ai-translation-bundle`](https://github.com/CylleneDigital/AiTranslationBundle)
(`^1.4.1`, from Packagist): the engine, its entities and its `cyllene_digital_ai_translation`
configuration tree live there, not here.

## Where things live

| Path | Role |
|---|---|
| `src/Controller/Admin/` | One invokable controller per route, under `Translation/` and `Suggestion/`; `Screen` is what the actions of a screen share (CSRF intention, flashes, the way back to the list), `Input/` the objects their requests are mapped to |
| `src/Override/ManualOverrideEditor.php` | The rules of a hand edit (unchanged, revert to the baseline, empty refusal, orphan refusal, syntax, placeholders), returned as a `ManualEdit`: the controller only words each outcome |
| `src/Security/` | The permissions (`Permission`) and the voter granting them from the roles |
| `src/Bridge/` | The Sylius adapters of the engine's host-facing interfaces: locales, admin author, channel scopes and their locales |
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
they leave as they find it is a MariaDB declared as a MySQL, which is what a workstation had to do
to run the Sylius 2.3.0 migrations under DBAL 4 (fixed in 2.3.1 by Sylius#19272): the engine would
ask it for MySQL's collation. CI declares MariaDB as such. The migrations under `tests/TestApplication/src/Migrations/`
version the engine tables for the dev site, as a host project would: the engine ships its Doctrine
mapping, not migrations. They are written for MySQL and MariaDB (the binary collation is chosen
from the server's real version) and abort on another database: the PostgreSQL jobs of CI create
the schema from the mapping (`database_setup: schema`), the MySQL and MariaDB jobs run them.

## Known pitfalls

- Do not set the `TRUSTED_PROXIES` env variable: the test app's `public/index.php` uses the
  removed constant `Request::HEADER_X_FORWARDED_ALL` → fatal error. Trusted proxies are set in
  `framework` config (`tests/TestApplication/config/config.yaml`).
- The admin "notifications" widget calls gus.sylius.com on every page, which a test environment
  has no use for (and may not reach): disabled via `sylius_twig_hooks` in the same config.
- Never set `DATABASE_URL` in the environment before running the tests: an external env variable
  would override `.env.test(.local)` and run the tests against the wrong database.
- The catalogue screen costs what the engine costs: the parsed translation files come from the
  engine's `cyllene_digital_ai_translation.translation_files_cache` pool, the overrides of the
  browsed locale from one grouped read for the whole view
  (`OverrideReader::getTranslationsForCatalogues()`), plus one read per reference locale;
  `tests/Integration/View/TranslationViewFactoryTest.php` checks that the number of catalogues
  does not change the count. The lever is in the engine, not in a plugin-side cache of its rows
  ([0014](docs/adr/0014-no-plugin-side-cache-of-catalogue-rows.md)).
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

Each rule is here in full, with the names the code uses; its reasons are in the decision record it
links ([`docs/adr/`](docs/adr/README.md)). Read the record before changing a rule, and write a new
record rather than editing an accepted one. A record names what its decision is about (a bus, a
priority, a status code), never a file path, a version or a list that moves with the code: this
file does.

- **Generation runs go through `cyllene_digital_ai_translation.bus`, never `sylius.command_bus`**
  ([0001](docs/adr/0001-generation-runs-on-a-dedicated-bus.md)).
- **One prefix for everything the plugin names**: `cyllene_digital_sylius_ai_translation` - service
  ids, routes, Twig hooks, grids, translation keys, menu entries, permission attributes, CSRF
  intentions. `cyllene_digital_ai_translation` is the engine's: its configuration key, and the
  generation bus the plugin declares for it (`cyllene_digital_ai_translation.bus`);
  `cyllene_ai_translation` is the engine's Messenger transport
  ([0002](docs/adr/0002-one-prefix-for-everything-the-plugin-names.md)).
- **Services are explicit**: one file per area in `config/services/`, ids prefixed with
  `cyllene_digital_sylius_ai_translation.` - **except the controllers, which keep their class as
  id**: the routes name them that way, and a controller under a prefixed id is not found. No
  autowiring nor autoconfiguration: a new constructor argument is added to its definition, a class
  implementing `ResetInterface` needs the `kernel.reset` tag by hand, and so do
  `kernel.event_listener`, `twig.extension`, `form.type_extension`, `sylius.grid` and
  `sylius.grid_data_provider`. No `#[Autowire]`, `#[AsEventListener]` or `#[AsDecorator]` in
  `src/`: they would do nothing. `#[AsGrid]` stays, `AbstractGrid` reads it itself
  ([0003](docs/adr/0003-explicit-services-no-autowiring.md)).
- **A `Bundle` with `SyliusPluginTrait` and a classic `Extension`**, not `AbstractBundle`
  ([0004](docs/adr/0004-bundle-class-with-sylius-plugin-trait.md)).
- **One invokable controller per route**: a new action is a new controller class
  ([0005](docs/adr/0005-one-invokable-controller-per-route.md)).
- **Controller routes stay in `config/routes/admin.yaml`**, not in attributes. Their input comes
  through `#[MapQueryString]`, `#[MapRequestPayload]` (`Controller/Admin/Input/`) and
  `#[MapEntity]`; those objects carry no validation constraint on purpose - a well-typed value the
  plugin refuses answers with a flash and a redirect (the estimate, which the generation form's
  JavaScript calls, with a 422 and its message in JSON). A value of the wrong type (`key[]=x`,
  `all=maybe`), which only a hand-built request sends, gets Symfony's own answer: 422 for a
  payload, 404 for a query string
  ([0006](docs/adr/0006-routes-in-yaml-inputs-without-constraints.md)).
- **CSRF is checked by `Screen::validateCsrf()`, not `#[IsCsrfTokenValid]`**. Two controllers do
  it differently, on purpose: `RejectController` also accepts the per-row token of Sylius' grid
  delete modal (requiring the screen's one there answers 403 to a rejection from the grid), and
  `ImportController` answers an import PHP emptied for its size before the check
  ([0007](docs/adr/0007-csrf-checked-by-screen.md)).
- **The engine interfaces are re-aliased with plain aliases** in `config/services/bridge.php`, not
  `#[AsAlias]`, and the engine bundle is registered **before** the plugin
  ([0008](docs/adr/0008-engine-interfaces-re-aliased-with-plain-aliases.md)).
- **The menu entry is added at priority `-1024`**
  ([0009](docs/adr/0009-menu-entry-at-priority-minus-1024.md)).
- **One permission per kind of consequence** (`Security/Permission`): view, edit, import, review,
  generate, each granted by its own role and all of them by `ROLE_AI_TRANSLATION_MANAGE`
  (`Security/PermissionVoter`). A controller takes the permission of what it does
  (`#[IsGranted(Permission::X->value)]`); **every permission lets its holder read**. What the
  admin may not do is not rendered either: grids, menu and templates ask
  `is_granted('cyllene_digital_sylius_ai_translation.<permission>')`
  ([0010](docs/adr/0010-one-permission-per-kind-of-consequence.md)).
- **An open Sylius modal is not where its template put it**: the confirmation modal component is
  moved out of its parent when shown, so a Stimulus controller on an ancestor reaches neither its
  targets nor its actions there. The generation controller recognises the modal's confirm button
  as the `submitter` of the form instead (`data-generation-confirmed`). The edit and review
  modals carry their controller on the modal element itself.
- **No script in the templates**: no `<script>`, no `on*=` attribute. A behaviour is a Stimulus
  controller registered in `assets/admin/entrypoint.js` (`tests/Unit/AdminAssetsTest.php` checks
  both, the suite having no browser). The modals are filled from the `data-*` attributes of
  their trigger, through Bootstrap's `show.bs.modal` - listened to in `connect()`, since the
  event name has dots ([0011](docs/adr/0011-no-script-in-templates.md)).
- **No file is ever written, nothing AI-generated is applied without a human**: overrides are
  database deltas, and a suggestion only becomes one through an explicit approval
  ([0012](docs/adr/0012-nothing-written-to-files-nothing-applied-without-a-human.md)).
- **Every engine import is listed** in `tests/Unit/EngineContractTest.php`, which reads `src/` and
  `config/`; the test fails on a new one and on a stale one. Only use what the engine's
  `docs/public-contract.md` names, method by method. The objects its services return, read
  without an import, go in `RETURNED_AND_READ`, checked by reflection. One container parameter of
  the engine is read too, `cyllene_digital_ai_translation.default_provider` (the provider the
  generation form presets): the container fails to compile if it goes
  ([0013](docs/adr/0013-only-the-engine-public-contract.md)).
- **No test builds an engine service by hand**: its constructors are not contract. A test that
  needs the real engine takes it from the container (`tests/Integration/`, `tests/Functional/`,
  with the `SyncsTestSchema` trait); one that does not stubs `TranslationManagerInterface`.
- **No server-side lock on a generation in the plugin**: the guard against double billing is the
  engine's, written into its public contract
  ([0024](docs/adr/0024-double-billing-guard-is-the-engines-contract.md)). The engine checks the
  channel when a run starts and before each chunk: `ChannelRegistry` keeps the channels for a
  whole request, but one second at most outside one (a worker, the console), or a channel
  deleted during a run would not stop it.
- **A deleted channel is purged on `sylius.channel.post_delete`, at priority `-1024`**, never on
  `pre_delete` nor in a Doctrine listener: `ChannelDeletionListener` calls the engine's
  `ScopePurger` once Sylius has committed the deletion and the host's listeners have run
  (overrides, suggestions, prompt context; the journal stays), and logs a purge that fails
  (an `\Exception`, not an `\Error`) instead of throwing; `cyllene:ai-translation:purge-scopes` catches what it
  missed ([0025](docs/adr/0025-a-deleted-channel-takes-its-translations-along.md)).
- The public surface - the bundle class and its namespace, the admin routes, the Twig hooks and
  templates, the roles, the menu entry and the generation bus - only breaks in a major version,
  with an entry in `UPGRADE.md`.

What was left as it is on purpose - no API, no live tracking of a generation, `pending_count`, the
bulk action renderer decorator, the duplicated syntax labels, the uncached channel map, the
`.gitignore` - is in the records 0015 to 0021: read them before
"fixing" one.

## Keeping the docs in step

When a behaviour changes, these say it too:

- the compatibility (Sylius, Symfony, PHP, databases): `composer.json`, the README,
  `docs/integration/installation.md`, the support policy of `docs/architecture/public-contract.md`, `CONTRIBUTING.md` and the
  matrix of `.github/workflows/build.yaml`;
- the public surface (routes, hooks, grids, replaceable services, roles): `docs/architecture/public-contract.md`,
  `docs/integration/theming.md`, `docs/integration/permissions.md`, and `UPGRADE.md` when it breaks;
- the layers and the flows: `docs/architecture/overview.md`; the test levels: `docs/testing/tests.md`;
- a decision, taken or reversed: a new record in `docs/adr/` and its line in `docs/adr/README.md`;
- the screens: `docs/admin/user-guide.md`, and the README screenshots (`docs/assets/`) for a visible
  change;
- the checks a contributor runs: `CONTRIBUTING.md`, in the order of `build.yaml`, and the
  scenario counts it quotes (also in the README).

## What does not belong here

The translation engine (providers, overrides, caches, console commands, configuration tree) lives
in `cyllene-digital/ai-translation-bundle`. Entity content (product names, descriptions) is Sylius
translation data with its own tooling.
