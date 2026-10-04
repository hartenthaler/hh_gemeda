# hh_shared

`hh_shared` is a small Composer library containing stable, reusable helpers
for Hartenthaler webtrees modules. It is not an enabled webtrees module and it
does not patch webtrees core.

The first shared components are the webtrees-core translation wrappers and a
translation loader compatible with webtrees 2.2 and 2.3. Additional helpers
are added only when at least two modules need the same behaviour.

## Installation

The library is normally installed as a Composer dependency of a custom module.
For a manual development checkout, the root `autoload.php` can be loaded by a
module from a sibling `hh_shared` directory.

## Documentation

- [Architecture and migration plan](docs/architecture.md)

## License

GPL-3.0-or-later.
