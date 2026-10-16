# 0025. A deleted channel takes its overrides and suggestions along, its journal stays

- Status: accepted
- Date: 2026-10-16
- Supersedes: [0023](0023-deleted-channel-keeps-its-translations.md)

## Context

A deleted channel kept its overrides and suggestions
([0023](0023-deleted-channel-keeps-its-translations.md)), because the engine's public contract
had no removal by scope. Those rows were shown by no screen, and a channel created later under the
same code found overrides nobody had written for it, served to its visitors at once.

The engine now has that removal in its public contract: a purge of one scope, which deletes its
overrides (each announced as removed, the caches invalidated after the commit), its suggestions
and its prompt context, keeps the generation journal, and runs in a transaction of its own. A
console command lists the scopes the engine stores data for that the host no longer declares, and
purges them on demand.

## Decision

When Sylius has deleted a channel, the plugin purges what the engine stores for it, on the event
Sylius dispatches after the deletion is committed: never before it, since a deletion Sylius
refuses (the last enabled channel, a channel still in use) must lose nothing, nor inside its
flush, whose rollback would follow an announced purge.

A purge that fails is logged, and does not turn a deletion that happened into an error page. A
channel deleted without Sylius' resource controller (a script, Sylius' API, SQL) is not purged
at all. The engine's command purges what either case left behind.

The purge runs after the host's own listeners of that event: a purge that fails leaves the entity
manager they share closed.

## Consequences

- Deleting a channel deletes its translation work, which does not come back: an export of the
  channel's view, before the deletion, is the copy to keep.
- A channel created again under the same code starts with no override and no suggestion, unless
  a run of the deleted channel failed at the provider after the purge: the engine stores that
  failure under the deleted code, which the new channel then finds unless the engine's command
  purged it in between.
- The journal keeps the deleted channel's runs: it records what was paid for, and serves no
  translation; a channel created again under the same code lists them in its journal.
- Whoever may delete a channel in Sylius deletes its translation work, without any permission of
  the plugin: the deletion is Sylius' decision, the purge only follows it.
- A channel with many overrides is purged inside the deletion request, one removal announced per
  override; in a bulk deletion, a purge that fails stops the deletion of the channels after it,
  the entity manager being closed.
- The prompt context goes with the purge, after the deletion, instead of being staged with it.
- The rows a channel deleted before this decision left are only purged by the engine's command.

## Related

- [0012. No file is written, nothing AI-generated is applied without a human](0012-nothing-written-to-files-nothing-applied-without-a-human.md)
- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
- [0024. The guard against double billing is the engine's, by its contract](0024-double-billing-guard-is-the-engines-contract.md):
  a run of a deleted channel stops before its next chunk
