# GeMeDa requirements

This document records the first implementation boundary and the questions that
must be answered before the module writes claims to GeMeDa.

## Phase 1 scope

The module adds an individual tab. Existing `_EXID` blocks are read from the
local GEDCOM record and shown to all visitors. A later write-enabled phase may
be used only by webtrees users explicitly listed by the administrator.

The first phase does not perform batch imports, import external person data,
rate claims, or modify existing GeMeDa records. It must continue to work as a
read-only module when the API cannot be reached.

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

The reference also lists `GET /lookup`, `GET /persons/{personHash}`,
`GET /search` and `POST /claims/{claimId}/rate`. The current public router
primarily exposes `/health`, `/api/v1/lookup/batch` and `/api/v1/claims`, so
Robert should confirm which documented routes are deployed and supported for
external clients.

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
external identifiers. Their official type values and URLs must be confirmed
before the write form is enabled.

## Questions for Robert

1. What is the production API base URL, and is it stable for all installations?
2. Which of the documented routes are currently deployed for third-party
   clients, especially `/lookup`, `/search` and `/persons/{personHash}`?
3. Is use of the public batch lookup by an open-source webtrees module
   explicitly permitted, and what rate limits, caching rules and attribution
   requirements apply?
4. How are service keys issued, rotated and scoped? Would a key be needed per
   webtrees installation, and may it be stored encrypted server-side?
5. Which response fields and provider type codes are authoritative for the
   `_EXID` representation in webtrees?
6. How are merged, withdrawn or corrected identities reported to clients, and
   is there an endpoint for invalidating or revising a claim?
7. Is a per-installation contributor pepper sufficient, or does GeMeDa issue a
   contributor identity itself?
8. Is a test endpoint or test dataset available, and who is the technical
   contact for integration questions?
