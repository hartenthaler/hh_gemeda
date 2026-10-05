# GeMeDa requirements

The requirements below incorporate the [GeMeDa database model specification
(28 September 2026)](https://cloud.rpi.digital/index.php/s/RAT3RRqjPWqqGD4)
and the [portal API reference](https://gitlab.genealogy.net/system0/zentrum-der-projekte/-/blob/main/docs/gemeda-api.md).

This document records the first implementation boundary and the questions that
must be answered before the module writes claims to GeMeDa.

## Phase 1 scope

The module adds an individual tab. Existing `_EXID` blocks are read from the
local GEDCOM record and shown to all visitors. A later write-enabled phase may
be used only by webtrees users explicitly listed by the administrator.

The first phase does not perform batch imports, import external person data,
rate claims, or modify existing GeMeDa records. It must continue to work as a
read-only module when the API cannot be reached.

Issue #18 extends the read-only phase with a provider meta-search. The module
loads the provider catalogue from `GET /api/v1/providers`, stores only the
administrator's enabled provider IDs, and queries the public
`https://meta.genealogy.net/proxy` endpoint for the providers selected by the
user. Results are displayed without a GEDCOM write action.

## Actors

- **Visitor:** may read the tab, never write.
- **Editor:** may read the tab unless explicitly authorized.
- **GeMeDa contributor:** an editor whose webtrees user ID is in the module
  allow-list; this role is reserved for the future claim form.
- **Administrator:** configures the API endpoint, secrets and allow-list.

## API findings from the public project

The public [GeMeDa API reference](https://gitlab.genealogy.net/system0/zentrum-der-projekte/-/blob/main/docs/gemeda-api.md)
and the current backend implementation provide substantially more detail than
the initial specification:

- The documented base path is `${GEMEDA_API_BASE_URL}/api/v1`.
- `POST /lookup/batch` is a public read operation. It accepts one to 100
  sources (`provider` and `externalId`) and returns the associated person hash,
  display name, linked sources, confirmation count and likelihood breakdown.
- `POST /claims` is a write operation. It requires
  `Authorization: Bearer <GEMEDA_SERVICE_KEY>`, two to 100 unique sources, a
  display name and one of `sehr_wahrscheinlich`, `wahrscheinlich` or
  `unwahrscheinlich`.
- Claim creation is transactional; GeMeDa creates the required source links,
  claims, aggregates and operation log together or rolls the transaction back.
- Public person responses do not expose `contributor_id`.
- The service configuration is kept outside the repository, in the deployed
  `/var/private-config/gemeda-api.php` file. The repository does not reveal the
  production host, key-issuance process, rate limits or third-party usage terms.

## Status from the GeMeDa team (29 September 2026)

- The current test base URL is `https://api.gemeda.rpi.digital`; it is not a
  production endpoint. At present there is only a test environment.
- The documented batch lookup and claims operations are available for testing,
  but claim creation is currently restricted to local testing by the service.
- Bearer authentication is the current mechanism. One shared service key is
  used at present; there is no per-webtrees-installation key process yet.
- No rate limits or caching rules have been defined. The module must therefore
  use conservative timeouts, bounded responses and versioned caching itself.
- The additional `GET` routes are not guaranteed. The provider catalogue
  endpoint is therefore treated as optional: until it is deployed, the module
  shows an availability warning and does not fall back to a permanently copied
  provider list.
- There is no admin portal for deleting, correcting, withdrawing or merging
  records yet.
- The service now needs a `contained_in` relation alongside `same_person` for
  source/container cases. It is not a same-person merge and must remain a
  separate relation.

The current project issue list is available at the [GitLab
Issues](https://gitlab.genealogy.net/system0/zentrum-der-projekte/-/issues).

The current service data model is deliberately source-oriented: a source is
identified by the unique pair `(provider, external_id)`, can be a
`person_record` or a `container`, and is assigned to at most one GeMeDa
person. A GeMeDa person is identified publicly by `person_hash`; its display
name is only a snapshot. Same-person claims connect sources and are recorded
with a confidence value and an operation log.

The implementation must follow the service's conflict rules: create a person
when none of the selected sources is known, reuse the existing person when
all known sources belong to the same person, and stop with a conflict when
different existing persons are involved. No automatic merge is allowed in the
webtrees module. A future `contained_in` relation is not a person merge and
must remain separate from same-person claims.

The reference also lists `GET /lookup`, `GET /persons/{personHash}`,
`GET /search` and `POST /claims/{claimId}/rate`. The current router primarily
exposes `/health`, `/api/v1/lookup/batch` and `/api/v1/claims`; the other routes
must be treated as optional until Robert confirms their deployment and
external-client support.

The current portal client uses `GET /api/v1/lookup/search?q=...` for person
search. The module follows that route; the former `POST /api/v1/search`
assumption is no longer used.

For the first module phase, the public batch lookup is sufficient in principle.
No write operation or service key is needed until the module is extended to
create GeMeDa claims.

## GEDCOM mapping

The proposed write result is:

```gedcom
1 _EXID gmd_a1b2c3d4
2 TYPE gemeda
1 _EXID 123
2 TYPE gedbas
1 _EXID 456
2 TYPE ofb
```

The GeMeDa person hash and each returned provider identifier are separate
external identifiers. The official type URI for a provider is only added when
the provider documents a stable URL template. The currently confirmed
templates from the Genealogienetz portal are documented in
[docs/provider-uris.md](provider-uris.md); a provider homepage or search URL
must not be mistaken for an identifier URL.
