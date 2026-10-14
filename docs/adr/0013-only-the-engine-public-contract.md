# 0013. Only the engine's public contract is used

- Status: accepted
- Date: 2026-10-13

## Context

The engine documents its public surface in a public contract. Everything else is
internal and may change in a minor version, which the plugin accepts with `^1.0`.

## Decision

The plugin only uses what that page names, method by method. A unit test lists every engine symbol
the plugin's code and configuration import, and fails on a new one and on a stale one.

## Consequences

- The objects the engine's services return, read without an import, are listed in the same test
  and checked by reflection.
- One container parameter of the engine is read too, the default provider the generation form
  presets: the container fails to compile if it goes.
- No test builds an engine service by hand, its constructors not being contract: a test takes the
  engine from the container, or stubs its manager interface.

## Related

- [0008. The engine interfaces are re-aliased with plain aliases](0008-engine-interfaces-re-aliased-with-plain-aliases.md)
- [0014. No plugin-side cache of the catalogue rows](0014-no-plugin-side-cache-of-catalogue-rows.md)
