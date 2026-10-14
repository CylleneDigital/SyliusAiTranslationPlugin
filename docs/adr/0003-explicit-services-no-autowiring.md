# 0003. Services are declared explicitly, without autowiring

- Status: accepted
- Date: 2026-10-13

## Context

Symfony recommends that a reusable bundle does not rely on autowiring nor autoconfiguration: its
services must not depend on what the host application happens to alias or tag.

## Decision

Every service is declared by hand, one file per area, with a prefixed id and every argument and
tag written out. The controllers are the exception to the prefix: they keep their class as id,
since the routes name them that way.

## Consequences

- A new constructor argument is added to the service definition.
- A tag autoconfiguration would have set is added by hand.
- The attributes that only autowiring or autoconfiguration reads do nothing here and are not
  used. One a class reads itself still works.
- A controller registered under a prefixed id would no longer be found by its route.

## Related

- [0002. One prefix for everything the plugin names](0002-one-prefix-for-everything-the-plugin-names.md)
- [0008. The engine interfaces are re-aliased with plain aliases](0008-engine-interfaces-re-aliased-with-plain-aliases.md)
