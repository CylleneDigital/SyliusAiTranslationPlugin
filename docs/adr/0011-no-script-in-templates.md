# 0011. No script in the templates, behaviour in Stimulus controllers

- Status: accepted
- Date: 2026-10-13

## Context

Inline scripts and `on*=` attributes cannot be overridden cleanly by a host, are refused by a strict
Content Security Policy, and no test level of the plugin would see them without a browser.

## Decision

A behaviour is a Stimulus controller under `assets/admin/js/`, registered as `ait-admin-*` on the
plugin's own Stimulus application in `assets/admin/entrypoint.js`. Templates hold no `<script>` and
no `on*=` attribute.

## Consequences

- `tests/Unit/AdminAssetsTest.php` checks both rules, the PHPUnit suite having no browser.
- The modals are filled from the `data-*` attributes of their trigger, through Bootstrap's
  `show.bs.modal`, listened to in `connect()` since the event name has dots.
- A change under `assets/` comes with a `@javascript` Behat scenario.
