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
suggestion, an errored one included, is not sent; the keys of a batch are reserved before the
provider is called, so a run started again does not send them; a retry leaves out the keys its
own interrupted run reserved; a key given a value during the run is left out of the batches that
follow; no failed run is retried by Messenger; a run for a scope the host no longer declares is
refused before the provider is called.

## Decision

The plugin still adds no server-side lock, and now relies on a promise of the engine rather than
on its behaviour. The plugin requires the first engine release that makes that promise.

## Consequences

- A generation sent twice is queued twice, and bills a key once as long as its suggestion waits
  for a review. A key is billed again when its suggestion was reviewed in between and the run
  still covers it: a rejected one, or any key of an "All keys" run. That is what asking again
  means.
- A "Retry errors" run re-sends the keys whose previous run failed, for as long as they fail, but
  not the ones a run killed during its call to the provider left reserved after the retry was
  queued.
- A run queued for a channel deleted since bills nothing; a run whose channel is deleted while it
  runs stops before its next batch, journaled as a failure.
- A change of the guard is now a major version of the engine, which the plugin's constraint
  does not accept without a release of its own.
- The lock protects the bill only while its store answers, and only the workers that share it: a
  deployment note of the engine, which documents it.

## Related

- [0001. Generation runs go through a dedicated bus, not `sylius.command_bus`](0001-generation-runs-on-a-dedicated-bus.md)
- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
- [0016. No live tracking of a generation](0016-no-live-tracking-of-a-generation.md)
- [0023. A deleted channel keeps its overrides, suggestions and journal](0023-deleted-channel-keeps-its-translations.md)
