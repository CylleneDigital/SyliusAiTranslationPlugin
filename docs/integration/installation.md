# Installation

The plugin is the Sylius back office of
[`cyllene-digital/ai-translation-bundle`](https://github.com/CylleneDigital/AiTranslationBundle):
Composer installs both, and the engine is configured under its own
`cyllene_digital_ai_translation` key.

## Requirements

| Component | Versions |
|---|---|
| PHP | `^8.3` (Symfony 8: `^8.4`), with `ext-intl` |
| Sylius | `2.1`, `2.2`, `2.3` |
| Symfony | `^7.4`, or `^8.0` with Sylius 2.3 |
| Engine | `^1.4.1` |

A Messenger worker is needed for AI generation, and a Webpack Encore build for the admin assets,
as for the rest of a Sylius application.

## 1. Require the package

```bash
composer config extra.symfony.allow-contrib true
composer require cyllene-digital/sylius-ai-translation-plugin
```

With Symfony Flex, the recipes register both bundles, import the routes and copy a commented
`config/packages/cyllene_digital_ai_translation.yaml`: step 2 is done, go to step 3. Without
Flex, follow every step.

## 2. Register the plugin and its routes

Without Flex, register the plugin **after** the engine - it re-points the
engine's host-facing interfaces at its Sylius adapters, and the last one registered wins:

```php
// config/bundles.php
CylleneDigital\AiTranslationBundle\CylleneDigitalAiTranslationBundle::class => ['all' => true],
CylleneDigital\SyliusAiTranslationPlugin\CylleneDigitalSyliusAiTranslationPlugin::class => ['all' => true],
```

```yaml
# config/routes/cyllene_digital_sylius_ai_translation.yaml
cyllene_digital_sylius_ai_translation:
    resource: '@CylleneDigitalSyliusAiTranslationPlugin/config/routes.yaml'
```

## 3. Configure the engine

In `config/packages/cyllene_digital_ai_translation.yaml` (the file the recipe copied, or
one you create without Flex): `translations_path`, `default_locale`, and at least one provider to enable AI
generation - the overrides work without any. See the engine's
[configuration reference](https://github.com/CylleneDigital/AiTranslationBundle/blob/main/docs/configuration-reference.md).

The plugin adds two things by itself: every `themes/*/translations` directory is browsed as an
`@ThemeName/…` catalogue, and the locales are the ones defined in *Configuration › Locales*.

## 4. Create the engine tables

The engine ships its Doctrine mapping, not migrations: version its tables in **your**
migrations, and run the diff again after updating it.

```bash
bin/console doctrine:migrations:diff && bin/console doctrine:migrations:migrate
```

## 5. Build the assets

Import the plugin's admin entrypoint from yours, then build ([details](assets.md)):

```js
// assets/admin/entrypoint.js
import '../../vendor/cyllene-digital/sylius-ai-translation-plugin/assets/admin/entrypoint.js';
```

```bash
yarn install
yarn build
```

## 6. Grant the roles

Both screens answer 403, and the menu entry is hidden, until an admin holds a role of the plugin
([details](permissions.md)). Everything to every admin:

```yaml
# config/packages/security.yaml
security:
    role_hierarchy:
        ROLE_ADMINISTRATION_ACCESS: [ROLE_AI_TRANSLATION_MANAGE]
```

## 7. Run a worker

Generation is asynchronous, on the engine's own transport:

```bash
bin/console messenger:consume cyllene_ai_translation
```

The plugin dispatches the runs on its own bus (`cyllene_digital_ai_translation.bus`), not on
`sylius.command_bus`: that one wraps handlers in `doctrine_transaction`, and a failing chunk
would roll back the suggestions already billed. If you dispatch `GenerateSuggestionsMessage`
yourself, use that bus too
([decision record](../adr/0001-generation-runs-on-a-dedicated-bus.md)).

Keep that transport asynchronous: a run stops before its next chunk when its channel is deleted
meanwhile, but a run executed inside a request (a synchronous transport) keeps the channels it read
when the request started, and does not see the deletion ([decision record](../adr/0024-double-billing-guard-is-the-engines-contract.md)).

## A deleted channel

Deleting a channel from the admin purges what the engine stores for it - its overrides, its
suggestions and its prompt context - once Sylius has committed the deletion; the generation
journal stays. A purge that fails is logged, and the deletion goes through; in a bulk deletion,
the channels after it are not deleted (Sylius says so in a flash). A channel deleted another way
(a script, Sylius' API, SQL), or deleted before this version of the plugin, keeps its rows until
the engine's command purges them; without `--force`, it only lists them:

```bash
bin/console cyllene:ai-translation:purge-scopes
bin/console cyllene:ai-translation:purge-scopes --force
```

The purge runs inside the deletion request, one `OverrideRemovedEvent` per override: for a channel
with tens of thousands of overrides, or a costly listener of that event (a reverse proxy purge),
it can outlast the proxy's timeout. The admin then sees an error page while the channel is deleted,
and the command purges what is left.
([decision record](../adr/0025-a-deleted-channel-takes-its-translations-along.md))

## The parsed translation files

The engine keeps the translation files it parses in the
`cyllene_digital_ai_translation.translation_files_cache` pool, keyed by their path and content: a
file rewritten in place is read again at once. A new `symfony/translation` release that parses a
file differently is only seen once the cached entry expires (seven days by default).
`cache:clear` does not empty the pool when `APP_SHARE_DIR` is set (the Symfony 7.4 and 8
skeletons):

```bash
bin/console cache:pool:clear cyllene_digital_ai_translation.translation_files_cache
```

## Behind a load balancer

An uploaded import waits for its confirmation, one hour at most, in the
`cyllene_digital_sylius_ai_translation.import_staging` cache pool, built on `cache.app`. The
confirmation only finds the upload if that cache is shared between the nodes - or point the pool
itself at a shared adapter:

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            cyllene_digital_sylius_ai_translation.import_staging:
                adapter: cache.adapter.redis
```

The engine has its own notes on [several servers](https://github.com/CylleneDigital/AiTranslationBundle/blob/main/docs/operations/multi-server.md).

## Checking the installation

```bash
bin/console debug:router | grep cyllene_digital_sylius_ai_translation   # 16 routes under the admin prefix
bin/console lint:container
bin/console cyllene:ai-translation                               # the engine's interactive console
```

Then, logged in as an admin holding a role: a **Translations** entry at the end of the admin
menu, the catalogue screen listing your `translations/` files, styled, with a row opening the
edit modal. A screen without style or whose modals stay closed means the assets of step 5 are
missing.
