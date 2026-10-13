# 0003. Services are declared explicitly, without autowiring

- Status: accepted
- Date: 2026-10-13

## Context

Symfony recommends that a reusable bundle does not rely on autowiring nor autoconfiguration: its
services must not depend on what the host application happens to alias or tag.

## Decision

One file per area in `config/services/`, every id prefixed with
`cyllene_digital_sylius_ai_translation.`, every argument and tag written out. The controllers keep
their class as id, since the routes name them that way.

## Consequences

- A new constructor argument is added to the service definition.
- Tags are added by hand: `kernel.reset` for a class implementing `ResetInterface`,
  `kernel.event_listener`, `twig.extension`, `form.type_extension`, `sylius.grid`,
  `sylius.grid_data_provider`.
- `#[Autowire]`, `#[AsEventListener]` and `#[AsDecorator]` do nothing in `src/` and are not used.
  `#[AsGrid]` stays: `AbstractGrid` reads it itself.

## Related

- [0002. One prefix for everything the plugin names](0002-one-prefix-for-everything-the-plugin-names.md)
- [0008. The engine interfaces are re-aliased with plain aliases](0008-engine-interfaces-re-aliased-with-plain-aliases.md)
