# 0010. One permission per kind of consequence

- Status: accepted
- Date: 2026-10-13

## Context

Browsing translations, editing them, importing a file, reviewing AI suggestions and launching a
billed generation do not carry the same risk: a project wants to grant them separately.

## Decision

One permission per kind of consequence - view, edit, import, review, generate - each granted by
its own role, and all of them by one managing role. Every permission lets its holder read.

## Consequences

- A controller takes the permission of what it does.
- What the admin may not do is not rendered either: grids, menu and templates ask for the same
  permission the server checks.
- The roles are part of the public surface.

## Related

- [0005. One invokable controller per route](0005-one-invokable-controller-per-route.md)
