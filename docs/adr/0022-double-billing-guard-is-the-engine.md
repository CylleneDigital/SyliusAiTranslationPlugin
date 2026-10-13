# 0022. The guard against double billing is the engine's

- Status: accepted
- Date: 2026-10-13

## Context

The generate controller queues every generation it receives. The only thing that stops the same
form from being sent twice is in the browser: the button locks for a while once clicked. A second
tab, or a click after that delay, queues the same runs again.

## Decision

The plugin adds no server-side lock. The engine already guards the bill: it runs one generation
at a time per catalogue, target locale and channel (a `symfony/lock` lock, a second concurrent run
is skipped), and a run skips every key that already has a pending suggestion.

## Consequences

- A generation sent twice is queued twice, and bills a key once as long as its suggestion waits
  for a review.
- A key is billed again when its suggestion was reviewed in between and the run still covers it:
  a rejected one, or any key of an "All keys" run. That is what asking again means.
- A "Retry errors" run is the exception to the skip, by definition: it re-sends the keys whose
  previous run failed, for as long as they fail.
- The engine's lock store is the host's: the default one is local to a machine, so workers spread
  over several hosts need a shared store. That is a deployment note of the engine, which documents
  it.
- A lock in the plugin, or a single-use token in the session, would duplicate the engine's guard
  without covering more.

## Related

- [0001. Generation runs go through a dedicated bus, not `sylius.command_bus`](0001-generation-runs-on-a-dedicated-bus.md)
- [0012. No file is written, nothing AI-generated is applied without a human](0012-nothing-written-to-files-nothing-applied-without-a-human.md)
- [0016. No live tracking of a generation](0016-no-live-tracking-of-a-generation.md)
