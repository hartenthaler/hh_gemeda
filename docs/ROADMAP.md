# GeMeDa roadmap

The module is planned in three deliberately small stages. The first two
stages remain read-only from the webtrees perspective; writing claims is kept
separate because it requires a confirmed service contract and stronger access
control.

## Version 0.1 — foundation and API clarification

- keep the module installable on webtrees 2.2 and 2.3;
- display existing GeMeDa and source identifiers read-only;
- record the current test API base URL and limitations; keep production
  deployment and external write access explicitly unresolved;
- confirm the `contained_in` relation alongside `same_person` in the service
  contract;
- document the agreed provider/type mapping.
- align the implementation with the service data model (`person`, `source`,
  `person_source`, `claim` and `operation_log`);
- register only confirmed provider ID URL templates; keep unresolved source
  homepages out of the EXID catalogue.

## Version 0.2 — public read integration

- implement the public batch lookup with bounded requests and timeouts;
- load the provider catalogue dynamically and offer administrator and
  per-search provider selection;
- query the public meta-search proxy sequentially with isolated provider
  failures and render provider-grouped read-only results;
- show matched GeMeDa identities and linked provider identifiers;
- add cache and graceful fallback behaviour;
- provide consistency information without modifying GEDCOM data.
- distinguish a `person_record` from a `container` and preserve that distinction
  in the UI and API mapping;
- treat different existing GeMeDa persons in one batch as a conflict rather
  than merging them.
- do not make the uncertain `GET /lookup`, `/search` or `/persons/:hash`
  routes a hard dependency.

## Version 0.3 — controlled claim workflow

- add an optional, administrator-controlled claim workflow;
- keep service keys server-side and restrict writing to explicitly authorised
  webtrees users;
- write confirmed GeMeDa and provider identifiers as separate `_EXID` blocks;
- handle conflicts, corrections, withdrawals and audit information according
  to the confirmed API contract.
- keep the `contained_in` relation separate from `same_person` claims;
- proceed only after the current local-only test access, per-installation key
  model, secure storage and rate-limit policy have been clarified;
- add administrative correction, merge/split and source-history operations
  only after the service contract defines them.

The stages are intentionally dependent: the write workflow must not be
implemented before the public read contract and the service terms are clear.
