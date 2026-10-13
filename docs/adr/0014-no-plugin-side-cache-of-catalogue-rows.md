# 0014. No plugin-side cache of the catalogue rows

- Status: accepted
- Date: 2026-10-13

## Context

The catalogue screen costs what the engine costs: for each page, every translation file of the
view is parsed again (the engine caches them per process, not across requests) and each catalogue
is read with queries of its own.

## Decision

The plugin does not cache the rows it shows. The lever is in the engine: a persistent file cache,
a batch read of the overrides of a view.

## Consequences

- A cache of the rows would duplicate the engine's inheritance rules (channel, parent language,
  file) and its invalidation.
- The screen gets faster when the engine does, without a change here.

## Related

- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
- [0020. The channel code to name map is not cached](0020-channel-name-map-not-cached.md)
