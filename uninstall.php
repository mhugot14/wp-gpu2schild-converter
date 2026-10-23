<?php
/**
 * Wird ausgeführt, wenn das Plugin im Backend gelöscht wird (nicht beim Deaktivieren).
 *
 * Entfernt alle Tabellen, Einstellungen und Zwischenspeicher des Plugins – auch die der
 * Vorversion 0.9.x, falls noch vorhanden. Regeln, Lehrkraft-Auswahlen, Importe und das
 * Zugriffsprotokoll sind danach endgültig gelöscht.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tabellen = [
	'usc_importe',
	'usc_import_zeilen',
	'usc_klassen_regeln',
	'usc_fach_regeln',
	'usc_lehrkraft_entscheidungen',
	'usc_schildfaecher',
	'usc_protokoll',
	// Vorversion
	'usc_loesch_klassen',
	'usc_loesch_faecher',
	'usc_schildimport',
];
foreach ( $tabellen as $tabelle ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$tabelle}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

$optionen = [
	'mh_usc_db_version',
	'mh_usc_migration',
	'mh_usc_settings',
	'mh_usc_stand',
	'mh_usc_schildfaecher_stand',
	'mh_usc_altregeln_sicherung',
	'mh_usc_altversion_entfernt',
];
foreach ( $optionen as $option ) {
	delete_option( $option );
}

// Zwischengespeicherte Ergebnisse (enthalten Lehrkraft-Kürzel) und Rückmeldungen.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\\_transient\\_mh\\_usc\\_%'
	    OR option_name LIKE '\\_transient\\_timeout\\_mh\\_usc\\_%'"
);

wp_clear_scheduled_hook( 'mh_usc_aufraeumen' );
wp_clear_scheduled_hook( 'untisSchildConverter/weekly_cron' );

// Exportdateien der Vorversion in wp-admin/ (ungeschützt, mit Lehrkraft-Kürzeln).
foreach ( [ 'schildimport.csv', 'schildimport.xlsx' ] as $datei ) {
	if ( is_file( ABSPATH . 'wp-admin/' . $datei ) ) {
		@unlink( ABSPATH . 'wp-admin/' . $datei );
	}
}
