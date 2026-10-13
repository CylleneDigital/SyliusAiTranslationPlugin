# 0020. The channel code to name map is not cached

- Status: accepted
- Date: 2026-10-13

## Context

The adapter that gives the engine its scopes builds the channel code to name map on each call.
The channels themselves are read once per request: only the loop is replayed.

## Decision

No cache of the map.

## Consequences

- The cost is a loop over a handful of channels per call.
- A cached map would be one more state to empty between two requests.

## Related

- [0014. No plugin-side cache of the catalogue rows](0014-no-plugin-side-cache-of-catalogue-rows.md)
