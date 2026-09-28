# GeMeDa roadmap

The module is planned in three deliberately small stages. The first two
stages remain read-only from the webtrees perspective; writing claims is kept
separate because it requires a confirmed service contract and stronger access
control.

## Version 0.1 — foundation and API clarification

- keep the module installable on webtrees 2.2 and 2.3;
- display existing GeMeDa and source identifiers read-only;
- confirm the production API base URL, supported routes, usage terms and test
  access with the GeMeDa team;
- document the agreed provider/type mapping.

## Version 0.2 — public read integration

- implement the public batch lookup with bounded requests and timeouts;
- show matched GeMeDa identities and linked provider identifiers;
- add cache and graceful fallback behaviour;
- provide consistency information without modifying GEDCOM data.

## Version 0.3 — controlled claim workflow

- add an optional, administrator-controlled claim workflow;
- keep service keys server-side and restrict writing to explicitly authorised
  webtrees users;
- write confirmed GeMeDa and provider identifiers as separate `_EXID` blocks;
- handle conflicts, corrections, withdrawals and audit information according
  to the confirmed API contract.

The stages are intentionally dependent: the write workflow must not be
implemented before the public read contract and the service terms are clear.
