# Change Log

## Next release

- Created the initial GeMeDa webtrees module scaffold.
- Documented the current GeMeDa test environment, including its API endpoint,
  local-only claim writes, shared Bearer key and open production security and
  rate-limit decisions.
- Documented the GeMeDa service data model and the confirmed provider URL
  templates; unresolved provider homepages remain explicitly unregistered as
  EXID authorities.
- Added a read-only individual tab for local GeMeDa-related `_EXID` links.
- Added an administration page and safe API abstraction for the later
  write-enabled phase.
- Added an administration setting to choose `EXID` or `_EXID` for future new
  GeMeDa identifiers; both spellings remain readable.
- Added a version-compatible person search using the configured GeMeDa API and
  a controlled action for editors to store a selected person hash as `EXID` or
  `_EXID` with `2 TYPE gemeda`.
- Updated the person search to the currently deployed `GET /api/v1/lookup/search`
  endpoint, with editable search fields for given name, surname and place.
- Replaced the module's duplicate HTTP compatibility layer with the shared
  `hh_shared` transport for webtrees 2.2 and 2.3.
- Added the read-only GeMeDa provider search: the provider catalogue is loaded
  dynamically, administrators control the enabled provider allow-list, and
  users select providers for each search. Results are grouped by provider and
  individual provider errors are isolated.
