# 0015. The plugin exposes no API

- Status: accepted
- Date: 2026-10-13

## Context

The plugin is the back office of the engine: screens for people who read, edit and review
translations. Programmatic access to overrides and suggestions already exists, as the engine's
services and console commands.

## Decision

The plugin adds no API resource nor endpoint beyond what its own screens call (the volume
estimate).

## Consequences

- An integration scripts the engine, not the plugin.
- No API surface to version, secure and document on top of the screens.

## Related

- [0013. Only the engine's public contract is used](0013-only-the-engine-public-contract.md)
