# Provider-URIs aus dem Genealogienetz-Portal

Die Portal-Anbindung unter `src/app` und den zugehörigen
`src/data-access`-Dateien wurde am 29. September 2026 auf stabile
Identitäts-URLs geprüft. Nur ein URL-Muster, bei dem der Provider die
Kennung unmittelbar hinter einem festen Pfad erwartet, darf in den
`hh_exid`-Katalog übernommen werden.

## Bestätigte ID-URL-Muster

| Provider | URI mit angehängter Kennung | Nachweis im Portal |
| --- | --- | --- |
| GEDBAS | `https://gedbas.genealogy.net/person/show/{ID}` | `src/data-access/gedbasSearch/result.mapper.ts`, `gedbasSearch.api.ts` |
| DePeVe | `https://depeve.de/person/{ID}` | `src/data-access/depeveSearch.ts` |
| GenWiki | `https://wiki.genealogy.net/?curid={ID}` | bereits im `hh_exid`-Katalog; Portal nutzt zusätzlich die MediaWiki-API |
| GOV | `https://gov.genealogy.net/item/show/{ID}` | im `hh_external_places`-Provider; nicht neu in `hh_exid` aufnehmen, wenn die GEDCOM-Registry-URI bereits maßgeblich ist |

GEDBAS und DePeVe sind als zusätzliche Autoritäten im gebündelten
`hh_exid`-Katalog registriert. Bei DePeVe ist das Kennungsformat im Portal
nicht weiter eingeschränkt; der Katalog lässt deshalb jeden sicheren,
URL-tauglichen Wert zu. GEDBAS verwendet die im Portal ermittelte numerische
Personenkennung.

## Noch nicht als EXID-URI registriert

Die im Portal sichtbaren Einstiegs-URIs sind:

| Angebot | Portal-URI | Verwendungsstatus |
| --- | --- | --- |
| Historische Adressbücher | `https://adressbuecher.genealogy.net/` | Startseite, kein ID-Muster im geprüften Code |
| DES | `https://des.genealogy.net/` | Startseite, kein ID-Muster im geprüften Code |
| Familienanzeigen | `http://familienanzeigen.genealogy.net/` | Startseite, kein ID-Muster im geprüften Code |
| Grabsteine | `https://grabsteine.genealogy.net/` | Startseite, kein ID-Muster im geprüften Code |
| Ortsfamilienbücher | `https://ofb.genealogy.net/` | Startseite/Suche, zusammengesetzte OFB-IDs sind kein einfacher URL-Pfad |
| GOV | `https://gov.genealogy.net/` | GOV-ID-Link ist bereits provider-spezifisch in `hh_external_places` dokumentiert |
| GenWiki | `https://wiki.genealogy.net/` | Seitenkennnummer ist bereits im `hh_exid`-Katalog dokumentiert |

Das Portal verlinkt außerdem auf Deutsche Verlustlisten, Sterbebilder,
Auswanderer aus Oldenburg, den Pommerschen Greif und das Allensteiner
Indexierungsprojekt. Für diese Angebote liefert der geprüfte
`src/app`-/`src/data-access`-Stand jedoch kein allgemeines stabiles Muster
`feste URI + externe ID`. Eine Startseite ist daher nicht automatisch eine
gültige EXID-Ziel-URI. Diese Provider benötigen zunächst eine bestätigte
Datensatz-URL und ein dokumentiertes Kennungsformat.

Die GeMeDa-Kennung (`person_hash`) ist davon zu unterscheiden. Der aktuelle
API- und Portalstand dokumentiert keine öffentliche URL-Schablone für einen
GeMeDa-Personenhash; deshalb wird dafür noch keine EXID-URI erfunden.
