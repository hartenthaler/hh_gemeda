# Development

## Working copy

Keep the canonical checkout below `Documents/Codex/webtrees-modules`. If it is
connected to a local webtrees test installation, use a junction rather than a
second edited copy.

## Validation

Run PHP linting for every changed PHP file and check the patch for whitespace:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

Compile changed gettext catalogs to their matching MO files. Before a pull
request, run the shared `Test-WebtreesModule.ps1` validation script when it is
available in the local tooling checkout.

## API safety

Do not invent an API endpoint, response field or authentication flow. Until the
GeMeDa service contract is confirmed, keep the unavailable API implementation
and the local read-only tab. Never log service keys or contributor peppers.

## Releases

Keep meaningful user-facing changes in `CHANGELOG.md`. Release notes should be
derived from the user-visible changes since the previous release; internal
scaffolding and temporary diagnostics do not belong there.
