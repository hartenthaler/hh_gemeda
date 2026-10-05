# GeMeDa-Provider und externe URLs

Diese Übersicht beschreibt den Providerkatalog aus Sicht des webtrees-Moduls
und des Zentrums der Projekte. Maßgeblich ist der aktuelle Code des Portals:

- der öffentliche Katalog `GET /api/v1/providers`;
- `backend/gemeda/config/providers.json`;
- die Meta-Suchpartner in `src/data-access/metaSearch/partners.ts`;
- die Quelladapter unter `src/data-access/gemeda/sources/`.

Das Modul `hh_gemeda` übernimmt den Katalog dynamisch. Die Tabelle ist daher
eine Dokumentation des aktuellen Quellcodes, keine statische Providerliste für
die Laufzeit.

## Aktueller Providerkatalog

| GeMeDa-ID | Bezeichnung | `metaSearchId` | Ableitung der `externalId` im Portal | Belastbare Datensatz-URL im geprüften Code |
| --- | --- | ---: | --- | --- |
| `gedbas` | GEDBAS | 1 | numerische ID aus `/person/show/{ID}` | `https://gedbas.genealogy.net/person/show/{ID}` |
| `adressbuecher` | Historische Adressbücher | 2 | UUID aus `/entry/{UUID}` | `https://adressbuecher.genealogy.net/entry/{UUID}` |
| `ofb` | Online Ortsfamilienbücher | 3 | `{ofb}::{ID}` aus den URL-Parametern `ofb` und `ID` | `http://www.online-ofb.de/famreport.php?ofb={ofb}&ID={ID}`; die Anzeige-URL kann auf `/{ofb}/` umgeschrieben werden |
| `grabsteine` | Grabsteine | 5 | Person: `tomb:{cem}:{tomb}:person:{hash}`; Container: `tomb:{cem}:{tomb}` | keine allgemeine EXID-Schablone; Identität und Container werden aus den Parametern `cem` und `tomb` abgeleitet |
| `verlustlisten` | Deutsche Verlustlisten 1. Weltkrieg | 6 | numerische ID aus `/search/show/{ID}` | `https://verlustlisten.genealogy.net/search/show/{ID}` |
| `grabsteine_ostfriesland` | Grabsteine Ostfriesland | 7 | numerische ID aus `/grabstein/{ID}` | URL-Muster im Adapter: `/grabstein/{ID}`; Host ist im Adapter nicht festgelegt |
| `sterbebilder` | Sterbebilder | 8 | Wert des URL-Parameters `id` | keine allgemeine EXID-Schablone im Adapter; die Identität wird aus `?id={ID}` gelesen |
| `auswanderer_oldenburg` | Auswanderer aus dem Großherzogtum Oldenburg | 10 | `{tree}::{personID}` aus `tree` und `personID` | keine allgemeine EXID-Schablone im Adapter; URL-Parameter `?tree={tree}&personID={ID}` |
| `des` | Daten-Eingabe-System DES | 11 | numerische ID aus `/search/show/{ID}` | `https://des.genealogy.net/search/show/{ID}` |
| `zufallsfunde` | Zufallsfunde | 13 | im aktuellen Quelladapter nicht abgeleitet | noch keine belastbare ID- oder URL-Regel im geprüften Code |
| `grabsteine_blf` | Grabsteine BLF Bayern | 14 | Wert des URL-Parameters `id` | keine allgemeine EXID-Schablone im Adapter; die Identität wird aus `?id={ID}` gelesen |
| `greifx` | Pommerscher Greif | 15 | Wert des URL-Parameters `id` | keine allgemeine EXID-Schablone im Adapter; die Identität wird aus `?id={ID}` gelesen |
| `allensteiner` | Allensteiner Indexierungsprojekt | 16 | im aktuellen Quelladapter nicht abgeleitet | noch keine belastbare ID- oder URL-Regel im geprüften Code |

Die Meta-Suche fasst die vielen `<database>`-Blöcke der Provider `ofb` und
`grabsteine` im Portal zu jeweils einem Ergebnisblock zusammen. Das ist eine
Darstellungsregel und keine zusätzliche Providerkennung.

## Konsequenzen für `hh_exid`

Eine GeMeDa-Providerkennung ist nicht automatisch eine EXID-`TYPE`-URI. Eine
EXID benötigt eine stabile, dokumentierte Ziel-URL, an die die ID nach den
Regeln des GEDCOM-Katalogs angehängt werden kann. Deshalb gilt:

1. URLs mit einem eindeutig belegten festen Pfad können als eigene Autorität
   registriert werden.
2. Zusammengesetzte IDs wie `{ofb}::{ID}` oder Grabstein-Person-Hashes
   dürfen nicht in eine erfundene einfache URI-Schablone gepresst werden.
3. Bei Query-Parameter-IDs muss zunächst festgelegt werden, ob und wie die
   vollständige URL dauerhaft als EXID abgebildet werden soll.
4. Provider ohne Adapterregel (`zufallsfunde`, `allensteiner`) bleiben in
   `hh_exid` ohne eigene URI-Definition, bis der Portalcode eine stabile
   Identitätsregel bereitstellt.

Die bekannten, bereits unabhängig von GeMeDa registrierten Autoritäten
(`GenWiki`, `GOV`, `Wikidata`, `GeoNames` usw.) bleiben davon unberührt. Der
GeMeDa-Katalog ist eine Quelle für Provider und Suchabfragen, nicht automatisch
eine zweite konkurrierende EXID-Registry.
