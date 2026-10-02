# Documentation

Documentation of **cyllene-digital/sylius-ai-translation-plugin**. What the plugin does and does
not do, installation in short and production notes: the root [README](../README.md).

## Audience

| Profile | Recommended pages |
|---------|-------------------|
| Administrator (back office) | [Back-office guide](admin/user-guide.md) |
| Sylius integrator | [Installation](integration/installation.md), [Assets](integration/assets.md), [Permissions](integration/permissions.md), [Theming and Twig hooks](integration/theming.md), [Public contract](architecture/public-contract.md) |
| Ops (deployment) | [Installation: worker and load balancer](integration/installation.md#7-run-a-worker), [UPGRADE](../UPGRADE.md) |
| Plugin contributor | [Architecture overview](architecture/overview.md), [Tests](testing/tests.md), [CONTRIBUTING](../CONTRIBUTING.md) |

## Table of contents

### Back office

- [Back-office guide](admin/user-guide.md): both screens in detail - the channel filter, browsing, edition, coverage, import and export, generation, review

### Integration

- [Installation](integration/installation.md): requirements, every step with and without Flex, the worker, a load-balanced setup, how to check the result
- [Assets](integration/assets.md): the admin entrypoint to import, its stylesheet and Stimulus controllers
- [Permissions](integration/permissions.md): the roles, what each opens, how to grant them
- [Theming and Twig hooks](integration/theming.md): templates, Twig hooks, grids, the menu entry, the engine's events

### Architecture

- [Architecture overview](architecture/overview.md): what the plugin is to the engine, layers, main flows, principles
- [Public contract](architecture/public-contract.md): what is stable (semver major), the replaceable services, the routes, what is internal, support policy

### Development

- [Tests](testing/tests.md): the four levels, what each guards, Behat, continuous integration

## The engine

The translation engine has its own
[documentation](https://github.com/CylleneDigital/AiTranslationBundle/tree/main/docs): concepts
(overrides, catalogues, suggestions, scopes), provider bridges, configuration reference, CLI,
caches, and its [public contract](https://github.com/CylleneDigital/AiTranslationBundle/blob/main/docs/public-contract.md).
