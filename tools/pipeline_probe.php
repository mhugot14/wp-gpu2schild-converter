<?php
/**
 * Probelauf der Pipeline mit einer pseudonymisierten GPU (aus tools/gpu_pruefen.php --anonym)
 * und den Regeln/Schild-Fächern aus der lokalen WordPress-Datenbank.
 *
 * Gibt nur Kennzahlen und Fachkürzel aus – keine Lehrkräfte, auch keine Pseudonyme.
 *
 *   C:\xampp\php\php.exe tools\pipeline_probe.php "C:\Pfad\gpu_anonym.TXT" [halbjahr]
 */

declare(strict_types=1);

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;
use Mh\UntisSchild\Model\Konflikt;
use Mh\UntisSchild\Model\Protokoll_Eintrag as P;
use Mh\UntisSchild\Model\Unterricht_Zeile;
use Mh\UntisSchild\Service\Pipeline;

if ( PHP_SAPI !== 'cli' ) {
	exit( 'Nur über die Kommandozeile aufrufen.' );
}
$pfad     = $argv[1] ?? '';
$halbjahr = (int) ( $argv[2] ?? 1 );
if ( ! is_file( $pfad ) ) {
	fwrite( STDERR, "Aufruf: php tools/pipeline_probe.php \"gpu_anonym.TXT\" [1|2]\n" );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';
global $wpdb;

// Pseudonymisierte Kopie: U-Nr;Wochenstunden;Wochenstd. Klasse;Wochenstd. Lehrer;Klasse;Lehrer;Fach;Gruppe
$zahl   = static fn( string $w ): ?float => is_numeric( str_replace( ',', '.', $w ) ) ? (float) str_replace( ',', '.', $w ) : null;
$zeilen = [];
$fh     = fopen( $pfad, 'r' );
fgetcsv( $fh, 0, ';', '"', '' );
$nr = 1;
while ( false !== ( $c = fgetcsv( $fh, 0, ';', '"', '' ) ) ) {
	$nr++;
	if ( count( $c ) < 8 || '' === $c[4] || '' === $c[6] ) {
		continue;
	}
	$wl       = $zahl( $c[3] );
	$zeilen[] = new Unterricht_Zeile( $c[4], $c[6], $c[5], $c[0], $c[7], Unterricht_Zeile::gilt_aus_gruppe( $c[7] ), $zahl( $c[2] ), $wl, $wl ?? 0.0, [ $nr ] );
}
fclose( $fh );

$p      = $wpdb->prefix;
$kregel = array_map( static fn( $r ) => new Klassen_Regel( (int) $r->id, $r->muster, $r->ziel, $r->bemerkung, (bool) $r->aktiv, (int) $r->position ),
	$wpdb->get_results( "SELECT * FROM {$p}usc_klassen_regeln" ) );
$fregel = array_map( static fn( $r ) => Fach_Regel::aus_text( (int) $r->id, $r->untis_faecher, $r->schild_faecher, $r->klassen_muster, $r->bemerkung, (bool) $r->aktiv, (int) $r->position ),
	$wpdb->get_results( "SELECT * FROM {$p}usc_fach_regeln" ) );
$schild = $wpdb->get_col( "SELECT interne_kurzform FROM {$p}usc_schildfaecher" );

$start = microtime( true );
$r     = ( new Pipeline() )->ausfuehren( $zeilen, $halbjahr, $kregel, $fregel, [], $schild ?: null );
$dauer = microtime( true ) - $start;

printf( "Halbjahr %d · %d Rohzeilen · %d Klassenregeln · %d Fachregeln · %d Schild-Fächer · %.0f ms\n\n",
	$halbjahr, count( $zeilen ), count( $kregel ), count( $fregel ), count( $schild ), $dauer * 1000 );

echo "Schritt            vorher  nachher  Protokolleinträge\n";
foreach ( Pipeline::SCHRITTE as $key => $name ) {
	$s = $r->statistik[ $key ];
	printf( "%-16s %7d %8d %10d\n", $name, $s['vorher'], $s['nachher'], count( $r->protokoll_fuer( $key ) ) );
}

$gruende = array_count_values( array_map( static fn( Konflikt $k ) => $k->grund, $r->konflikte ) );
printf( "\nLehrkraft-Konflikte: %d (meiste Stunden: %d, offen: %d)\n",
	count( $r->konflikte ), $gruende[ Konflikt::GRUND_WOCHENSTUNDEN ] ?? 0, $gruende[ Konflikt::GRUND_OFFEN ] ?? 0 );

$hj_wechsel = count( array_filter( $r->protokoll_fuer( P::SCHRITT_KONFLIKTE ), static fn( $e ) => P::AKTION_ENTFERNT === $e->aktion ) );
printf( "Halbjahreswechsel/leere Lehrkraft bereinigt: %d\n", $hj_wechsel );

$mehrdeutig = count( array_filter( $r->protokoll_fuer( P::SCHRITT_FAECHER ), static fn( $e ) => P::AKTION_HINWEIS === $e->aktion ) );
printf( "Fachregeln mit Überschneidung: %d\n", $mehrdeutig );

$ohne_k = array_filter( $kregel, static fn( $x ) => 0 === $r->treffer_fuer( $x->kennung() ) );
$ohne_f = array_filter( $fregel, static fn( $x ) => 0 === $r->treffer_fuer( $x->kennung() ) );
printf( "Regeln ohne Treffer: %d Klassenregeln, %d Fachregeln\n", count( $ohne_k ), count( $ohne_f ) );

printf( "\nFächer, die in Schild fehlen (%d):\n", count( $r->unbekannte_faecher ) );
foreach ( $r->unbekannte_faecher as $fach => $klassen ) {
	printf( "  %-8s %3d Klassen\n", $fach, count( $klassen ) );
}
printf( "\nExport gesperrt: %s\n", $r->export_gesperrt() ? 'ja (offene Konflikte)' : 'nein' );
