# Assets

The plugin ships one Webpack Encore entrypoint, for the admin. Its templates load no script or
stylesheet by themselves and carry no inline script: the application bundles the entrypoint with
its own assets, and a Content Security Policy needs no `unsafe-inline` for scripts.

One inline style remains, the computed width of the coverage bars (`style="width: 42%"`): a
policy that restricts styles has to allow it, with `style-src-attr 'unsafe-inline'`.

## Import the entrypoint

```js
// assets/admin/entrypoint.js
import '../../vendor/cyllene-digital/sylius-ai-translation-plugin/assets/admin/entrypoint.js';
```

Then build:

```bash
yarn install
yarn build
```

`yarn encore dev --watch` while developing, `yarn encore production` for production, as for the
rest of a Sylius application. The entrypoint only needs what `@sylius-ui/admin` already brings
(`@hotwired/stimulus`, Bootstrap's modals).

Without the import, both screens render unstyled and every interaction below stays inert, with
no error message.

## Content

| Source | Role |
|---|---|
| `assets/admin/css/admin.css` | the plugin's rules: compact modal textareas, truncated grid cells, filter helps |
| `assets/admin/js/*-controller.js` | the Stimulus controllers, registered on the plugin's own Stimulus application |

| Identifier | Role | Used in |
|---|---|---|
| `ait-admin-edit-modal` | fills the edit modal from the row that opened it | catalogue screen |
| `ait-admin-header-help` | attaches the column helps to the grid headers | catalogue screen |
| `ait-admin-review-modal` | fills the review modal, points its two decisions at the suggestion's routes, disables approval on an empty value, says when the row is still with the provider | suggestions screen |
| `ait-admin-generation` | fetches the cost estimate (cleared as soon as a coordinate changes), asks for confirmation before a run on every catalogue, ignores a second submit | suggestions screen |
| `ait-admin-scope-filter` | reloads the page on the chosen channel and carries it through Sylius' filter form | both screens |
| `ait-admin-confirm` | asks before a form is submitted | orphan purge |

The modals are Bootstrap's: each trigger carries the data of its row as `data-*` attributes, and
the controller reads them from the `show.bs.modal` event.
