# 0019. The labels of the syntax issues are not factored

- Status: accepted
- Date: 2026-10-13

## Context

The engine returns syntax issue codes (`syntax_icu_invalid`, `syntax_legacy_in_icu`,
`syntax_icu_in_legacy`). The line that translates them and joins them with " · " is written four
times: in `SaveController`, in `ApproveController` and in two templates of the suggestions grid.

## Decision

The four lines stay. A shared version needs a service and a Twig filter: two declarations to save
one line at each place.

## Consequences

- Changing the separator or the key prefix means changing four places.
- To be reconsidered if a fifth place appears.
