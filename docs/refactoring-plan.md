# Refactoring-Plan Untis2Schild Converter (v0.9.2 → v1.0.0)

Stand: 2026-10-18

## Ziel

Die GPU002 aus Untis wird nachvollziehbar und fehlerarm in eine Schild-Importdatei
(klassenweise Fachzuordnung) umgewandelt. Bedienung im **Frontend** für einen
eingeschränkten Personenkreis, Gestaltung und Architektur wie **mh_form_workflows**.

## Entscheidungen (mit Michael abgestimmt, 2026-10-18)

| Thema | Entscheidung |
|---|---|
| Fach mit mehreren Lehrkräften in einer Klasse | Lehrkraft mit den meisten Wochenstunden wird vorgeschlagen und angewendet; bei Gleichstand offener Konflikt. Entscheidungen werden gespeichert und bei späteren Importen wiederverwendet. |
| Exportformat | Bisherige 10 Spalten unverändert (Kursbezeichnung, Klasse, Jahr, Halbjahr, Jahrgang, Fach, Kursart, Wochenstunden, Wochenstunden Lehrer, Lehrer) als XLSX; neu: Wochenstunden befüllt. |
| Halbjahreslogik | Bleibt: Im 1. HJ werden reine „2. HJ“-Fächer weggelassen, im 2. HJ bleibt alles. |
| Zugriff | Administrator*innen + im Backend ausgewählte Benutzer; zusätzlich Kachel im `[mh_dashboard]` von mh_form_workflows (benötigt dort einen kleinen Filter-Hook). |

## Befunde im Altcode (werden mit dem Refactoring behoben)

1. Fachaufteilung übernimmt nur die Lehrkraft der ersten Zeile; weitere Lehrkräfte gehen verloren (`CSVHandler::faecher_loeschen`).
2. Dubletten werden **vor** den Regeln entfernt; durch Umbenennung entstehende Dubletten bleiben.
3. Löschen nutzt `LIKE`, Umbenennen `=` – Muster wirken uneinheitlich.
4. Kodierung: ANSI-Import nicht umgewandelt, Export-Filter verdreht.
5. Wochenstunden werden verworfen.
6. `fach varchar(10)` schneidet ab.
7. Exportdateien landen im Arbeitsverzeichnis (`wp-admin/`) – öffentlich abrufbar, mit Lehrerkürzeln.
8. Keine Nonces, kein Escaping, Klassenregel per String in SQL (Injection).
9. Hochgeladene Excel-Dateien bleiben im Plugin-Ordner liegen.
10. Jeder Upload verändert sofort den Datenbestand, kein Protokoll, keine Vorschau.
11. Regeln nur als Textblock pflegbar; „neu“ löscht ohne Rückfrage.
12. `CSVHandlerSpreadsheet.php` bindet einen nicht existierenden Pfad ein.
13. Aktivierung plant einen Cron ohne Handler, der nie abgemeldet wird.

## Architektur

```
untisSchildConverter.php      Bootstrap ohne Namespace, Konstanten MH_USC_*
composer.json                 PSR-4  Mh\UntisSchild\ → src/, phpoffice/phpspreadsheet
src/
  Setup/       Activator (dbDelta, DB_VERSION, Migration Altregeln), Plugin_Bootstrap (manuelles Wiring)
  Controller/  Converter_Controller (Shortcode, POST-Handler, Download), Settings_Controller (Backend)
  Model/       Gpu_Row, Schild_Row, Klassen_Regel, Fach_Regel, Pipeline_Result, Protokoll_Eintrag
  Service/     Gpu_Parser, Klassen_Muster, Pipeline (+ Schritte), Schild_Exporter, Access
  Repository/  Import_Repository, Klassen_Regel_Repository, Fach_Regel_Repository,
               Lehrkraft_Entscheidung_Repository, Schild_Fach_Repository
templates/     partial-style.php, partial-tabs.php, import.php, klassen.php, faecher.php,
               konflikte.php, schildfaecher.php, ergebnis.php, admin-settings.php
tests/         Pipeline-Tests ohne WordPress, erfundene GPU002 (tools/generate_testdata.php)
```

### Kernidee: Pipeline ohne Datenbankzugriff

Die GPU-Rohzeilen eines Importlaufs bleiben **unverändert** gespeichert. Das Ergebnis wird
bei jedem Aufruf aus Rohzeilen + aktuellen Regeln berechnet (deterministisch, im Speicher).
Jeder Schritt liefert ein Protokoll (Zeile vorher → nachher, auslösende Regel):

1. **Halbjahr** – „Gruppe“-Spalte tolerant auswerten (`1. HJ`, `1.HJ`, `1 HJ`), Logik wie bisher.
2. **Klassenregeln** – Muster → Zielklasse oder löschen.
3. **Fachregeln** – Untis-Fach/-Fächer → kein Fach (löschen), ein Fach (umbenennen) oder
   mehrere (aufteilen). Pro Klasse und Zielfach entsteht je **beteiligter Lehrkraft** eine Zeile.
   Wochenstunden bei Aufteilung: leer (Schild-Wert bleibt maßgeblich).
4. **Lehrkraft-Konflikte** – Klasse+Fach mit mehreren Lehrkräften: gespeicherte Entscheidung →
   sonst meiste Wochenstunden → bei Gleichstand offen (Export gesperrt).
5. **Dubletten** – erst jetzt; Wochenstunden je eindeutiger Unterrichtsnummer summiert.
6. **Schild-Abgleich** – Fächer, die nicht in der Schild-Fächerliste stehen (Export-Warnung).

### Muster für Klassen

Einheitlich für alle Regeln, ohne Groß/Klein-Unterscheidung: exakter Name (`AK21A`),
Platzhalter `*` oder `%` (`AK*`), Liste mit Komma (`AK21A, AK21B`). Die Oberfläche zeigt live,
welche Klassen des aktuellen Imports getroffen werden.

### Datenbank (Präfix `usc_`, DB_VERSION)

| Tabelle | Inhalt |
|---|---|
| `usc_importe` | Lauf: Schuljahr, Halbjahr, Dateiname, Zeilen, erstellt_von, erstellt_am |
| `usc_import_zeilen` | Rohzeilen: U-Nr, Klasse, Lehrer, Fach, Wochenstunden, Gruppe |
| `usc_klassen_regeln` | Muster, Zielklasse (leer = löschen), Bemerkung, aktiv, geändert_von/am |
| `usc_fach_regeln` | Untis-Fächer, Schild-Fächer, Klassenmuster, Bemerkung, aktiv, geändert_von/am |
| `usc_lehrkraft_entscheidungen` | Klasse, Fach, Lehrkraft, entschieden_von/am |
| `usc_schildfaecher` | interne Kurzform, ASD-Kürzel, Bezeichnung (bleibt) |

Altregeln aus `usc_loesch_klassen` / `usc_loesch_faecher` werden bei der Migration übernommen;
die Alttabellen bleiben bis zur Bestätigung bestehen und werden erst später entfernt.

### Frontend `[mh_untis_schild]`

Reiter: **Import · Klassen · Fächer · Konflikte · Schild-Fächer · Ergebnis**.
Jede Regel einzeln anlegen/bearbeiten/löschen/deaktivieren, Massen-Einfügen per Textblock
bleibt (mit Vorschau vor dem Speichern), Löschen aller Regeln nur mit Bestätigung.
Export als Download über `admin-post` mit Nonce – keine Datei auf dem Server.
Sicherheit: Nonce + Zugriffsprüfung in jedem Handler, Escaping in allen Templates,
Uploads nur aus dem PHP-Temp-Verzeichnis lesen.

### Backend

Nur noch eine Einstellungsseite: berechtigte Benutzer, Seite mit dem Shortcode (für die
Dashboard-Kachel und Links), Hinweis auf fehlende PHP-Erweiterung `zip`.

## Stand (2026-10-23)

**Phase 1 + 2 erledigt.**

- Bootstrap, Composer/PSR-4, Activator mit 5 neuen Tabellen; alte Backend-Seiten laufen
  übergangsweise weiter (`Plugin_Bootstrap::load_legacy_admin()`).
- Migration: 25 Klassenregeln, 92 von 93 Fachregeln (die fehlende hatte kein Untis-Fach und
  löschte nur Zeilen ohne Fach – das macht jetzt der Parser). Position = alte Abarbeitungs-
  reihenfolge `ORDER BY fach_schild` (Löschungen zuerst); bei Überschneidungen gilt die erste.
- Pipeline mit 34 Tests (`php tests/run.php`), darunter Regressionstests für die Altfehler.
- Probelauf mit pseudonymisierter GPU (`tools/pipeline_probe.php`), HJ 1: 1.528 → 835 Zeilen,
  54 Lehrkraft-Konflikte (16 automatisch, 38 Gleichstand), 37 Zeilen passen auf mehrere
  Fachregeln, 46 Fachregeln ohne Treffer, ~0,35 s.

Erkenntnisse für die nächsten Phasen:

- Die Schild-Fächerliste in der Entwicklungs-DB war eine fremde Liste (Emmerich, gesichert in
  `wp_usc_schildfaecher_backup_emmerich`). Am 2026-10-23 einmalig per SQL durch die 151 aktiven
  Fächer aus `wa_subjects` (webuntisAnalyser) ersetzt – reine Datenkopie, keine Code-Abhängigkeit.
  ASD-Kürzel sind dort nicht vorhanden. Achtung: wa_subjects kann auch Kürzel enthalten, die der
  webuntisAnalyser aus der Stundentafel selbst angelegt hat; der Abgleich ist damit optimistischer
  als mit einem echten Schild-Export.
- Backend-Startseite (`Admin_Controller`, `templates/admin-start.php`) mit Erklärung, Status,
  Shortcode-Einbindung und „Seite anlegen“. Shortcode-Konstante: `Plugin_Bootstrap::SHORTCODE`.
- 38 Gleichstände beim ersten Lauf → Konfliktansicht braucht schnelle Mehrfachauswahl.
- Überschneidende und trefferlose Regeln in der Regelverwaltung sichtbar machen.
- Pipeline-Ergebnis je Import + Regelstand cachen (Transient), nicht bei jedem Seitenaufruf rechnen.

**Phase 3–5 + Freigaben (Teil von 6) erledigt (2026-10-23)** – zur Prüfung durch Michael.

- Shortcode `[mh_untis_schild]` (`Converter_Controller`, `templates/frontend/`): Reiter Import,
  Klassen, Fächer, Konflikte, Schild-Fächer, Ergebnis. Alle Aktionen per POST über
  admin-post mit Nonce + `Zugriff::darf()`; kein `nopriv`.
- Ergebnis wird je Import + Stand (`mh_usc_stand`) als komprimierter Transient gecacht.
- Export: XLSX direkt in die Ausgabe (`Excel_Service::sende_xlsx`), gesperrt bei offenen Konflikten.
- Backend: Untis2Schild → Freigaben (Option `mh_usc_settings[user_ids]`).
- Integrationstest (Scratchpad, pseudonymisierte Daten): alle Reiter ohne Warnungen, Aktionen
  inkl. Fehlerfällen und falscher Nonce, Export 835 Zeilen im alten Format.
- Größe: Reiter „Ergebnis“ ~0,9 MB HTML, „Fächer“ ~0,35 MB – bei Bedarf Protokoll nachladen.

**Ergänzt (2026-10-23):** Regel-Assistent mit Bausteinen für Fach- und Klassenregeln
(`partial-bausteine-js.php`, Experten-Eingabe bleibt), Vorbelegung aus „Fehlt in Schild“,
Regelprüfer (`Service\Regel_Pruefer`: doppelt, wirkungslos, zusammenfassbar, Schild-Fach
unbekannt, ohne Treffer) mit Zusammenfassen nach Nachrechnen. Auf den echten Regeln: Fach-
regeln 92 → 36, Klassenregeln 25 → 1 bei unverändertem Ergebnis (Testtabellen).

Offen: Dashboard-Kachel in mh_form_workflows (Filter-Hook nötig), Phase 7 (Altcode/Alttabellen
entfernen nach Freigabe, uninstall.php, Version 1.0.0), Sichtprüfung im Browser.

## Umsetzungsphasen

1. **Grundlage** – Composer/PSR-4, Bootstrap, Activator inkl. Migration, CLAUDE.md, Altcode entfernen.
2. **Pipeline + Tests** – Parser, Muster, alle Schritte; Tests mit erfundener GPU002.
3. **Repositories + Import** – Tabellen, Importlauf speichern, Schild-Fächer-Import.
4. **Frontend** – Controller, Reiter, Regelverwaltung, Ergebnisprotokoll.
5. **Konflikte + Export** – Konfliktansicht, Entscheidungen, XLSX-Download.
6. **Backend + Dashboard** – Einstellungen, Kachel in mh_form_workflows (Filter-Hook).
7. **Aufräumen** – uninstall.php, Alttabellen entfernen (nach Bestätigung), Version 1.0.0.

## GPU002-Layout (geprüft 2026-10-23 mit `tools/gpu_pruefen.php`, echte Datei 2023-09-18)

- ANSI (Windows-1252), Komma, Anführungszeichen, keine Kopfzeile, 47 Spalten, ~1.500 Zeilen.
- Spalten (1-basiert): 1 U-Nr, 2 Wochenstunden, 3 Wochenstd. Klasse, 4 Wochenstd. Lehrer,
  5 Klasse, 6 Lehrer, 7 Fach, 12 Gruppe. Datum von/bis (15/16) ist für alle Zeilen gleich.
- Gruppe enthält neben `1. HJ` / `2. HJ` weitere Werte (`WU`, `WG`, `GBU`, `GBO`, `GBM`) →
  ganzjährig behandeln, beim Import anzeigen.
- 11 % der Klasse+Fach-Kombinationen haben mehrere Lehrkräfte (vor Halbjahresfilter).
- 41 Kopplungen (eine U-Nr über mehrere Klassen) → Stunden je Klasse aus Spalte 3.
- Einzelne Zeilen ohne Klasse/Fach → überspringen + protokollieren.
- Platzhalter-Lehrkräfte mit Sonderzeichen möglich → Warnung.

Export: „Wochenstunden“ = Spalte 3, „Wochenstunden Lehrer“ = Spalte 4. Konfliktvorschlag
nach Spalte 4. Testdaten: erfundene Datei + pseudonymisierte Kopie (nur U-Nr, Wstd, Klasse,
Lehrer-Pseudonym, Fach, Gruppe – liegt bei Michael, nicht im Repo).
