# Back office

Two screens, under the **Translations** menu of the Sylius admin (routes imported under the
admin prefix, hence protected by the admin firewall).

What follows describes the screens as an admin holding every permission sees them. Each kind of
action has its own role - reading, editing, importing, reviewing, generating - and the screens
only show what the admin may do: see [Permissions](../integration/permissions.md).

## The channel filter (both screens)

Under the header, the first block of each screen is a **Channel** dropdown: "Global (all channels)" or one
of the Sylius channels. It is a **page-level filter** carried by the URL (`?scope=CODE`,
a full reload on change, shareable): every block below is computed **for that channel**,
with the runtime semantics (a channel sees its own overrides, else the inherited global
ones, else the file values; a regional locale also inherits its language's overrides:
`fr_FR` falls back on `fr`), and the locales narrow to the ones the channel exposes.
Links between the two screens carry the channel along.

## Channels › edit: the "AI translation" section

The channel form (create and update) gains an **AI translation** card with one field,
**Context for the AI**: free text appended to the prompt of every suggestion generated
**for that channel** (audience, tone, vocabulary, brand, channel specifics), after the
global `llm_context` of the configuration. It has no effect on global generations nor on
other channels, is never translated itself, and is sent to LLM providers as an instruction
and to DeepL as its steering `context`. The cost estimate of the Suggestions screen
includes it. Stored by the engine in its per-scope settings table (scope = channel code):
the host's `Channel` entity is not touched, and deleting a channel deletes its settings.

## Translations › Catalogue (`/admin/translations`)

The catalogue browser is a **Sylius grid** over computed rows (`CatalogueGrid` +
`CatalogueGridProvider`, PHP-configured; a host project extends it like any Sylius grid):

- **Everything listed by default**: with no filter, the table shows every catalogue of the
  project (grouped by catalogue, "Show 100/25/50" page-size selector, sortable key). The
  filters, in the standard accordion, only narrow: **catalogue** (a select grouped by
  category, each group opening on a "Whole category" entry), **locale** (the channel's locales when a channel is selected, labelled
  `[fr_FR] Français (France)`), full-text **search** (key, original, override, inherited value, reference),
  **untranslated only**, **modified only**. "Modified only" also lists the overrides whose
  catalogue no longer exists in the files (cleanup candidates). With a channel selected,
  the rows show the channel's override, the global value as "inherited". An inherited value
  says where it comes from: "inherited from global", "inherited from fr" (the parent
  language of a regional locale), "inherited from fr (global)" in a channel view…
- **Untranslated rows**: the rows are the union of the browsed locale's keys with the
  keys of every other locale of the view: a key with no translation yet still gets a row, flagged
  "untranslated", ready to be filled in by hand; the **untranslated only** filter turns
  the table into the hand-translation worklist. A **Reference column** shows the text to
  translate from: the selected channel's default locale, else the coverage default locale, with
  a per-row default-locale fallback; hidden when the reference is the browsed locale itself.
  Display-only: "missing" stays defined against the default locale, like the coverage.
- **Edit modal**: clicking any text cell (or the row's pencil) opens the translation in
  full: reference and original side by side, the current value edited by hand, and
  **"remove the override"** (shown when one exists). The save follows the engine's rule for
  every door that writes a value: a value the entry **already shows writes nothing**, the
  value it would show without its override (the one it inherits, else the file value)
  **deletes the override** instead of storing a copy; saving on an untranslated row simply
  creates it. An **empty value is refused** where the entry has a text to show: an empty
  override would be served as an empty text ("remove the override" is how one goes). The value is kept as typed, edge spaces included. Writing an override
  **closes the key's pending AI suggestions** (the flash says how many): the value someone
  chose replaces the proposal. Every action returns to the list as it was (channel,
  filters, sorting, page). A click beside the modal does not close it, so that a text being
  typed is not lost: Escape, the cross and "Cancel" do, and so for the review modal.
- **Coverage**: per-locale cards at the top of the page (progress bar, % against the
  engine's `default_locale`, missing keys, pending suggestions counted apart), **for the
  selected channel**: a key counts as translated when the channel's effective value (own
  override, inherited override or file value) is non-empty, and the pending
  suggestions are the channel's. The "N missing keys" line opens the **catalogue browser on
  that locale, untranslated rows only** (and channel), the hand-translation worklist,
  with a sparkles link next to it, shown to who may generate, for the **AI generation form**
  preset on default locale → that locale; "N pending review" links to the **suggestion list
  filtered on that locale** (and channel). A third line, "N modified translations", counts the
  overrides **owned by the selected channel** (global ones in the global view: what was
  customised for it, not what it inherits) and opens the browser pre-filtered on "this
  locale, modified only" within the channel. Cached per channel according to
  `coverage_cache_ttl` (default: no cache in debug, 5 min otherwise).
- **Orphan overrides**: when overrides point at catalogues gone from the files, a warning
  banner shows their count with a **Purge** button (confirmation, CSRF POST): what the
  engine's interactive console offers as its "purge orphans" journey.
- **Export this view**: the page header's dropdown downloads **exactly what the table
  lists**, in the same order (every row with its original and effective value: override, inherited or file
  value; untranslated rows included as empty values), in **XLIFF or CSV** (the XLIFF
  puts the original in `<source>`, the CSV carries an `original` column). That is the
  artifact to hand a translator or review in a spreadsheet. The full **delta backup** (only
  the overrides, every locale and channel in one file, restored as-is) lives in the
  `export-overrides` CLI command.
- **Import**: header button opening a **modal**: file field, a collapsible "Which format?"
  block with one example per format, "download a ready-to-fill template" links. The upload
  writes nothing: the dashboard shows a **preview card** (N overrides would be imported,
  invalid entries skipped and **each named** (its row or trans-unit, its key and why: a
  locale the host does not know or its channel does not offer, a value that breaks the
  catalogue's syntax...),
  overrides removed when the imported value is the one the entry would show without them,
  already-up-to-date or empty values ignored, per-locale breakdown) to **confirm or
  cancel**; the skipped entries are listed again after the import. A new upload, refused or
  not, withdraws the one that was waiting. The import **diffs
  against the effective baseline**: re-importing a corrected view snapshot only turns the
  changed values into overrides, and a value equal to the override already stored is left
  alone. Format detected by extension, CSRF, 8 MB cap (less when PHP's own upload limit is lower: the screen announces the real one), real media type
  cross-checked. A refusal says what to do about it: a file too big names the limit, an
  interrupted upload asks to try again, a server that could not store the file points to its
  administrator.
- **Pagination**: 100 rows per page (a large catalogue can hold thousands); any filter
  change returns to the first page.
- Changes are **visible immediately** (automatic cache invalidation, see the engine's
  [Caches](https://github.com/CylleneDigital/AiTranslationBundle/blob/main/docs/operations/cache.md) page).

## Translations › AI suggestions (`/admin/translations/suggestions`)

The generation and review screen for `pending` suggestions, **per channel**: a
suggestion carries the channel it was generated for, the list only shows the selected
channel's suggestions (global ones in the global view), and approving writes the override
**in that channel**. Generating for a channel therefore fills that channel only: a key
missing globally stays missing globally, by design (the channel inherits whatever the
global value becomes later). Source and target texts are the channel's effective values,
so a channel-specific wording in the source language drives the suggestion.

- **Estimate volume & cost**: button under the generation form. Shows, for the current
  choices and without sending anything to the provider: keys to translate, source
  characters, API call count, estimated tokens (the real prompts are measured,
  ~4 characters/token), and the **estimated USD cost**, from the list prices of the public LiteLLM
  feed (continuously refreshed, 24 h cache; when unreachable, the estimate falls back to
  volumes only). Each provider answers with its own billing logic
  (`CostEstimatingProviderInterface`): for **DeepL** (no public price list), the estimate
  shows the **account's remaining quota** (`/v2/usage`). Negotiated or non-USD pricing may
  differ. For a reasoning model the cost reads "≥": the tokens it spends thinking cannot be
  counted beforehand, so the figure is a floor. The figures are those of the coordinates they were asked for: they are cleared as
  soon as the provider, a locale, the catalogue or a switch changes.
- **Generate suggestions**: form at the top of the screen (source locale preset on the
  channel's default locale, on the coverage default locale in the global view, target locale (both preset when arriving through a coverage "missing
  keys" link), one catalogue or all, provider (preset on the configured `default_provider`), "All keys"; the channel is the page's).
  Each catalogue departs as a Messenger `GenerateSuggestionsMessage`, dispatched on the
  plugin's own bus (`cyllene_digital_ai_translation.bus`, no `doctrine_transaction`
  middleware: a failing chunk must not roll back the chunks already billed), on the engine's own
  **async transport** (`cyllene_ai_translation`, declared and routed by the engine,
  DSN configurable via `messenger_transport_dsn`): run
  `messenger:consume cyllene_ai_translation` for the suggestions to arrive (the flash
  says the generation is queued; refresh to watch them land). The button locks for a minute
  once clicked, in the browser only: the same form sent again - from a second tab, or after that
  minute - queues its runs again. The engine does not bill a key a second time while its
  suggestion waits for a review (it skips a run while the same one is in progress, and every key
  that already has a pending suggestion); once the suggestion is rejected, or approved before an
  "All keys" run, the key is sent and billed again. A "Retry errors" switch
  re-sends **only** the keys whose previous run failed: no other key, missing or not. A provider failure stores an **errored
  suggestion per unfulfilled key** (no value, confidence 0, the provider error on the
  row) and is journaled in `cyllene_translation_generation_log` without Messenger retry
  (re-generating later is idempotent). A run queued for a channel deleted since is refused
  before anything is sent to the provider: it leaves no journal entry, only an error in the
  worker's log and the message on the failure transport when there is one. A run whose channel
  is deleted while it runs stops before its next chunk, journaled as a failure. The screen
  lists the **last ten runs** of the channel
  being browsed under the grid, failures included with their error: generation being
  asynchronous, that is where a run that produced nothing shows.
- **Save-time syntax validation**: an override (hand edit, import) or an approved
  suggestion whose value does not match its catalogue's declared format (ICU vs legacy:
  the `+intl-icu` file naming) is refused with the reason; generated values only get a
  "Check the syntax" badge and the detail in the review modal.
- **The pending list is a Sylius grid** (`SuggestionGrid`, PHP-configured; a host project
  extends it like any Sylius grid): filters in the standard accordion (catalogue, as
  "Category › Domain — Type", locale (the channel's), "Generation failed", full-text search
  on key / source / proposal), **sortable confidence**
  (default: highest first), the "Show 25 / 50 / 100" page-size selector, pagination.
- **Per row**: key + catalogue, source text (and its locale), proposed translation (with an
  **"Placeholders altered"** badge when the translation lost or added a placeholder, a
  **"Plural forms missing"** one when an ICU plural lacks a category the target language
  needs (valid ICU, but "2th"), an **"Already shown"** one when the proposal is the
  entry's current value (checks computed when the page is rendered, as the approval runs
  them, not read from what the generation stored), and a red
  **"Generation failed"** badge (full error on hover and in the review modal) on errored rows,
  whose value must be typed in before approval (the row's approve button is disabled, the bulk
  skips them, the server refuses them); a grey **"Sent to the provider"** one instead on the rows
  the engine reserved before calling the provider: their run is waiting for the reply, or was
  stopped while it did - they stay so, and "Retry errors" sends them again. The "Generation
  failed" filter counts them too),
  target-locale badge, confidence badge (green `>= 90%`, orange `>= 70%`, red below,
  self-assessed by the LLMs), provider badge. Clicking the **source or proposal cell opens
  the review modal**.
- **Review modal**: the full source text (read-only), the **current value** of the entry
  and where it comes from (override, inherited, file), the editable proposal, the
  placeholder (missing / extra), syntax and plural warnings when relevant, a notice while
  the value equals the current one, and both decisions: **approve** (with the value as
  edited; the `edited_on_approve` trace is kept) or **reject**. The approval's flash says
  what it did to the override: written, nothing (already the value shown), or removed
  (the value shown without it). Long texts are readable here whatever the column width.
- **Row actions**: approve as-is, review (the modal), reject (Sylius' own delete action
  with its confirmation modal). **Rejecting deletes the suggestion** (nothing is applied;
  the key becomes eligible again for a later run). A value whose placeholders differ from
  the source's is **refused at approval**, the flash saying which are missing and which
  are extra. A suggestion reviewed meanwhile (another window, another admin) is left as
  it is, with a warning.
- **Bulk actions**: the grid's checkboxes + "Approve selected" / "Reject selected" with a
  confirmation modal each; suggestions with altered placeholders are skipped by bulk
  approval (flash with their keys) and remain for individual review; the flash counts the
  approvals that wrote no override. Every action returns
  to the list as it was (channel, filters, sorting, page).
- Every POST action is **CSRF-protected**.
- A warning shows when **no provider is configured** (the generation form is then hidden).
