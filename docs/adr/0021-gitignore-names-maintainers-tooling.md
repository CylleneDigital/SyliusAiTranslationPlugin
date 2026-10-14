# 0021. The `.gitignore` names the maintainers' local tooling

- Status: accepted
- Date: 2026-10-13

## Context

The maintainers work with local files that are not distributed (a Docker stack, a task runner,
private notes). They could be ignored per workstation, in Git's local exclude file, instead.

## Decision

They are listed in the versioned `.gitignore`, in a section of their own.

## Consequences

- A `git add -A` on a new workstation cannot ship them.
- The repository names files a contributor will never see; the stack contributors use is the
  versioned Compose one.
