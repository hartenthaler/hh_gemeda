# GeMeDa architecture

This model alignment follows the [GeMeDa database model specification
(28 September 2026)](https://cloud.rpi.digital/index.php/s/RAT3RRqjPWqqGD4).

## Current read path

`GeMeDaModule::getTabContent()` reads the individual's GEDCOM text through
`Infrastructure\GeMeDaLinkReader`. The reader accepts top-level `EXID` and
`_EXID` blocks and associates a following `2 TYPE` value with the identifier.
Only the GeMeDa/source types currently named in the requirements are shown.
No network request is made by this path.

The view receives a small immutable `Domain\GeMeDaLink` value object. This keeps
presentation independent from the future API response model.

## API contract status

The current GeMeDa project documents a public batch read endpoint at
`POST /api/v1/lookup/batch` and a bearer-authenticated write endpoint at
`POST /api/v1/claims`. The batch request is limited to 100 sources. The claim
request requires at least two unique sources and a display name and confidence
value. The service creates the related records transactionally.

The production base URL, external-client permissions, rate limits and the
deployment status of the additional documented routes still require
confirmation. The module must therefore not hard-code a live URL or assume
that a service key is available.

## Alignment with the GeMeDa data model

The public data model separates three concerns:

- `person` is the cross-source GeMeDa identity. Its public stable identifier
  is `person_hash`; `display_name` is a presentation snapshot, not the
  identity key.
- `source` describes one provider record. The pair `(provider, external_id)`
  is unique. A source is either a `person_record` or a `container` and may
  carry an `external_url` and a label snapshot.
- `person_source` assigns a source to at most one GeMeDa person. Reusing a
  source must not create a second assignment.

Claims connect two sources. They currently use `same_person`; the confidence
values are `sehr_wahrscheinlich`, `wahrscheinlich` and `unwahrscheinlich`.
The service records claims and operations transactionally. A batch with no
existing GeMeDa person creates one person; a batch whose sources all belong
to one person reuses that person; sources belonging to different persons are
a conflict and must not be merged automatically.

`contained_in` is a planned relation between a person record and a container.
It is not a same-person assignment and must not be implemented by adding both
sources to one `person_source` group. Administrative correction, merge/split,
stable claim keys based on provider identifiers and source metadata history
remain later API concerns.

## Planned write path

After the remaining API contract questions are confirmed, introduce a real implementation of
`GeMeDaApiClientInterface`. It should be injected behind the module boundary,
send the service key only server-side, and expose an explicit command for claim
creation. The command must check authentication, the administrator allow-list,
CSRF and the required fields before sending a request.

On success, the returned GeMeDa person hash and provider identifiers are written
as separate `_EXID` blocks. Conflicts and API failures are shown as actionable
messages and must not leave partially written GEDCOM data.

## Administration

The scaffold stores the endpoint, service key, contributor pepper and user
allow-list as module preferences. Secrets are never rendered back into the
form. Before production use, confirm whether webtrees site preferences satisfy
the service's security requirements or whether a dedicated encrypted storage
mechanism is needed.

## Performance and failure handling

The tab is intentionally local-only in phase 1. Future remote reads should use
timeouts, bounded response sizes, a cache with an explicit version key and a
graceful read-only fallback. Batch operations remain out of scope until their
authorization and load characteristics are documented.
