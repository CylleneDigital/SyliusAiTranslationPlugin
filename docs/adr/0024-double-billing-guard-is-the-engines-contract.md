# 0024. The guard against double billing is the engine's, by its contract

- Status: accepted
- Date: 2026-10-15
- Supersedes: [0022](0022-double-billing-guard-is-the-engine.md)

## Context

The plugin left the guard against double billing to the engine
([0022](0022-double-billing-guard-is-the-engine.md)), while that guard was a behaviour the engine's
public contract did not promise: a minor version of the engine could have changed it without
notice. The engine has since written that guard into its public contract, as what a generation
run guarantees, which only changes in a major version: among others, one run at a time per
catalogue, target locale and scope, the keys of a chunk reserved before the provider is called,
and a run for a scope the host no longer declares refused, or stopped before its next chunk.

## Decision

The plugin still adds no server-side lock, and now relies on a promise of the engine rather than
on its behaviour: its constraint only accepts engine releases that make that promise.

The engine learns whether a channel still exists from the plugin, when a run starts and before
each chunk. The plugin keeps its list of channels for a whole request, but only briefly outside
one - a Messenger worker, the console: a list kept for the whole message would hide a channel
deleted during the run, and the run would go on billing chunks for nobody.

## Consequences

- A generation sent twice is queued twice, and bills a key once as long as its suggestion waits
  for a review. A key is billed again when its suggestion was reviewed in between and the run
  still covers it: a rejected one, or any key of an "All keys" run. That is what asking again
  means.
- A "Retry errors" run re-sends the keys whose previous run failed, for as long as they fail,
  except the ones its own attempt reserved before being killed: started again, it does not bill
  them twice.
- The keys reserved for a chunk are stored as errored suggestions until the reply comes, or,
  when the run was stopped during the call, until a "Retry errors" run sends them again or a
  reviewer settles them by hand: the suggestions screen shows them apart from the provider
  failures.
- A run queued for a channel deleted since bills nothing. A run whose channel is deleted while
  it runs stops before its next chunk; the chunk already with the provider is billed.
- Outside a request, the channels are read again before a chunk once the short delay has passed:
  about one query per chunk, next to a call to a paid provider.
- A run executed inside a request - a host routing the engine's messages to a synchronous
  transport - keeps the channels it read: a channel deleted meanwhile does not stop it.
- A change of the guard is now a major version of the engine, which the plugin's constraint
  does not accept without a release of its own.
- A lock in the plugin, or a single-use token in the session, would still duplicate the engine's
  guard without covering more.
- The lock protects the bill only while its store answers, and only between the workers that
  share it. These are deployment notes of the engine, which documents them.

## Related

- [0001. Generation runs go through a dedicated bus, not `sylius.command_bus`](0001-generation-runs-on-a-dedicated-bus.md)
- [0012. No file is written, nothing AI-generated is applied without a human](0012-nothing-written-to-files-nothing-applied-without-a-human.md)
- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
- [0016. No live tracking of a generation](0016-no-live-tracking-of-a-generation.md)
- [0020. The channel code to name map is not cached](0020-channel-name-map-not-cached.md):
  the channels it reads once per request are the ones kept only briefly outside one
- [0025. A deleted channel takes its overrides and suggestions along, its journal stays](0025-a-deleted-channel-takes-its-translations-along.md)
