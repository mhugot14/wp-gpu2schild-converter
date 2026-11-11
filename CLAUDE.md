# CLAUDE.md – Untis2Schild Converter

## Projekt

WordPress-Plugin, das die GPU002 (Unterricht) aus Untis in eine Schild-Importdatei für die
klassenweise Fachzuordnung umwandelt. Bereinigung über Klassen- und Fachregeln, Lehrkraft-
Konflikte, Abgleich mit der Schild-Fächerliste. Bedienung im Frontend über den Shortcode
`[mh_untis_schild]` für Administrator*innen und ausgewählte Benutzer.

Planung, Entscheidungen und Stand des Refactorings: `docs/refactoring-plan.md`.
Datenschutzkonzept (Daten, Fristen, Maßnahmen, offene Punkte der Schule): `docs/datenschutz.md`.
Keine Verbindung zu mh_form_workflows – beide Plugins bleiben unabhängig.

**WebUntis-Abgleich** (Reiter `webuntis`, ab 1.1.0): optional über die Filter des Plugins
webuntisAnalyser (`wa_webuntis_klassen`, `wa_unterricht_stunden`) – ohne das Plugin erscheint
nur ein Hinweis. Der Analyser liefert unübersetzte Stunden je Untis-Fach und Kürzel; der
Converter schickt sie in `Service\Webuntis_Abgleich` durch seine eigene Pipeline (alle Regeln,
ohne gespeicherte Entscheidungen) und vergleicht mit dem Ergebnis. Datenstand in
`usc_webuntis_stand` (eine Zeile je Untis-Klasse, Aktualisieren per AJAX `usc_wu_*` Klasse für
Klasse), Aufbewahrung wie die Importe. Der Reiter „Konflikte“ zeigt daraus Hinweise mit
„übernehmen“. Der Export kann auf ausgewählte Klassen beschränkt werden (`umfang=auswahl`,
`klassen[]`; gesperrt nur bei offenen Konflikten in diesen Klassen).

## Konventionen (wie mh_form_workflows)

- Namespace `Mh\UntisSchild\` → `src/` (PSR-4 via Composer), Hauptdatei ohne Namespace.
- Klassennamen `Snake_Case` mit großen Anfangsbuchstaben, Methoden/Variablen `snake_case`.
- `declare(strict_types=1);` in jeder Datei, PHP 8.1+.
- Controller orchestrieren, Repositories kapseln **alle** `$wpdb`-Zugriffe, Templates in `templates/`.
- Die Pipeline (`Service`) arbeitet ohne Datenbank und ohne WordPress, damit sie testbar bleibt.
- Kommentare und Nutzertexte auf Deutsch.
- Sicherheit: Nonce + Zugriffsprüfung in jedem POST-/Download-Handler, Ausgaben escapen,
  keine Exportdateien auf dem Server ablegen.
- Neue Aktionen auf Lehrkraftdaten mit `Protokoll_Repository::log()` protokollieren – im Detail
  nur technische Angaben (Anzahlen), nie Kürzel. Neue Tabellen in `uninstall.php` ergänzen,
  Schemaänderungen über `Activator::DB_VERSION`.

## Datenschutz — verbindlich

Die GPU002 und die Importtabellen enthalten Lehrerkürzel und Unterrichtsverteilung.

- Keine echten GPU002-/XLSX-Dateien öffnen (auch nicht in `uploads/`), keine Zeileninhalte aus
  `usc_import_zeilen`, `usc_lehrkraft_entscheidungen` oder Zwischenspeichern (Transients `mh_usc_erg_*`) ausgeben.
  Schema, Spaltennamen und `COUNT(*)` sind in Ordnung.
- Tests und Beispiele nur mit erfundenen oder pseudonymisierten Daten.
- Integrationstests, die schreiben, nie gegen die echten Tabellen: Michael testet parallel im
  Browser, Sichern/Zurückspielen würde seine Änderungen überschreiben. Stattdessen eine eigene
  Instanz `new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST )` mit eigenem Präfix
  (z. B. `wp_usctest_`) – kein `clone $wpdb`, der teilt die Verbindung und bricht ab.
- JavaScript der Reiter mit jsdom zur Laufzeit testen, nicht nur `node --check`. Globale
  JS-Objekte nie `window.usc` nennen: `<div id="usc">` belegt diesen Namen bereits.
- Wenn eine Fehlersuche echte Daten zu brauchen scheint: nachfragen.

## Changelog

Backend-Seite „Changelog“ (`Admin_Controller::render_changelog()`, Template
`templates/admin-changelog.php`), Einträge in `src/Model/Changelog.php`. Nutzerrelevante
Änderungen in einen obersten Eintrag mit `'version' => null` schreiben, beim Release Version
und Datum eintragen. Das Template ist in mh_form_workflows und webuntisAnalyser identisch.
