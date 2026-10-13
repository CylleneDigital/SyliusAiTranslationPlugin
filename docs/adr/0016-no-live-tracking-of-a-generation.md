# 0016. No live tracking of a generation

- Status: accepted
- Date: 2026-10-13

## Context

A generation is asynchronous: the admin is told it is queued, and refreshes to see the
suggestions arrive. Three levels were studied: a periodic refresh of the list and the journal,
Server-Sent Events, and a real progress with a run identifier and state.

## Decision

None for now. The screen says the generation is queued and lists the last ten runs of the channel,
failures included.

## Consequences

- The engine cannot say that a run is queued, so the two lighter levels could not tell "waiting"
  from "nothing will come".
- Server-Sent Events hold one PHP connection per open tab.
- A real progress is an evolution of the engine's contract first.

## Related

- [0022. The guard against double billing is the engine's](0022-double-billing-guard-is-the-engine.md)
