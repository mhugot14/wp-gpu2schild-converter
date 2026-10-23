# Datenschutzkonzept – Untis2Schild Converter (ab Version 1.0.0)

Stand: 2026-10-23. Punkte, die die Schule entscheiden muss, sind mit **[festzulegen]** markiert.

## 1. Zweck

Übertragung der klassenweisen Unterrichtsverteilung aus Untis nach SchILD-NRW, damit alle
Schülerinnen und Schüler einer Klasse ihre Fächer und Fachlehrkräfte erhalten. Keine weitere
Auswertung (keine Statistik über Lehrkräfte, kein Abgleich mit anderen Systemen).

## 2. Verarbeitete Daten

| Daten | Betroffene | Herkunft | Gespeichert in |
|---|---|---|---|
| Lehrkraft-Kürzel, Klasse, Fach, Wochenstunden (Klasse/Lehrkraft), Unterrichtsnummer, Gruppe | Lehrkräfte (Beschäftigtendaten) | GPU002 aus Untis | `usc_import_zeilen` |
| Gemerkte Auswahl „Klasse + Fach → Lehrkraft“ | Lehrkräfte | Eingabe im Reiter „Konflikte“ | `usc_lehrkraft_entscheidungen` |
| WordPress-Benutzer-ID der handelnden Person, Aktion, Zeit | Nutzer*innen des Converters | automatisch | `usc_protokoll`, `erstellt_von`/`geaendert_von` |
| Klassen- und Fachregeln, Schild-Fächerliste | – (keine Personendaten) | Eingabe / Schild-Export | `usc_*_regeln`, `usc_schildfaecher` |

Nicht verarbeitet: Schülerdaten, Namen von Lehrkräften, Freitextspalten der GPU002 (werden beim
Einlesen verworfen).

**Rechtsgrundlage:** [festzulegen] – voraussichtlich Erfüllung der schulischen Aufgabe
(Art. 6 Abs. 1 lit. e DSGVO i. V. m. dem Schulgesetz NRW) bzw. Beschäftigtendatenverarbeitung
nach § 18 DSG NRW. Eintrag im Verzeichnis von Verarbeitungstätigkeiten: [festzulegen].

## 3. Technische und organisatorische Maßnahmen

**Zugriff**
- Nur Administrator*innen und unter „Untis2Schild → Freigaben & Datenschutz“ ausdrücklich
  freigeschaltete Personen. Alle anderen – auch angemeldete – sehen nur einen Hinweis.
- Jede Aktion läuft per POST mit Nonce und Berechtigungsprüfung; nicht angemeldete Anfragen
  werden gar nicht angenommen (kein `admin_post_nopriv`).
- Freigaben regelmäßig prüfen, z. B. beim Ausscheiden aus der Verwaltung. Zuständig: [festzulegen].

**Seite mit dem Converter**
- Wird nicht zwischengespeichert (no-store, `DONOTCACHEPAGE` für Cache-Plugins) – sonst könnte
  die Ansicht einer berechtigten Person an andere ausgeliefert werden.
- Für Suchmaschinen gesperrt (noindex). Die Seite nicht auf „privat“ stellen: Dann sehen
  Freigegebene mit einfacher Rolle sie nicht.

**Dateien**
- Hochgeladene Dateien (GPU002, Schild-Export) werden nur aus dem PHP-Temp-Verzeichnis gelesen
  und nicht gespeichert.
- Die Schild-Importdatei wird beim Herunterladen erzeugt, nicht auf dem Server abgelegt und
  ohne Zwischenspeicherung ausgeliefert.
- Ordner `tools/`, `tests/`, `docs/` sind per `.htaccess` für Webzugriffe gesperrt.

**Speicherbegrenzung**
- Höchstens 3 Importe; jeder wird nach einer einstellbaren Frist gelöscht (Standard 365 Tage,
  täglicher Lauf). Frist: [festzulegen, Vorschlag 365 Tage].
- Zugriffsprotokoll: 365 Tage.
- Zwischengespeicherte Ergebnisse (enthalten Kürzel) werden bei jeder Änderung sofort gelöscht,
  spätestens nach einer Stunde.
- Gemerkte Lehrkraft-Auswahlen bleiben, bis sie im Reiter „Konflikte“ entfernt werden – sie
  werden für künftige Importe gebraucht. Nicht mehr verwendete Auswahlen sind dort markiert.
- Beim Löschen des Plugins (nicht beim Deaktivieren) entfernt `uninstall.php` alle Tabellen,
  Einstellungen und Zwischenspeicher.

**Rechenschaft**
- Zugriffsprotokoll für Import, Export, Lehrkraft-Auswahl, Schild-Fächerliste und Einstellungen;
  nur technische Angaben (Anzahlen), keine Kürzel oder Inhalte.
- Textvorschlag für die Datenschutzerklärung unter Einstellungen → Datenschutz.

## 4. Altlasten der Vorversion 0.9.x

Die Vorversion schrieb bei jedem Aufruf ihrer Upload-Seite `schildimport.csv` und
`schildimport.xlsx` mit der kompletten Unterrichtsverteilung (inkl. Kürzel) **ungeschützt nach
`wp-admin/`** – abrufbar ohne Anmeldung. Außerdem lag die Verteilung in `usc_schildimport`.

Beim Update auf 1.0.0 (DB-Stand 2) werden automatisch entfernt: die Tabellen
`usc_loesch_klassen`, `usc_loesch_faecher`, `usc_schildimport` und die genannten Dateien. Die
alten Regeln werden vorher als Text gesichert (Option `mh_usc_altregeln_sicherung`, sichtbar
unter „Freigaben & Datenschutz“). Konnte eine Datei nicht gelöscht werden, zeigt die
Übersichtsseite einen Fehler.

Organisatorisch zu prüfen **[festzulegen]**:
- Liegen die Dateien auf dem Live-System noch in `wp-admin/`? (Sofort löschen, nicht erst beim Update.)
- Wurden sie abgerufen? Zugriffslogs des Webservers auf `/wp-admin/schildimport.*` prüfen und
  mit der/dem Datenschutzbeauftragten klären, ob eine Meldung nach Art. 33 DSGVO nötig ist.
- Datenbank- und Datei-Backups enthalten die Altdaten weiterhin – Aufbewahrung der Backups prüfen.

## 5. Entwicklung

Echte GPU002-Dateien und Tabelleninhalte werden bei der Entwicklung nicht geöffnet; geprüft wird
mit erfundenen oder pseudonymisierten Daten (`tools/gpu_pruefen.php --anonym`). Siehe `CLAUDE.md`.
