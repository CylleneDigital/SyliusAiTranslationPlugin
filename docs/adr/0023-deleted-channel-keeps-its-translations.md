# 0023. A deleted channel keeps its overrides, suggestions and journal

- Status: accepted
- Date: 2026-10-13

## Context

Overrides, suggestions and journal rows carry the code of the channel they belong to. When a
channel is deleted, the plugin removes its prompt context (`ChannelDeletionListener`). The engine's
public contract has no removal of the rest by scope.

## Decision

The overrides, suggestions and journal rows of a deleted channel are left in the database.

## Consequences

- Deleting a channel does not destroy translation work, which would not come back.
- Those rows are shown by no screen while the channel does not exist; a channel created again
  under the same code finds them.
- Removing them would first need a removal by scope in the engine's contract.

## Related

- [0012. No file is written, nothing AI-generated is applied without a human](0012-nothing-written-to-files-nothing-applied-without-a-human.md)
- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
