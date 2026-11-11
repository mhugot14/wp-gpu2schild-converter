<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

/**
 * Class Changelog
 *
 * Versionshistorie für die Backend-Seite „Changelog“ (templates/admin-changelog.php).
 *
 * Pflege: Bei jedem Release oben einen Eintrag ergänzen (neueste Version zuerst).
 * Solange noch nicht veröffentlicht, steht oben ein Eintrag mit 'version' => null;
 * beim Release die Versionsnummer und das Datum eintragen.
 * Schlüssel je Eintrag: version, datum (Y-m-d), zusammenfassung, neu, verbessert,
 * behoben, sicherheit, technik, hinweis – alle außer version optional.
 * Erlaubtes Markup: <code>, <strong>, <em>.
 *
 * Die Einträge bis 1.1.0 wurden im November 2026 aus der Git-Historie zusammengestellt.
 */
class Changelog {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function releases(): array {
		return [
			[
				'version'         => '1.1.0',
				'datum'           => '2026-11-04',
				'zusammenfassung' => 'Abgleich mit dem WebUntis-Stundenplan, Teilexport.',
				'neu'             => [
					'Reiter „WebUntis-Abgleich“: vergleicht das Ergebnis je Klasse und Fach mit der Lehrkraft, die laut WebUntis-Stundenplan die meisten Stunden gehalten hat. Befunde: Lehrkraft weicht ab, Fach fehlt in der GPU002, nur in der GPU002, Konflikt offen, Klasse fehlt.',
					'Datierter Datenstand mit „Aktualisieren“ – Klasse für Klasse mit Fortschrittsanzeige, einzelne Klassen lassen sich neu laden.',
					'Reiter „Konflikte“: Hinweis auf die Lehrkraft laut WebUntis mit „übernehmen“.',
					'Export wahlweise nur für ausgewählte Klassen (auch direkt aus dem Abgleich); gesperrt nur bei offenen Konflikten in diesen Klassen.',
				],
				'hinweis'         => 'Der WebUntis-Abgleich benötigt das Plugin WebUntis Analyser ab Version 0.7.0. Ohne es erscheint nur ein Hinweis.',
			],
			[
				'version'         => '1.0.0',
				'datum'           => '2026-10-23',
				'zusammenfassung' => 'Komplett neu aufgebaut: Converter im Frontend, Regelassistent, Datenschutz.',
				'neu'             => [
					'Bedienung im Frontend über den Shortcode <code>[mh_untis_schild]</code> mit den Reitern Import, Klassen, Fächer, Konflikte, Schild-Fächer und Ergebnis; Freigabe für ausgewählte Benutzer*innen.',
					'Regelassistent mit Bausteinen statt Syntax, Suche nach Kurzform und Bezeichnung.',
					'Regelprüfer: findet doppelte, wirkungslose und zusammenfassbare Regeln.',
					'Lehrkraft-Konflikte (Doppelbesetzung) werden angezeigt und per Auswahl entschieden.',
					'Excel-Export direkt als Download.',
					'Backend: Übersicht mit Status und Seite-anlegen-Knopf, „Freigaben & Datenschutz“.',
				],
				'verbessert'      => [
					'Neue Verarbeitung der GPU002: Kodierung und Trennzeichen werden erkannt, jeder Schritt (Halbjahr, Klassen- und Fachregeln, Konflikte, Dubletten, Schild-Abgleich) wird protokolliert.',
					'Bisherige Regeln werden übernommen, ihre Reihenfolge bleibt erhalten.',
				],
				'behoben'         => [
					'Lehrkraft ging beim Aufteilen eines Fachs verloren.',
					'Bei Doppelbesetzung wurde die Lehrkraft alphabetisch statt nach Stunden gewählt.',
					'Dubletten nach Umbenennung, ungenutzte Wochenstunden, Zeichenkodierung.',
				],
				'sicherheit'      => [
					'Die Vorversion legte die Exportdatei mit allen Lehrkraft-Kürzeln ungeschützt in <code>wp-admin/</code> ab – diese Dateien werden beim Update gelöscht, Exporte nicht mehr auf dem Server gespeichert.',
					'Löschfristen: höchstens drei Importe, täglicher Löschlauf nach einstellbarer Frist (Standard 365 Tage).',
					'Zugriffsprotokoll für Import, Export, Lehrkraft-Auswahl und Einstellungen – nur Anzahlen, keine Kürzel.',
					'Converter-Seite wird nicht zwischengespeichert und nicht indexiert; Textvorschlag für die Datenschutzerklärung.',
				],
				'technik'         => [
					'Alte Tabellen der Version 0.9 werden nach erfolgreicher Übernahme gelöscht; die alten Regeln bleiben vorher als Text gesichert.',
					'Beim Deinstallieren werden alle Tabellen und Einstellungen entfernt.',
				],
			],
			[
				'version' => '0.9.2',
				'datum'   => '2025-09-30',
				'verbessert' => [
					'Tabellen der Fächer und Klassen sortiert anzeigen.',
				],
				'behoben' => [
					'Verständliche Fehlermeldung, wenn beim Einlesen von Excel-Dateien die PHP-Erweiterung <code>zip</code> fehlt.',
					'Namenskonflikt zwischen eigenem Autoloader und Composer.',
				],
			],
			[
				'version' => '0.9.1',
				'datum'   => '2023-11-16',
				'behoben' => [
					'Fachlöschungen werden vor den Fachänderungen ausgeführt.',
				],
			],
			[
				'version'    => '0.9',
				'datum'      => '2023-11-15',
				'verbessert' => [
					'Export-Datenschema für den Import in SchILD-NRW überarbeitet.',
					'Beschreibung zum Hochladen der Schild-Fächerliste (Excel).',
				],
				'behoben'    => [
					'Pfadfehler beim Einlesen der Schild-Excel-Datei.',
				],
			],
			[
				'version'    => '0.2',
				'datum'      => '2023-11-04',
				'neu'        => [
					'Import der Schild-Fächer über Excel-Dateien.',
					'Vergleich von SchILD-NRW- und Untis-Fächern.',
					'Export zusätzlich als XLSX für den direkten Import in SchILD-NRW.',
					'Übersicht der aktuell vorhandenen Schild-Importe.',
				],
				'verbessert' => [
					'CSV-Export mit den Spalten, die Schild erwartet.',
					'Unterricht, der nur im 2. Halbjahr stattfindet, wird im 1. Halbjahr nicht mehr exportiert.',
				],
				'behoben'    => [
					'Zeichensatz der exportierten CSV, Pfadfehler auf Linux-Servern.',
				],
			],
			[
				'version' => '0.1',
				'datum'   => '2023-08-31',
				'neu'     => [
					'Mehrere Fächer in mehrere Fächer umwandeln.',
					'Fächer und Klassen von Hand ergänzen.',
					'Hochladen von CSV- und TXT-Dateien, Schuljahr wird automatisch vorbelegt.',
					'Datenbanktabellen werden bei der Aktivierung angelegt.',
				],
				'verbessert' => [
					'Durchgängiges Protokoll der Fachänderungen.',
				],
			],
			[
				'version'         => '0.0',
				'datum'           => '2023-04-26',
				'zusammenfassung' => 'Erste Fassung.',
				'neu'             => [
					'GPU002 aus Untis einlesen und als CSV für Schild bereitstellen (Download).',
					'Lösch-Klassen und Lösch-Fächer pflegen; Fächer ändern oder löschen.',
					'Trennzeichen der CSV wählbar (Semikolon, Komma, Tab).',
				],
			],
		];
	}
}
