# Upgrade guide

This file lists, one major version at a time, what has to change in a project already using the
plugin.

The plugin plugs two screens into the Sylius back office through routes, Twig hooks and a menu
entry, guards them with dedicated roles, and re-points the engine's host-facing interfaces at its
Sylius adapters: a compatibility break (a renamed route or hook, a changed role or permission, a moved template)
can silently drop a host's override or lock its admins out of the screens. Every break must
therefore be written here before it is released, together with the exact steps to carry out.

The engine has its own guide:
[`cyllene-digital/ai-translation-bundle`](https://github.com/CylleneDigital/AiTranslationBundle/blob/main/UPGRADE.md).

`v1.0.0` is the first release: there is nothing to upgrade from yet.
