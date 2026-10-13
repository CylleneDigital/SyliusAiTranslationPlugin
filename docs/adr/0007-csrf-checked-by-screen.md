# 0007. CSRF is checked by `Screen::validateCsrf()`, not `#[IsCsrfTokenValid]`

- Status: accepted
- Date: 2026-10-13

## Context

`#[IsCsrfTokenValid]` throws an authentication exception on an invalid token. Tried, it sends a
logged-in admin to the login page instead of answering 403.

## Decision

Every state-changing controller calls `Screen::validateCsrf()`, which throws an
`AccessDeniedException`.

## Consequences

- An invalid token is a 403 for a logged-in admin.
- One request is answered before that check, on purpose: an import PHP emptied for its size (over
  `post_max_size`, the token goes with the file). No override is read nor written there: the admin
  is told why, and the import that was waiting is withdrawn, like on any refused upload - the most
  a forged request could do on that path.

## Related

- [0005. One invokable controller per route](0005-one-invokable-controller-per-route.md)
