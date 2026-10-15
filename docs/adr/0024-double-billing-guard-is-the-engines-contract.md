# 0024. The guard against double billing is the engine's, by its contract

- Status: accepted
- Date: 2026-10-15
- Supersedes: [0022](0022-double-billing-guard-is-the-engine.md)

## Context

The plugin left the guard against double billing to the engine
([0022](0022-double-billing-guard-is-the-engine.md)), while that guard was a behaviour the engine's
public contract did not promise: a minor version of the engine could have changed it without
notice. The engine has since written it into its public contract, as what a generation run
guarantees: one run at a time per catalogue, target locale and scope; a key that has a pending
suggestion, an errored one included, is not sent; the keys of a chunk are reserved before the
provider is called, so a run started again does not send them; a run that retries errors leaves
out the keys its own interrupted attempt reserved; in a run for the missing keys, a key given a
value during the run is left out of the chunks that follow; no failed run is retried by
Messenger; a run for a scope the host no longer declares is refused before the provider is
called, and one whose scope goes while it runs stops before its next chunk.

## Decision

The plugin still adds no server-side lock, and now relies on a promise of the engine rather than
on its behaviour: its constraint only accepts engine releases that make that promise.

The engine learns whether a channel still exists from the plugin, before each chunk. The plugin
keeps its list of channels for a whole request, but only briefly outside one - a Messenger
worker, the console: a list kept for the whole message would hide a channel deleted during the
run, and the run would go on billing chunks for nobody.

## Consequences

- A generation sent twice is queued twice, and bills a key once as long as its suggestion waits
  for a review. A key is billed again when its suggestion was reviewed in between and the run
  still covers it: a rejected one, or any key of an "All keys" run. That is what asking again
  means.
- A "Retry errors" run re-sends the keys whose previous run failed, for as long as they fail,
  except the ones its own attempt reserved before being killed: started again, it does not bill
  them twice.
- The keys reserved for a chunk are stored as errored suggestions until the reply comes: the
  suggestions screen shows them apart from the provider failures, since a run in progress has
  not failed.
- A run queued for a channel deleted since bills nothing. A run whose channel is deleted while
  it runs stops before its next chunk; the chunk already with the provider is billed.
- A change of the guard is now a major version of the engine, which the plugin's constraint
  does not accept without a release of its own.
- A lock in the plugin, or a single-use token in the session, would still duplicate the engine's
  guard without covering more.
- The lock protects the bill only while its store answers, and only between the workers that
  share it; when the store cannot be reached, the engine runs the generation unguarded. Duplicate
  suggestions stay impossible, the database refuses them. These are deployment notes of the
  engine, which documents them.

## Related

- [0001. Generation runs go through a dedicated bus, not `sylius.command_bus`](0001-generation-runs-on-a-dedicated-bus.md)
- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
- [0016. No live tracking of a generation](0016-no-live-tracking-of-a-generation.md)
- [0020. The channel code to name map is not cached](0020-channel-name-map-not-cached.md)
- [0023. A deleted channel keeps its overrides, suggestions and journal](0023-deleted-channel-keeps-its-translations.md)
