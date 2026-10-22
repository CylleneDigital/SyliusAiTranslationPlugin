# Public contract and versioning

This plugin follows [semantic versioning](https://semver.org). The elements below must **not** be
changed in a backward incompatible way without a **major** version bump. Everything else is internal
and may change in a minor version (see [What is internal](#what-is-internal)). Every break is written
in [UPGRADE.md](../../UPGRADE.md) before it is released.

## What is stable

| Element | Where it is listed |
|---|---|
| The bundle class `CylleneDigital\SyliusAiTranslationPlugin\CylleneDigitalSyliusAiTranslationPlugin` and what a host imports: `config/routes.yaml`, `assets/admin/entrypoint.js` | [Installation](../integration/installation.md) |
| The admin routes, their names, paths and permissions | [Routes](#routes) |
| The Twig hooks and their hookables, the templates and their paths, the data of `hookable_metadata.context` | [Theming and Twig hooks](../integration/theming.md) |
| The two grids, their names, their action types and the keys of a catalogue row | [Theming and Twig hooks](../integration/theming.md#grids) |
| The menu entry and its two children | [Theming and Twig hooks](../integration/theming.md#menu) |
| The roles and the permission attributes | [Permissions](../integration/permissions.md) |
| The four replaceable services | [Services](#services) |
| The generation bus `cyllene_digital_ai_translation.bus` and the cache pool `cyllene_digital_sylius_ai_translation.import_staging` | [Installation](../integration/installation.md#7-run-a-worker) |

## Services

The services are private and named `cyllene_digital_sylius_ai_translation.<area>.<name>`; the
controllers (one per route, public as any controller service) keep their class as id. Four of them are meant to be replaced or decorated:

| Id | Role | How |
|---|---|---|
| `….bridge.locale_provider` | the locales the engine works on: Sylius' *Configuration › Locales* | re-alias the engine's `LocaleProviderInterface` |
| `….bridge.author_provider` | who an override or a review is attributed to: the logged-in admin | re-alias the engine's `AuthorProviderInterface` |
| `….bridge.channel_scope_provider` | the engine's scopes: the Sylius channels, and the locales each one offers | re-alias the engine's `ScopeProviderInterface` |
| `….security.permission_voter` | the permissions, from the roles | decorate it, as any voter |

To replace a `bridge` service - other locales than Sylius', another notion of scope than the
channel - alias its interface to your own service in your `config/services.yaml`: the
application's configuration is loaded after the bundles'. Decorating one works too: nothing in
the plugin is typed on their classes.

The locales of a scope are the one thing the screens read from the Sylius channels themselves,
not from the scope provider: with scopes that are not channels, a scope offers every locale -
and one whose code is also a channel's offers that channel's. The engine's import reads them from
the scope provider: a replacement, or a decorator, keeps that check only if it implements
`ScopeLocalesProviderInterface` too - otherwise an import writes an entry in a locale its
scope does not offer.

Every other service is internal: its class is `final`, the controllers and the grids are typed
on it, and a decorator makes the page fail with a `TypeError`. Change what the screens do
through the hooks, the grids, the templates and the engine's configuration instead.

## Routes

All under the admin prefix, named `cyllene_digital_sylius_ai_translation_admin_*`:

| Route | Path | Permission |
|---|---|---|
| `…translation_index` | `GET /translations` | view |
| `…translation_export` | `GET /translations/export/{format}` | view |
| `…translation_import_template` | `GET /translations/import-template/{format}` | view |
| `…translation_save`, `…translation_reset` | `POST /translations/save`, `/reset` | edit |
| `…translation_purge_orphans` | `POST /translations/purge-orphans` | edit |
| `…translation_import`, `…translation_import_confirm`, `…translation_import_cancel` | `POST /translations/import…` | import |
| `…suggestion_index` | `GET /translations/suggestions` | view |
| `…suggestion_estimate` | `GET /translations/suggestions/estimate` | generate |
| `…suggestion_generate` | `POST /translations/suggestions/generate` | generate |
| `…suggestion_approve`, `…suggestion_reject` | `POST /translations/suggestions/{id}/approve`, `POST` or `DELETE` `/reject` | review |
| `…suggestion_approve_bulk`, `…suggestion_reject_bulk` | `POST /translations/suggestions/approve-bulk`, `/reject-bulk` | review |

Every `POST` expects the CSRF token of its screen in `_token`; the reject route also takes the
`_csrf_token` of Sylius' own delete action, which the grid uses.

## What is internal

Everything this page does not name, in particular:

- every service but the four of [Services](#services), their classes (all `final`) and ids;
- the controllers, their input objects (`Controller\Admin\Input`) and the form extension;
- the grid classes, their data providers and the keys of a grid row other than the listed ones;
- the Twig extensions and functions, the Stimulus controllers and their identifiers, the CSS
  classes;
- the translation keys and the wording of the flashes;
- the DI extension and what it prepends, other than the hooks, the grid action types, the
  generation bus and the import staging cache pool
  (`cyllene_digital_sylius_ai_translation.import_staging`, which a host may re-point).

It may change in a minor version. The [decision records](../adr/README.md) explain choices, some
of them about internals: a record promises nothing, only this page says what is stable.

## Support policy

| Line | What is covered |
|---|---|
| Sylius | 2.1, 2.2 and 2.3 (CI) |
| Symfony | 7.4 and 8.x (8.x with PHP 8.4+ and Sylius 2.3 in CI) |
| PHP | 8.3+ (CI: 8.3, 8.4, 8.5) |
| Databases (CI) | MySQL 8.0 and 8.4, MariaDB 10.11 and 11.4, PostgreSQL 15, 16 and 17 |
| Engine | `cyllene-digital/ai-translation-bundle` `^2.0` (CI: the newest and the oldest release) |

Support for a Sylius, Symfony or PHP version ends when it leaves the CI matrix. Report security
flaws privately: [`SECURITY.md`](../../SECURITY.md).
