<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Setup;

use wpdb;

/**
 * Class Activator
 *
 * Legt die Tabellen an (dbDelta) und übernimmt einmalig die Regeln aus den Alttabellen
 * usc_loesch_klassen / usc_loesch_faecher. Die Alttabellen bleiben bestehen, bis das neue
 * Frontend sie abgelöst hat.
 */
class Activator {

	public const DB_VERSION         = '1';
	public const OPTION_DB_VERSION  = 'mh_usc_db_version';
	public const OPTION_MIGRATION   = 'mh_usc_migration';

	/** Cron-Hook der Altversion – wurde geplant, aber nie verarbeitet. */
	private const LEGACY_CRON_HOOK = 'untisSchildConverter/weekly_cron';

	public static function activate(): void {
		global $wpdb;

		self::create_tables( $wpdb );
		self::migrate_legacy_rules( $wpdb );
		wp_clear_scheduled_hook( self::LEGACY_CRON_HOOK );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::LEGACY_CRON_HOOK );
	}

	private static function create_tables( wpdb $wpdb ): void {
		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Ein Importlauf = eine hochgeladene GPU002.
		dbDelta( "CREATE TABLE {$p}usc_importe (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			schuljahr smallint(5) unsigned NOT NULL,
			halbjahr tinyint(1) unsigned NOT NULL,
			dateiname varchar(255) NOT NULL DEFAULT '',
			kodierung varchar(30) NOT NULL DEFAULT '',
			trennzeichen varchar(10) NOT NULL DEFAULT '',
			zeilen int(10) unsigned NOT NULL DEFAULT 0,
			uebersprungen int(10) unsigned NOT NULL DEFAULT 0,
			erstellt_von bigint(20) unsigned NOT NULL DEFAULT 0,
			erstellt_am datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset;" );

		// Rohzeilen der GPU002 – werden nie verändert, das Ergebnis wird daraus berechnet.
		dbDelta( "CREATE TABLE {$p}usc_import_zeilen (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_id bigint(20) unsigned NOT NULL,
			zeile_nr int(10) unsigned NOT NULL,
			unr varchar(20) NOT NULL DEFAULT '',
			klasse varchar(40) NOT NULL DEFAULT '',
			lehrer varchar(40) NOT NULL DEFAULT '',
			fach varchar(40) NOT NULL DEFAULT '',
			gruppe varchar(40) NOT NULL DEFAULT '',
			wstd_klasse decimal(6,2) DEFAULT NULL,
			wstd_lehrer decimal(6,2) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY import_id (import_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$p}usc_klassen_regeln (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			muster varchar(255) NOT NULL,
			ziel varchar(40) NOT NULL DEFAULT '',
			bemerkung varchar(255) NOT NULL DEFAULT '',
			aktiv tinyint(1) unsigned NOT NULL DEFAULT 1,
			position int(10) unsigned NOT NULL DEFAULT 0,
			geaendert_von bigint(20) unsigned NOT NULL DEFAULT 0,
			geaendert_am datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$p}usc_fach_regeln (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			untis_faecher varchar(255) NOT NULL,
			schild_faecher varchar(255) NOT NULL DEFAULT '',
			klassen_muster varchar(255) NOT NULL DEFAULT '',
			bemerkung varchar(255) NOT NULL DEFAULT '',
			aktiv tinyint(1) unsigned NOT NULL DEFAULT 1,
			position int(10) unsigned NOT NULL DEFAULT 0,
			geaendert_von bigint(20) unsigned NOT NULL DEFAULT 0,
			geaendert_am datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset;" );

		// Gemerkte Auswahl, wer ein Fach in einer Klasse bekommt, wenn es mehrere Lehrkräfte gibt.
		dbDelta( "CREATE TABLE {$p}usc_lehrkraft_entscheidungen (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			klasse varchar(40) NOT NULL,
			fach varchar(40) NOT NULL,
			lehrer varchar(40) NOT NULL,
			entschieden_von bigint(20) unsigned NOT NULL DEFAULT 0,
			entschieden_am datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY klasse_fach (klasse,fach)
		) $charset;" );

		// Besteht schon aus der Altversion; Struktur unverändert.
		dbDelta( "CREATE TABLE {$p}usc_schildfaecher (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			interne_kurzform varchar(20) NOT NULL,
			asd_kuerzel varchar(20) NOT NULL,
			bezeichnung varchar(150) NOT NULL,
			PRIMARY KEY  (id)
		) $charset;" );
	}

	/**
	 * Übernimmt die Regeln der Altversion, solange die neuen Regeltabellen leer sind.
	 *
	 * Bei sich überschneidenden Regeln gilt die erste passende. Die Altversion hat die
	 * Fachregeln nach fach_schild sortiert abgearbeitet (Löschungen zuerst) – diese
	 * Reihenfolge wird als position übernommen, damit sich Ergebnisse nicht still ändern.
	 */
	private static function migrate_legacy_rules( wpdb $wpdb ): void {
		$p        = $wpdb->prefix;
		$now      = current_time( 'mysql' );
		$bericht  = [ 'datum' => $now, 'klassen' => 0, 'faecher' => 0 ];

		if ( self::table_exists( $wpdb, "{$p}usc_loesch_klassen" )
			&& 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}usc_klassen_regeln" ) ) {
			$alt = $wpdb->get_results( "SELECT klasse_untis, klasse_schild, bemerkung FROM {$p}usc_loesch_klassen ORDER BY id" );
			foreach ( $alt as $i => $row ) {
				$muster = trim( (string) $row->klasse_untis );
				if ( '' === $muster ) {
					continue;
				}
				$wpdb->insert( "{$p}usc_klassen_regeln", [
					'muster'       => $muster,
					'ziel'         => trim( (string) $row->klasse_schild ),
					'bemerkung'    => trim( (string) $row->bemerkung ),
					'aktiv'        => 1,
					'position'     => ( $i + 1 ) * 10,
					'geaendert_am' => $now,
				] );
				$bericht['klassen']++;
			}
		}

		if ( self::table_exists( $wpdb, "{$p}usc_loesch_faecher" )
			&& 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}usc_fach_regeln" ) ) {
			$alt = $wpdb->get_results( "SELECT fach_untis, fach_schild, klasse, bemerkung FROM {$p}usc_loesch_faecher ORDER BY fach_schild, id" );
			foreach ( $alt as $i => $row ) {
				$untis = self::normalize_faecher( (string) $row->fach_untis );
				if ( '' === $untis ) {
					continue;
				}
				$wpdb->insert( "{$p}usc_fach_regeln", [
					'untis_faecher'  => $untis,
					'schild_faecher' => self::normalize_faecher( (string) $row->fach_schild ),
					'klassen_muster' => trim( (string) $row->klasse ),
					'bemerkung'      => trim( (string) $row->bemerkung ),
					'aktiv'          => 1,
					'position'       => ( $i + 1 ) * 10,
					'geaendert_am'   => $now,
				] );
				$bericht['faecher']++;
			}
		}

		if ( $bericht['klassen'] > 0 || $bericht['faecher'] > 0 ) {
			update_option( self::OPTION_MIGRATION, $bericht, false );
		}
	}

	/** "LF1 | LF2 |" → "LF1|LF2" */
	private static function normalize_faecher( string $wert ): string {
		$teile = array_filter( array_map( 'trim', explode( '|', $wert ) ), static fn( $t ) => '' !== $t );
		return implode( '|', $teile );
	}

	private static function table_exists( wpdb $wpdb, string $table ): bool {
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
