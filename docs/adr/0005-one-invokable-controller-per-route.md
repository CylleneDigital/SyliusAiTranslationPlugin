# 0005. One invokable controller per route

- Status: accepted
- Date: 2026-10-13

## Context

Each admin action has its own consequence - reading, editing, importing, reviewing, spending money
on a generation - and its own permission.

## Decision

Each route has its own invokable controller class. What the actions of a screen share - the CSRF
intention, the flashes, the way back to the list - is one service, `Screen`, with one instance per
screen.

## Consequences

- A new action is a new controller class, with the permission of what it does on the class.
- A controller only words the outcome of a rule that lives in a service of the plugin or in the
  engine.

## Related

- [0006. Routes in YAML, request inputs without validation constraints](0006-routes-in-yaml-inputs-without-constraints.md)
- [0007. CSRF is checked by `Screen::validateCsrf()`, not `#[IsCsrfTokenValid]`](0007-csrf-checked-by-screen.md)
- [0010. One permission per kind of consequence](0010-one-permission-per-kind-of-consequence.md)
