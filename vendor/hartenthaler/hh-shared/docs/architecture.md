# Shared library architecture

## Purpose

Several Hartenthaler modules need the same small pieces of compatibility code.
The shared library is a normal Composer package, not an enabled webtrees
module. It provides one stable namespace and one source of truth for code that
is genuinely common to more than one module.

The library must not patch webtrees core. Compatibility is implemented through
small adapters and feature detection.

## Influences

The following projects were reviewed:

- [`bschwede/wt-shared-libs`](https://github.com/bschwede/wt-shared-libs) uses
  shared helpers for I18N, class names, routes, API differences and module
  traits. Its scope is a useful reference, but its custom version-aware loader
  and broad helper collection should not be copied wholesale.
- [`Jefferson49/webtrees-common`](https://github.com/Jefferson49/webtrees-common)
  uses a root-level `autoload.php`, separates helpers by responsibility and
  contains compatibility code for webtrees 2.2/2.3. The accompanying
  discussion recommends `"prepend-autoloader": false`, loading other Composer
  packages before the common library, and avoiding shared-library calls from a
  module constructor.

The present package follows the useful structural ideas while using ordinary
PSR-4 Composer autoloading. The root `autoload.php` is only a manual-copy
fallback.

## Directory structure

```text
hh_shared/
├── autoload.php              # manual-copy fallback
├── composer.json             # normal PSR-4 package definition
├── docs/
│   └── architecture.md
├── resources/                # only if shared resources are introduced
└── src/
    └── Internationalization/
        ├── MoreI18N.php
        └── TranslationLoader.php
```

The namespace is `Hartenthaler\\Webtrees\\Shared\\`. Domain-specific code
such as EXID catalogues, place-provider parsing, GOV types and coordinate
logic remains in its owning module.

## First shared components

### `MoreI18N`

`MoreI18N::xlate()`, `xlateContext()`, `plural()` and `number()` call the
corresponding webtrees core methods under names that gettext does not mistake
for new module strings. This allows modules to reuse core translations without
duplicating them in their own POT files.

### `TranslationLoader`

`TranslationLoader::load()` first uses the webtrees 2.3
`Fisharebest\\Webtrees\\I18N\\Translation` API and then falls back to the
webtrees 2.2 `Fisharebest\\Localization\\Translation` API. It accepts PO or
MO files and always returns an array suitable for
`ModuleCustomInterface::customTranslations()`.

The loader closes streams reliably and never passes a missing filename to
`str_ends_with()`.

## Compatibility policy

- Support webtrees 2.2 and 2.3 explicitly; do not retain obsolete 2.1 names
  unless a module still needs them.
- Detect the available core class or method at the boundary of an adapter.
- Keep the public shared API small and document every compatibility decision.
- Add a regression test or a reproducible check before moving code from a
  module into this package.
- Keep shared releases versioned independently and pin a compatible range in
  consuming modules.

Possible later adapters include changed access-level enums, facts signatures,
route registration and controller class names. They should be extracted only
after a second module needs the same adapter and both webtrees versions have
been tested.

CLI bootstrap code, provider clients, EXID logic and place-specific
normalisation are optional components and should not be added to the common
core merely because they are useful in one module.

## Autoload and distribution

The published package uses Composer PSR-4 autoloading. A consuming module may
load its Composer dependencies in its root `autoload.php`; if a manual module
checkout is used during development, that file may additionally load a
sibling `hh_shared/autoload.php`.

If a module's `composer.json` uses additional packages, Composer should be
configured with `"prepend-autoloader": false` so the module's own dependencies
are loaded before the shared package. The shared package must not be referenced
from the module constructor; use it from `boot()`, request handlers, services
or other code that runs after the module autoloader has been registered.

Before publishing a module release, test both distribution paths:

1. Composer/CMM installation with the shared package resolved as a dependency;
2. manual installation of the release archive, including the required shared
   package files or a documented dependency installation step.

The chosen release process must never silently leave a module with a missing
shared dependency.

## Pilot migration: `hh_exid_report`

The report is the first consumer because it has one isolated duplicate: its
2.2/2.3 translation loader. The migration replaces that method with
`TranslationLoader` and uses `MoreI18N` for core strings such as “Family tree”,
“Label” and “Count”. Its EXID report logic remains in the report module.

Validation covers PHP linting, Composer validation, PO/MO checks, and a manual
check with both translation-class APIs. Only after this pilot is stable should
other modules migrate.
