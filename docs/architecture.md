# GeMeDa architecture

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
