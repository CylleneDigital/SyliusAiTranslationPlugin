## What this pull request does



## Points to watch

- [ ] Tests pass (`vendor/bin/phpunit`, `vendor/bin/behat --strict`)
- [ ] PHPStan and ECS are green
- [ ] `UPGRADE.md` is up to date if the public surface changes (routes, Twig hooks, templates, role, menu entry, services aliased over the engine)
- [ ] A decision taken or reversed has its record in `docs/adr/`
- [ ] No API key, customer data or pre-production URL appears in the code, the tests or this description

## If the pull request needs a newer engine

Name the `cyllene-digital/ai-translation-bundle` version it relies on and raise the constraint in
`composer.json` in the same pull request.
