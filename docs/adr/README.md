# Architecture decision records

One file per decision: its context, what was decided, what follows from it. A decision is not
edited once accepted: a new record supersedes it, and the old one says so in its status.

A new record takes the next number, the template of the others (Status, Date, Context, Decision,
Consequences, Related) and a line in the table below.

| # | Decision |
|---|---|
| 0001 | [Generation runs go through the engine bus, not `sylius.command_bus`](0001-generation-runs-on-the-engine-bus.md) |
| 0002 | [One prefix for everything the plugin names](0002-one-prefix-for-everything-the-plugin-names.md) |
| 0003 | [Services are declared explicitly, without autowiring](0003-explicit-services-no-autowiring.md) |
| 0004 | [A `Bundle` class with `SyliusPluginTrait`, not `AbstractBundle`](0004-bundle-class-with-sylius-plugin-trait.md) |
| 0005 | [One invokable controller per route](0005-one-invokable-controller-per-route.md) |
| 0006 | [Routes in YAML, request inputs without validation constraints](0006-routes-in-yaml-inputs-without-constraints.md) |
| 0007 | [CSRF is checked by `Screen::validateCsrf()`, not `#[IsCsrfTokenValid]`](0007-csrf-checked-by-screen.md) |
| 0008 | [The engine interfaces are re-aliased with plain aliases](0008-engine-interfaces-re-aliased-with-plain-aliases.md) |
| 0009 | [The menu entry is added at priority `-1024`](0009-menu-entry-at-priority-minus-1024.md) |
| 0010 | [One permission per kind of consequence](0010-one-permission-per-kind-of-consequence.md) |
| 0011 | [No script in the templates, behaviour in Stimulus controllers](0011-no-script-in-templates.md) |
| 0012 | [No file is written, nothing AI-generated is applied without a human](0012-nothing-written-to-files-nothing-applied-without-a-human.md) |
| 0013 | [Only the engine's public contract is used](0013-only-the-engine-public-contract.md) |
| 0014 | [No plugin-side cache of the catalogue rows](0014-no-plugin-side-cache-of-catalogue-rows.md) |
| 0015 | [The plugin exposes no API](0015-no-api.md) |
| 0016 | [No live tracking of a generation](0016-no-live-tracking-of-a-generation.md) |
| 0017 | [`pending_count` stays in the data of the suggestions screen](0017-pending-count-stays-in-the-screen-data.md) |
| 0018 | [The bulk action renderer decorator is kept, outside the public contract](0018-bulk-action-renderer-decorator-kept-off-contract.md) |
| 0019 | [The labels of the syntax issues are not factored](0019-syntax-issue-labels-not-factored.md) |
| 0020 | [The channel code to name map is not cached](0020-channel-name-map-not-cached.md) |
| 0021 | [The `.gitignore` names the maintainers' local tooling](0021-gitignore-names-maintainers-tooling.md) |
| 0022 | [The guard against double billing is the engine's](0022-double-billing-guard-is-the-engine.md) |
| 0023 | [A deleted channel keeps its overrides, suggestions and journal](0023-deleted-channel-keeps-its-translations.md) |
