# 0009. The menu entry is added at priority `-1024`

- Status: accepted
- Date: 2026-10-13

## Context

A host that reorders its admin root menu with a fixed list of children hits KnpMenu's "order does
not contain all children" when an entry is added before its own listener runs.

## Decision

The listener adding the plugin's entry runs at priority `-1024`, after the usual host listeners.

## Consequences

- The entry lands after a host's reordering, at the end of the menu, instead of breaking it.
- A host that wants it elsewhere moves it in a listener of a lower priority. A host that reorders
  with a fixed list there must name the plugin's entry in it, or reorder tolerantly (the children
  it names in its order, the others after them): a list without it raises the same KnpMenu
  exception.
