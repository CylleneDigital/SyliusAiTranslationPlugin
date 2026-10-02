# Permissions

Both screens trigger sensitive actions: **billed** AI generations and overwrites of the shop's
wording. On top of the Sylius admin firewall, they require a role of the plugin.

## The roles

One role per kind of consequence:

| Role | Opens |
|---|---|
| `ROLE_AI_TRANSLATION_VIEW` | both screens, the coverage, the export of a view |
| `ROLE_AI_TRANSLATION_EDIT` | saving a value by hand or removing its override, purging the orphan overrides |
| `ROLE_AI_TRANSLATION_IMPORT` | importing a file of overrides |
| `ROLE_AI_TRANSLATION_REVIEW` | approving and rejecting AI suggestions, one by one or in bulk |
| `ROLE_AI_TRANSLATION_GENERATE` | the cost estimate and the generations: what spends the provider budget |
| `ROLE_AI_TRANSLATION_MANAGE` | everything above |

Each role also lets its holder read both screens. Without any of them, the screens answer 403
and the menu entry is not shown.

The screens only show what the admin may do: without `EDIT`, the catalogue is read-only (no
row action, cells open no modal); without `REVIEW`, the suggestions are listed without their
decisions; without `GENERATE`, the generation form is absent. The server checks the same
permission on every action, whatever the page showed.

## Granting them

Sylius has no role management screen of its own: grant the roles through `role_hierarchy`.

```yaml
# config/packages/security.yaml
security:
    role_hierarchy:
        # Everything, to every admin
        ROLE_ADMINISTRATION_ACCESS: [ROLE_AI_TRANSLATION_MANAGE]
```

```yaml
security:
    role_hierarchy:
        # A translator edits and reviews, without the provider budget
        ROLE_TRANSLATOR: [ROLE_AI_TRANSLATION_EDIT, ROLE_AI_TRANSLATION_REVIEW]
        # Marketing reads the coverage
        ROLE_MARKETING: [ROLE_AI_TRANSLATION_VIEW]
```

`ROLE_TRANSLATOR` and `ROLE_MARKETING` are yours: give them to admin users the way your project
assigns roles (a role plugin, a command, the database).

## In your own code

The permissions are security attributes, granted by a voter from the roles above
(`CylleneDigital\SyliusAiTranslationPlugin\Security\Permission`):

```php
#[IsGranted(Permission::Review->value)]
```

```twig
{% if is_granted('cyllene_digital_sylius_ai_translation.generate') %}…{% endif %}
```

| Attribute | Role |
|---|---|
| `cyllene_digital_sylius_ai_translation.view` | `ROLE_AI_TRANSLATION_VIEW`, or any other role of the plugin |
| `cyllene_digital_sylius_ai_translation.edit` | `ROLE_AI_TRANSLATION_EDIT` |
| `cyllene_digital_sylius_ai_translation.import` | `ROLE_AI_TRANSLATION_IMPORT` |
| `cyllene_digital_sylius_ai_translation.review` | `ROLE_AI_TRANSLATION_REVIEW` |
| `cyllene_digital_sylius_ai_translation.generate` | `ROLE_AI_TRANSLATION_GENERATE` |

## What a role is worth

Some of what the plugin does is its purpose, not a flaw - and is worth knowing before granting
its roles:

- **An admin allowed to edit, import or review can change any wording of the shop**, including
  the translations a theme or Sylius itself renders as HTML (`|raw`). The plugin stores the value
  as given: it does not know which keys end up unescaped. Give `ROLE_AI_TRANSLATION_EDIT`,
  `ROLE_AI_TRANSLATION_IMPORT` and `ROLE_AI_TRANSLATION_REVIEW` to who you would let edit a
  template.
- **An AI-generated value goes the same way** once approved: the review is the control, which is
  why nothing generated is ever applied without one.
- **`ROLE_AI_TRANSLATION_GENERATE` spends the provider budget**, and the texts to translate are
  sent to the provider configured, with the channel's context when it has one.

A way around these roles, or a way to do any of the above without them, is a vulnerability: see
[`SECURITY.md`](../../SECURITY.md).
