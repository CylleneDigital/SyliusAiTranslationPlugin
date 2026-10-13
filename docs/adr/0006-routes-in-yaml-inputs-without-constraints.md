# 0006. Routes in YAML, request inputs without validation constraints

- Status: accepted
- Date: 2026-10-13

## Context

A controller with route attributes is tagged `routing.controller`: the host's own attribute import
would register its routes a second time, outside the admin prefix. And a validation constraint on a
mapped input answers 422, where the back office answers a refused value with a flash and a
redirect.

## Decision

Every admin route is in `config/routes/admin.yaml`, imported by `config/routes.yaml` under
`/%sylius_admin.path_name%`. Inputs come through `#[MapQueryString]`, `#[MapRequestPayload]`
(`src/Controller/Admin/Input/`) and `#[MapEntity]`, and those objects carry no constraint.

## Consequences

- A well-typed value the plugin refuses is told to the admin in a flash, after a redirect.
- A value of the wrong type (`key[]=x`, `all=maybe`), which only a hand-built request sends, gets
  Symfony's own answer: 422 for a payload, 404 for a query string. The estimate's JavaScript shows
  its generic error then.

## Related

- [0005. One invokable controller per route](0005-one-invokable-controller-per-route.md)
