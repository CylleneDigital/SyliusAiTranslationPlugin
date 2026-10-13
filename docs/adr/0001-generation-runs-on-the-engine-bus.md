# 0001. Generation runs go through the engine bus, not `sylius.command_bus`

- Status: accepted
- Date: 2026-10-13

## Context

A generation is one Messenger message per catalogue, and each run calls a billed AI provider chunk
by chunk. Sylius wraps the handlers of `sylius.command_bus` in the `doctrine_transaction`
middleware.

## Decision

The plugin declares a bus of its own for the engine, `cyllene_digital_ai_translation.bus`, without
`doctrine_transaction`, and dispatches every `GenerateSuggestionsMessage` on it.

## Consequences

- A failing chunk does not roll back the suggestions of the chunks already billed, and replaying
  the message does not bill the run again.
- A host that dispatches `GenerateSuggestionsMessage` itself uses that bus too.
- The bus is part of the public surface: its name only changes in a major version.

## Related

- [0002. One prefix for everything the plugin names](0002-one-prefix-for-everything-the-plugin-names.md)
- [0022. The guard against double billing is the engine's](0022-double-billing-guard-is-the-engine.md)
