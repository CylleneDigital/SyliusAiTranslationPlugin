# 0008. The engine interfaces are re-aliased with plain aliases

- Status: accepted
- Date: 2026-10-13

## Context

The engine aliases its host-facing interfaces (locales, author, scopes) to its own defaults. The
plugin provides the Sylius implementations, and must win over those defaults deterministically.

## Decision

The plugin re-declares those aliases as plain aliases in its service configuration, not with
`#[AsAlias]`.

## Consequences

- The engine bundle is registered before the plugin: the plugin, loaded after it, overrides the
  engine's aliases deterministically.
- The host keeps the last word: its own configuration is read after every bundle, so re-aliasing
  an interface there replaces the plugin's implementation. The public contract lists these
  services as replaceable.

## Related

- [0003. Services are declared explicitly, without autowiring](0003-explicit-services-no-autowiring.md)
- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
