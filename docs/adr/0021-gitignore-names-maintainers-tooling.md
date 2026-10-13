# 0021. The `.gitignore` names the maintainers' local tooling

- Status: accepted
- Date: 2026-10-13

## Context

The maintainers work with local files that are not distributed (a Docker stack, a task runner,
private notes). They could be ignored per workstation in `.git/info/exclude` instead.

## Decision

They are listed in the versioned `.gitignore`, under "Maintainers' local tooling, not
distributed".

## Consequences

- A `git add -A` on a new workstation cannot ship them.
- The repository names files a contributor will never see; the stack contributors use is the
  versioned `compose.yml`.
