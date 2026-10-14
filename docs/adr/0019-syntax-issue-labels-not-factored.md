# 0019. The labels of the syntax issues are not factored

- Status: accepted
- Date: 2026-10-13

## Context

The engine returns syntax issue codes. The line that translates them and joins them into one
message is written once per place that shows them: a few controllers and templates.

## Decision

The lines stay. A shared version needs a service and a Twig filter: two declarations to save one
line at each place.

## Consequences

- Changing the separator or the key prefix means changing each place.
- To be reconsidered if the places multiply.
