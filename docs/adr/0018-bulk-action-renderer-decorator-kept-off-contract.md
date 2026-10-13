# 0018. The bulk action renderer decorator is kept, outside the public contract

- Status: accepted
- Date: 2026-10-13

## Context

The resource bundle's bulk action renderer requires a `ResourceGridView`. The suggestions screen
builds a plain `GridView`: its controller owns the whole page (channel filter, generation form,
review modal), not the resource controller. Without help, "Approve selection" and "Reject
selection" do not render.

## Decision

`src/Grid/BulkActionGridRenderer.php` decorates the Sylius service: a `ResourceGridView` goes to
the decorated renderer untouched, a plain view to the grid bundle's own Twig renderer. It is a
known behaviour, not a promise: it is not in `docs/architecture/public-contract.md`.

## Consequences

- The decorator is on the path of every grid of the host, with no effect on them.
- Another plugin decorating the same service depends on the order of the decorators.
- Removing it means declaring `TranslationSuggestion` as a Sylius resource and going through the
  resource controller: a change of structure of the screen, which can happen without a major
  version since nothing public names the decorator.

## Related

- [0005. One invokable controller per route](0005-one-invokable-controller-per-route.md)
