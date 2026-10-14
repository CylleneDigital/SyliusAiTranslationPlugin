# 0012. No file is written, nothing AI-generated is applied without a human

- Status: accepted
- Date: 2026-10-13

## Context

Translation files belong to the host project and its deployments. An AI translation can be wrong
in ways only a reader of the language sees.

## Decision

An override is a database delta over the files, never a write to them. A generation only produces
pending suggestions, and a suggestion only becomes an override through an explicit approval.

## Consequences

- Removing an override gives back what the entry shows without it: the value it inherits - the
  global override in a channel, the parent language's for a regional locale - else the file value.
- There is no "apply everything" without review: a bulk approval acts on the suggestions an admin
  selected.
- The storefront never shows a pending or rejected suggestion.

## Related

- [0010. One permission per kind of consequence](0010-one-permission-per-kind-of-consequence.md)
- [0022. The guard against double billing is the engine's](0022-double-billing-guard-is-the-engine.md)
