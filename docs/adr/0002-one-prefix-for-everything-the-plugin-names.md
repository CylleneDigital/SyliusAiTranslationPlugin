# 0002. One prefix for everything the plugin names

- Status: accepted
- Date: 2026-10-13

## Context

The plugin names service ids, routes, Twig hooks, grids, translation keys, menu entries, permission
attributes and CSRF intentions. A host has to tell them from the engine's and from its own.

## Decision

Everything the plugin names starts with `cyllene_digital_sylius_ai_translation`.
`cyllene_digital_ai_translation` stays the engine's: its configuration key, and the generation bus
the plugin declares for it. `cyllene_ai_translation` is the engine's Messenger transport.

## Consequences

- The bus keeps the engine's prefix on purpose: it is the engine's bus, and a host worker or a
  custom dispatch names it without knowing the plugin. It is not renamed.
- A new service, route, hook, grid or key takes the plugin prefix.

## Related

- [0001. Generation runs go through the engine bus, not `sylius.command_bus`](0001-generation-runs-on-the-engine-bus.md)
- [0003. Services are declared explicitly, without autowiring](0003-explicit-services-no-autowiring.md)
