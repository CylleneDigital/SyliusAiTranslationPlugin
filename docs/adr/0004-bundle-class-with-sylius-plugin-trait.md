# 0004. A `Bundle` class with `SyliusPluginTrait`, not `AbstractBundle`

- Status: accepted
- Date: 2026-10-13

## Context

Symfony's `AbstractBundle` puts the configuration and the extension in the bundle class. A Sylius
plugin uses `SyliusPluginTrait`, which redefines `getContainerExtension()`.

## Decision

The bundle is a plain `Bundle` with `SyliusPluginTrait` and a classic `Extension` class, like the
other Sylius plugins.

## Consequences

- The extension loads the services and prepends what the plugin configures in other bundles.
- The bundle class and its namespace are part of the public surface.

## Related

- [0003. Services are declared explicitly, without autowiring](0003-explicit-services-no-autowiring.md)
