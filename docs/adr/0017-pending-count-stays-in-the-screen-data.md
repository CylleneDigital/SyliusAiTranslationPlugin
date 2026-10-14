# 0017. `pending_count` stays in the data of the suggestions screen

- Status: accepted
- Date: 2026-10-13

## Context

The suggestions screen counts the pending suggestions of the channel on every display and passes
`pending_count` to its hooks. No template of the plugin reads it. The theming guide lists it
among the data a host overriding the screen can use, and a released version carries it.

## Decision

It stays.

## Consequences

- One `COUNT` query per page.
- Removing it would break a host template that reads it: not before a major version, with an
  entry in the upgrade guide.
