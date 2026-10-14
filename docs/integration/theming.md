# Theming and Twig hooks

What a project using the plugin overrides or extends on its two screens: templates, Twig hooks,
grids and the menu entry. Everything named on this page is part of the
[public contract](../architecture/public-contract.md): it only changes in a major version.

## Templates

Every template is overridden the Symfony way, from
`templates/bundles/CylleneDigitalSyliusAiTranslationPlugin/`, keeping the path it has under the
plugin's `templates/`:

```
templates/bundles/CylleneDigitalSyliusAiTranslationPlugin/admin/translation/grid/field/key.html.twig
```

## Twig hooks

Both screens are Sylius index pages built from Twig hooks: what a hook does not list comes from
`sylius_admin.common.index` (the grid block included).

| Hook | Hookables (priority) |
|---|---|
| `cyllene_digital_sylius_ai_translation.admin.translation.index.content` | `header` (200), `scope` (180), `dashboard` (150), Sylius' `grid` (100), `edit_modal` (50) |
| `cyllene_digital_sylius_ai_translation.admin.translation.index.content.header` | `breadcrumbs` (100), `title_block` (0) |
| `cyllene_digital_sylius_ai_translation.admin.translation.index.content.grid` | `no_data_block` (0) |
| `cyllene_digital_sylius_ai_translation.admin.suggestion.index.content` | `header` (200), `scope` (180), `generate` (150), Sylius' `grid` (100), `journal` (80), `review_modal` (50) |
| `cyllene_digital_sylius_ai_translation.admin.suggestion.index.content.header` | `breadcrumbs` (100), `title_block` (0) |
| `cyllene_digital_sylius_ai_translation.admin.suggestion.index.content.grid` | `no_data_block` (0) |
| `sylius_admin.channel.create.content.form.sections`, `…update…` | `ai_translation` (-100): the "AI translation" card of the channel form |
| `sylius_admin.channel.create.content.form.sections.ai_translation`, `…update…` | `prompt_context` (0) |

Add a block, replace one or switch one off as for any Sylius page:

```yaml
# config/packages/sylius_twig_hooks.yaml
sylius_twig_hooks:
    hooks:
        'cyllene_digital_sylius_ai_translation.admin.suggestion.index.content':
            journal:
                enabled: false
            my_notice:
                template: 'admin/translation/my_notice.html.twig'
                priority: 160
```

Each hookable reads the page's data from `hookable_metadata.context`: `scope`,
`scope_params`, `available_scopes`, `resources` (the grid view) on both screens; `coverage`,
`overrides_by_locale`, `orphan_count`, `pending_import`, `pending_suggestion_count`,
`export_formats`, `import_max_megabytes` on the catalogue; `available_providers`, `default_provider`, `available_catalogues`,
`catalogue_groups`, `available_locales`, `source_locale_default`, `pending_count`, `filtered`,
`filtered_by_target_locale_only`, `generation_logs` on the suggestions.

## Grids

Both lists are Sylius grids, configured in PHP:

| Grid | Class | Data |
|---|---|---|
| `cyllene_digital_sylius_ai_translation_admin_catalogue` | `Grid\CatalogueGrid` | computed rows (files, overrides, reference locales), through its own data provider |
| `cyllene_digital_sylius_ai_translation_admin_suggestion` | `Grid\SuggestionGrid` | the engine's pending suggestions, through Doctrine |

Extend one - a column, a filter, an action - from a listener on its definition event,
`sylius.grid.cyllene_digital_sylius_ai_translation_admin_catalogue` or
`sylius.grid.cyllene_digital_sylius_ai_translation_admin_suggestion`, as for any Sylius grid. The row and bulk actions use
templates registered under the action types `edit_translation`, `approve_suggestion`,
`review_suggestion`, `approve_suggestions` and `reject_suggestions`
(`sylius_grid.templates.action` / `bulk_action`): re-declare a type to change its template.

The catalogue rows are arrays, not entities: `key`, `catalogue`, `locale`, `scope`, `original`,
`override`, `hasOverride`, `inherited`, `inheritedFrom`, `reference`, `referenceLocale`,
`missing`.

## Menu

The plugin adds a root entry `cyllene_digital_sylius_ai_translation` with two children,
`cyllene_digital_sylius_ai_translation_browser` and `cyllene_digital_sylius_ai_translation_suggestions`, from a
`sylius.menu.admin.main` listener of priority `-1024` - after the usual host listeners, so that
a project reordering its menu with a fixed list is not broken: KnpMenu's `reorderChildren()`
throws `Cannot reorder children, order does not contain all children.` as soon as the given order
misses a child, which is what happens when a plugin has already added an item. Adding the entry
last avoids the trap for hosts that reorder at the common priorities (`0`, `-100`, `-999`…).

Move or rename the entry from a listener of a lower priority. A project that reorders its menu
below `-1024` either lists `cyllene_digital_sylius_ai_translation` in its order, or reorders tolerantly:

```php
// Keeps the requested order, ignores unknown names, appends children added by plugins.
private function reorderChildren(ItemInterface $menu, array $order): void
{
    $names = array_keys($menu->getChildren());
    $ordered = array_values(array_intersect($order, $names));
    $remaining = array_values(array_diff($names, $ordered));

    $menu->reorderChildren([...$ordered, ...$remaining]);
}
```

## Reacting to what happens

The plugin dispatches nothing of its own: an override saved or removed, a suggestion approved or
rejected are the engine's events (`OverrideSavedEvent`, `OverrideRemovedEvent`,
`SuggestionApprovedEvent`, `SuggestionRejectedEvent`), whatever door they came through - the
back office, the console or your own code. See the engine's
[public contract](https://github.com/CylleneDigital/AiTranslationBundle/blob/main/docs/public-contract.md).
