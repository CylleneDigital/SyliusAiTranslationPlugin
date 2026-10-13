# 0011. No script in the templates, behaviour in Stimulus controllers

- Status: accepted
- Date: 2026-10-13

## Context

Inline scripts and `on*=` attributes cannot be overridden cleanly by a host, are refused by a strict
Content Security Policy, and no test level of the plugin would see them without a browser.

## Decision

A behaviour is a Stimulus controller, registered on the plugin's own Stimulus application by the
entrypoint a host imports. Templates hold no `<script>` and no `on*=` attribute.

## Consequences

- A unit test checks both rules, the PHPUnit suite having no browser.
- The modals are filled from the `data-*` attributes of their trigger, when Bootstrap shows them.
- A change of behaviour comes with a `@javascript` Behat scenario, the only level that runs the
  controllers.
