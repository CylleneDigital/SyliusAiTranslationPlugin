# 0006. Routes in YAML, request inputs without validation constraints

- Status: accepted
- Date: 2026-10-13

## Context

A controller with route attributes is tagged `routing.controller`: the host's own attribute import
would register its routes a second time, outside the admin prefix. And a validation constraint on a
mapped input answers 422, where the back office answers a refused value with a flash and a
redirect.

## Decision

Every admin route is declared in YAML, in the file the host imports under its admin prefix. The
request inputs are mapped to objects (`#[MapQueryString]`, `#[MapRequestPayload]`, `#[MapEntity]`)
that carry no validation constraint.

## Consequences

- A well-typed value the plugin refuses is told to the admin in a flash, after a redirect.
- The volume estimate is the one action that is not a page: called by the generation form's
  JavaScript, it answers a refused value with a 422 and its message in JSON.
- A value of the wrong type (`key[]=x`, `all=maybe`), which only a hand-built request sends, gets
  Symfony's own answer: 422 for a payload, 404 for a query string. The estimate's JavaScript shows
  its generic error then.

## Related

- [0005. One invokable controller per route](0005-one-invokable-controller-per-route.md)
