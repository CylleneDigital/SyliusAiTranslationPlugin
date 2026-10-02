# Security policy

This plugin gives back-office users two screens that overwrite the wording of the whole shop and
trigger **billed** AI generations, accepts uploaded XLIFF and CSV files, and sends translation
values to third-party AI providers with API keys held by the host project. A flaw here can let an
unauthorized admin rewrite storefront texts, spend the provider budget, store an unexpected file or
inject markup into a translated value, so please report it privately.

What each role lets its holder do on purpose is described in
[`docs/integration/permissions.md`](docs/integration/permissions.md#what-a-role-is-worth): a way around these roles, or a
way to do any of it without them, is a vulnerability.

## Reporting a vulnerability

**Do not open a public issue.** Use either of the two private channels:

- [GitHub security advisory](https://github.com/CylleneDigital/SyliusAiTranslationPlugin/security/advisories/new)
  ("Report a vulnerability")
- email to sylius@groupe-cyllene.com

Please include the plugin and engine versions, the Sylius and PHP versions, and the steps to
reproduce. **Leave out API keys, customer data and pre-production URLs.**

A flaw in the translation engine itself (providers, overrides, caches) is reported to
[`cyllene-digital/ai-translation-bundle`](https://github.com/CylleneDigital/AiTranslationBundle/security/policy).

## Response time

First response within **5 working days**. We keep you posted on the analysis, then on the fix and
its release date.

## Supported versions

| Version | Support |
|---|---|
| `1.x` | Bug and security fixes |

The supported Sylius, Symfony, PHP and database versions are listed in
[`docs/architecture/public-contract.md`](docs/architecture/public-contract.md#support-policy).

## Disclosure

Coordinated disclosure: the fix is released first, then the advisory. We are happy to credit the
reporter, unless they ask otherwise.
