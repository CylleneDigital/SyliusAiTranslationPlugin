# Architecture overview

## Plugin purpose

The plugin is the Sylius back office of
[`cyllene-digital/ai-translation-bundle`](https://github.com/CylleneDigital/AiTranslationBundle),
"the engine". The engine reads the translation files, stores the overrides, talks to the AI
providers and decorates the translator; it knows nothing of Sylius. The plugin gives it two admin
screens, the Sylius channels as scopes, the Sylius locales and the logged-in admin as author.

It owns no entity, no table and no configuration tree: the data and the `cyllene_digital_ai_translation`
key are the engine's.

## Technical stack

| Area | Choice |
|---|---|
| Screens | Sylius index pages built from Twig hooks, their lists as Sylius grids configured in PHP |
| Front end | One Webpack Encore entrypoint the host imports: a stylesheet and Stimulus controllers, [no script in the templates](../adr/0011-no-script-in-templates.md) |
| Asynchronous work | The engine's Messenger message, dispatched on [a dedicated bus](../adr/0001-generation-runs-on-a-dedicated-bus.md) |
| Services | Defined explicitly, one file per area in `config/services/`, [without autowiring](../adr/0003-explicit-services-no-autowiring.md) |
| Security | The admin firewall, plus [one permission per kind of action](../adr/0010-one-permission-per-kind-of-consequence.md), granted by a voter from roles |

## Code layers (`src/`)

| Path | Role |
|---|---|
| `Controller/Admin/` | One invokable controller per route, under `Translation/` and `Suggestion/`. `Screen` holds what the actions of a screen share: its CSRF intention, its flashes and the way back to its list. `Input/` holds the objects the requests are mapped to |
| `Bridge/` | The Sylius adapters of the engine's host-facing interfaces: locales, author, channel scopes |
| `Scope/` | Which channel a request works on (`ChannelScopeResolver`), and the channels read once per request (`ChannelRegistry`) |
| `Grid/` | The two grids, the data provider of the catalogue one (its rows are computed, not entities), their filter choices and the bulk action renderer |
| `View/` | The rows of the catalogue grid and of the export, built from the engine's data |
| `Override/` | The rules of a hand edit: unchanged, revert to the baseline, empty refusal, orphan refusal, syntax, placeholders |
| `Transfer/` | Import staging: a file is previewed, then confirmed, before anything is written |
| `Security/` | The permissions and the voter granting them from the roles |
| `Form/Extension/` | The "AI translation" field of the channel form |
| `Menu/`, `EventListener/` | The admin menu entry, and the cleanup of a deleted channel's settings |
| `Twig/` | What the review screen shows about a suggestion, computed when the page is rendered |
| `DependencyInjection/` | Loads the services and prepends the Twig hooks, the grid action templates, the generation bus, the import staging cache pool and the theme translation roots |

## Main flows

### Browsing and editing (catalogue screen)

1. `Translation\IndexController` resolves the channel from `?scope=` and renders the page; the
   grid asks `CatalogueGridProvider` for its rows.
2. `CatalogueRows` turns the filters into a selection and `TranslationViewFactory` reads the
   engine: file values, overrides, inherited values and reference texts.
3. A row opens the edit modal, filled from the `data-*` attributes of its trigger by a Stimulus
   controller.
4. `Translation\SaveController` hands the value to `ManualOverrideEditor`, which decides: store an
   override, remove one, write nothing or refuse the value. The controller words the outcome and returns to the
   list as it was.

### Importing

1. `Translation\ImportController` checks the upload (size, extension, sniffed media type) - the
   size being the plugin's cap or PHP's own limit when that is lower (`ImportUploadLimit`) - and
   withdraws the import that was waiting. `ImportStager` then previews the file and parks its
   content in a cache pool, its preview in the session.
2. The page shows the preview. `Translation\ImportConfirmController` takes the staged content and
   lets the engine write the overrides; `Translation\ImportCancelController` drops it.

### Generating and reviewing (suggestions screen)

1. `Suggestion\EstimateController` answers the volume and the cost of the form's coordinates,
   without calling the provider.
2. `Suggestion\GenerateController` validates the same coordinates and dispatches one
   `GenerateSuggestionsMessage` per catalogue on `cyllene_digital_ai_translation.bus`. The form's
   button locks for 60 seconds in the browser (`assets/admin/js/generation-controller.js`); the
   server queues whatever it receives, the engine guarding the bill
   ([why](../adr/0022-double-billing-guard-is-the-engine.md)).
3. A worker runs the engine's handler: pending suggestions appear in the grid, and each run in the
   journal under it, failures included - the last ten of the channel
   (`Suggestion\IndexController::JOURNAL_LENGTH`).
4. `Suggestion\ApproveController` (or the bulk one) asks the engine to turn a suggestion into an
   override; `Suggestion\RejectController` deletes it. Every write checks its CSRF token through
   `Screen::validateCsrf()`; the rejection also accepts the per-row token of the grid's delete
   modal ([why](../adr/0007-csrf-checked-by-screen.md)).

## Principles

- **No file is ever written**, and nothing AI-generated is applied without an explicit approval.
- **The engine is used through its public contract only**: every engine class the plugin imports
  is listed in a test, and a new one shows up in a diff.
- **A screen works on one channel**, carried by the URL: every block of a page, every action and
  every redirect keeps it.
- **What an admin may not do is not rendered**: the templates, the grids and the menu ask for the
  same permission the server checks.
- **A generation never goes through a transactional bus**: a failing chunk would roll back
  suggestions already billed.

Why each of them holds, and what was left as it is on purpose: the
[decision records](../adr/README.md).

## Entry files

| File | Role |
|---|---|
| `src/CylleneDigitalSyliusAiTranslationPlugin.php` | The bundle class |
| `config/routes.yaml` | What a host imports: `config/routes/admin.yaml` under the admin prefix |
| `config/services.php` | Imports `config/services/*.php` |
| `config/twig_hooks/admin.yaml` | The hooks of both screens and of the channel form section |
| `assets/admin/entrypoint.js` | The admin entrypoint a host imports |
