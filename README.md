# GeMeDa for webtrees

GeMeDa (Genealogische Meta-Datenbank) is intended to connect people in a
webtrees tree with identities in genealogical source databases. This module is
the webtrees-side integration: it displays local GeMeDa-related identifiers
and will later support carefully controlled claim creation.

The project background is described in the [Zentrum der Projekte](https://gitlab.genealogy.net/system0/zentrum-der-projekte)
and in the [CompGen article about the genealogical research centre](https://www.compgen.de/2026/09/das-neue-zentrum-der-genealogischen-forschung-idee-und-stand-der-arbeiten-fuer-das-genealogienetz-portal/).

## Current status

The initial scaffold provides:

- an individual tab for existing local `_EXID` records whose `TYPE` identifies
  GeMeDa or a linked source database;
- a read-only display for visitors and editors;
- an administration page for the future API endpoint, server-side secret,
  contributor pepper and explicit contributor allow-list;
- an administration setting for the tag used by future new identifiers:
  `EXID` (GEDCOM 7) or `_EXID` (GEDCOM 5.5.1);
- an API abstraction which currently fails safely. The public project already
  documents a batch lookup endpoint and a bearer-authenticated claim endpoint;
  the current test endpoint is known, while the production URL,
  external-client permissions and operational limits still need confirmation.

The current test endpoint is `https://api.gemeda.rpi.digital/api/v1`. It is
not a production service: only the test environment exists, and claim writes
are currently restricted to local testing by the GeMeDa service. A single
shared Bearer key is used at present; a per-installation key and its secure
storage remain open production decisions.

No external record is imported and no claim is written in this phase.
Existing `EXID` and `_EXID` entries are read equally; the administrator's tag
choice affects only identifiers created in a later write-enabled phase.

## Requirements

- webtrees 2.2 or 2.3;
- the PHP version supported by the installed webtrees release;
- administrator access only if the optional API settings are to be configured.

## GEDCOM representation

The planned mapping follows the specification supplied with this module:

```gedcom
1 _EXID gmd_a1b2c3d4
2 TYPE gemeda
1 _EXID 123
2 TYPE gedbas
1 _EXID 456
2 TYPE ofb
```

The exact provider catalogue and the returned GeMeDa link structure remain
configurable once the official API contract is available.

## Installation

### Manual installation

Copy the `hh_gemeda` directory into `modules_v4`, enable **GeMeDa** in the
webtrees control panel and open its administration page. The module works in a
read-only mode before API settings are configured.

### Composer / CMM

The package declares type `webtrees-module` and the official
`webtrees/module-installer` dependency. In a webtrees source checkout, Composer
or a compatible Custom Module Management workflow can install it below
`modules_v4` automatically.

## Security and privacy

API secrets are entered only on the administration page and are used
server-side. During the current test phase, one shared service key is used and
the GeMeDa service restricts claim writes to local testing. The contributor
allow-list is explicit; an ordinary visitor or editor cannot create claims.
Before production, per-installation credentials (or a documented alternative),
secure storage, rate limits and correction workflows still need to be defined.
The module must remain useful in read-only mode if the API is unavailable. The
final data-retention and privacy wording depends on the GeMeDa service
agreement.

## Documentation

- [Requirements and open questions](docs/requirements.md)
- [Architecture and implementation plan](docs/architecture.md)
- [Provider URLs and EXID mapping](docs/provider-uris.md)
- [Roadmap](docs/ROADMAP.md)
- [Development and validation](docs/DEVELOPMENT.md)

## Translation

The module-specific user interface is translated with gettext PO/MO files.
Terms already translated by webtrees should continue to use the core
translation layer rather than being duplicated in this module.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
