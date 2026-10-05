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

The current test base URL is `https://api.gemeda.rpi.digital`. It is available
for local testing; there is no production environment and external claim
creation is not generally authorised. Bearer authentication is current and
one shared key is used. No rate limits or caching policy are defined yet, so
the module must implement conservative limits itself. The uncertain `GET`
routes must not be a hard dependency.

## Person search boundary

The individual tab delegates person search to
`Infrastructure\\HttpGeMeDaApiClient`. The client uses the shared
`Hartenthaler\\Webtrees\\Shared\\Http\\HttpTransport`, which selects the
webtrees 2.3 PSR-18 service or the webtrees 2.2-compatible fallback.
`GeMeDaSearchCriteria` and `GeMeDaSearchResult` keep the user interface
independent of evolving API field names. The current endpoint is
`GET /api/v1/lookup/search?q=...`; changing the route or adapting the final
response schema is confined to this adapter.

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

`contained_in` is now a required relation between a person record and a
container for cases such as a grave marker that refers to more than one
person. It is not a same-person assignment and must not be implemented by
adding both sources to one `person_source` group. The API and UI contract for
this relation is still being finalised. Administrative correction,
merge/split, stable claim keys based on provider identifiers and source
metadata history remain later API concerns.

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
form. The current test setup uses one shared service key. Before production,
decide whether each installation receives a key or whether another
authorisation model applies, and use secure or encrypted storage. There is no
service admin portal for correction, deletion, withdrawal or merging yet.

## Performance and failure handling

The tab is intentionally local-only in phase 1. Future remote reads should use
timeouts, bounded response sizes, a cache with an explicit version key and a
graceful read-only fallback. Because the service has not defined rate limits,
the module must be conservative by default. Batch operations remain out of
scope until their authorization and load characteristics are documented.
## Provider search

The module does not maintain a permanent copy of the GeMeDa provider list.
When the administrator page or a provider search is opened, the HTTP client
requests `GET /api/v1/providers`. Each returned provider contains its GeMeDa
identifier, display name and numeric `metaSearchId`. The administrator stores
only the IDs explicitly enabled for this installation; newly published
providers therefore remain disabled by default.

For a search, the user selects a subset of the enabled providers. The module
queries `https://meta.genealogy.net/proxy` once per selected `metaSearchId`
using `lastname`, `placename` and `db`, and renders the XML responses as
read-only, provider-grouped results. A failed provider produces a status for
that provider only. If the catalogue endpoint is not deployed yet, the module
shows a warning and does not use a stale hard-coded provider catalogue.
