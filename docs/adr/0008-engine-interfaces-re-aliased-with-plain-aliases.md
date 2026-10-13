# 0008. The engine interfaces are re-aliased with plain aliases

- Status: accepted
- Date: 2026-10-13

## Context

The engine aliases its host-facing interfaces (locales, author, scopes) to its own defaults. The
plugin provides the Sylius implementations, and must win whatever the host's configuration.

## Decision

`config/services/bridge.php` re-declares those aliases as plain aliases, not with `#[AsAlias]`.

## Consequences

- The engine bundle is registered before the plugin: the plugin, loaded after it, overrides the
  aliases deterministically.
- A host that wants its own implementation re-aliases the interface after the plugin, or decorates
  the plugin's service.

## Related

- [0003. Services are declared explicitly, without autowiring](0003-explicit-services-no-autowiring.md)
- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
