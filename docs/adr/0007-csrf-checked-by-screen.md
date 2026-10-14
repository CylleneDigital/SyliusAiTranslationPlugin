# 0007. CSRF is checked by `Screen::validateCsrf()`, not `#[IsCsrfTokenValid]`

- Status: accepted
- Date: 2026-10-13

## Context

`#[IsCsrfTokenValid]` throws an authentication exception on an invalid token. Tried, it sends a
logged-in admin to the login page instead of answering 403.

## Decision

A state-changing controller checks its token through the `Screen` service, against the intention
of its screen, and an invalid token throws an `AccessDeniedException`.

## Consequences

- An invalid token is a 403 for a logged-in admin.
- Rejecting a suggestion accepts a second token: the one Sylius' grid delete modal posts, named
  after the row. The action is reached from that modal and from the review modal, and checks
  whichever came, still through `Screen`. Requiring the screen's token there would answer 403 to
  a rejection from the grid.
- One request is answered before the check, on purpose: an import PHP emptied for its size (over
  `post_max_size`, the token goes with the file). No override is read nor written there: the admin
  is told why, and the import that was waiting is withdrawn, like on any refused upload - the most
  a forged request could do on that path.

## Related

- [0005. One invokable controller per route](0005-one-invokable-controller-per-route.md)
