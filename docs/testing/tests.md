# Tests

Four levels, from the fastest to the closest to an administrator.

| Level | Where | What it needs |
|---|---|---|
| Unit | `tests/Unit/` | Nothing: no kernel, no database |
| Integration | `tests/Integration/` | The test application's kernel and a database, no HTTP |
| Functional | `tests/Functional/` | The same, through the HTTP kernel, with an admin logged in |
| Behat | `features/`, `tests/Behat/` | The same; a Chrome for the `@javascript` scenarios |

```bash
vendor/bin/phpunit --testsuite=unit
vendor/bin/phpunit --testsuite=non-unit     # integration and functional
vendor/bin/behat --strict --no-interaction -f progress --tags='~@javascript'
```

Setting up the environment, the database and Chrome: [CONTRIBUTING](../../CONTRIBUTING.md).

## Unit tests

They guard what no screen exercises, among others:

- `EngineContractTest`: the list of every engine class the plugin imports, and of the members it
  reads on the objects the engine returns. A new coupling fails the test until it is listed.
- `ReplaceableServicesTest`: nothing in `src/` is typed on a service a host may replace.
- `AdminAssetsTest`: no script in the templates, and every Stimulus controller they name is
  registered in the entrypoint.
- `DependencyInjection/ThemeTranslationRootsTest`: a theme added while developing makes the
  container stale.

## Integration and functional tests

They boot the test application (`tests/TestApplication/`) on a real database and create the data
they need, through the entities: no fixture is loaded. `tests/SyncsTestSchema.php` creates the
schema from the mapping, so an empty database is enough - except on a MariaDB declared as a MySQL
and older than 11.4 (what Sylius 2.3.0 required for its migrations), which gets its schema from the
migrations ([CONTRIBUTING](../../CONTRIBUTING.md)).
Its `deleteOverridesAndSuggestions()` is how a test starts from an empty slate: it deletes the rows
behind the engine's back, and empties `cache.app` with them - the engine would otherwise still
serve the overrides of the previous test.

A generation is only queued: no test consumes the transport, so no AI provider is ever called, and
the keys of the test application are dummies. The suggestions the tests review are created as
rows. The test application's `translations/` and `themes/` directories are the "host project"
catalogues the tests browse.

No test builds an engine service by hand: its constructors are not part of its contract. A test
that needs the real engine takes it from the container. What a view costs is therefore counted
on the real engine too: `tests/Integration/View/TranslationViewFactoryTest.php` reads the queries
Doctrine's debug middleware collected, and checks that the number of catalogues of a view does
not change it; `tests/Integration/Twig/SuggestionReviewExtensionTest.php` does the same for a page
of the suggestions screen, read for each locale and channel it holds.

## Behat

One suite per feature, selected by the tag of its file name (`tests/Behat/Resources/suites.php`).
The back-office scenarios are under `features/admin/`; those of `features/shop/` check that what
was done there reaches the storefront - and that a pending or rejected suggestion does not.

The `@javascript` scenarios are the only thing that runs the Stimulus controllers: a change under
`assets/` comes with one, and the test application's front end has to be rebuilt first.

Pages are under `tests/Behat/Page/` (`Admin/Translation`, `Admin/Suggestion`, `Shop`), the channel
form section under `tests/Behat/Element/Admin/Channel/`. The setup context empties `cache.app`
before each scenario: Sylius' purge truncates the tables behind the engine's back.

## Continuous integration

The matrix runs every level on Sylius 2.1, 2.2 and 2.3, PHP 8.3 to 8.5, on MySQL 8.4, and on the
primary combination on MySQL 8.0, MariaDB 10.11 and 11.4, PostgreSQL 15 to 17; the `@javascript`
scenarios only run on the primary combination. A separate job runs PHPUnit and the static checks
on the oldest engine release the constraint allows, and a weekly one on the engine's unreleased
`main`.
