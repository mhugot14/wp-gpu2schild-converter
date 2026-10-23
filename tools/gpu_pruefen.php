<?php
/**
 * Prüfskript für eine GPU002 aus Untis – läuft lokal ohne WordPress.
 *
 * Gibt das Spaltenlayout aus, ohne Lehrerkürzel preiszugeben:
 * - Lehrerkürzel (Spalte 6) werden durch Pseudonyme L01, L02 … ersetzt. Gleiches Kürzel =
 *   gleiches Pseudonym, die Vergabe ist zufällig (keine Rückschlüsse über die Reihenfolge).
 * - Jeder Wert, der irgendwo als Lehrerkürzel vorkommt, wird auch in allen anderen Spalten
 *   maskiert – falls das Layout verschoben ist.
 * - Von Spalten, die das Plugin nicht braucht (u. a. Freitexte), erscheinen nur Kennzahlen.
 * Die Zuordnung Kürzel → Pseudonym wird nirgends gespeichert.
 *
 * Aufruf (Eingabeaufforderung im Plugin-Ordner):
 *   C:\xampp\php\php.exe tools\gpu_pruefen.php "C:\Pfad\GPU002.TXT"
 *   C:\xampp\php\php.exe tools\gpu_pruefen.php "C:\Pfad\GPU002.TXT" --anonym="C:\Pfad\gpu_anonym.csv"
 *
 * Mit --anonym entsteht zusätzlich eine Kopie, die nur U-Nr, Wochenstunden (Spalten 2-4), Klasse,
 * Lehrer-Pseudonym, Fach und Gruppe enthält (Semikolon, UTF-8). Bitte vor dem Weitergeben
 * die Ausgabe und die Datei kurz selbst ansehen.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 'Nur über die Kommandozeile aufrufen.' );
}

// Spalten nach Untis-Spezifikation GPU002 (1-basiert); nur die, die das Plugin verwendet.
const SPALTE_UNR    = 1;
const SPALTE_WSTD   = 2;
const SPALTE_KLASSE = 5;
const SPALTE_LEHRER = 6;
const SPALTE_FACH   = 7;
const SPALTE_GRUPPE = 12;

const SPALTEN_NAMEN = [
	1  => 'Unterrichtsnummer',
	2  => 'Wochenstunden',
	3  => 'Wochenstd. Klasse',
	4  => 'Wochenstd. Lehrer',
	5  => 'Klasse',
	6  => 'Lehrer',
	7  => 'Fach',
	8  => 'Fachraum',
	9  => 'Statistik 1 Unterricht',
	10 => 'Schülerzahl',
	11 => 'Wochenwert',
	12 => 'Gruppe',
	13 => 'Zeilentext 1',
	14 => 'Zeilenwert',
	15 => 'Datum von',
	16 => 'Datum bis',
	17 => 'Jahreswert',
	18 => 'Text',
];

// ---------------------------------------------------------------------------
// Argumente
// ---------------------------------------------------------------------------
$pfad        = null;
$anonym_pfad = null;
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( str_starts_with( $arg, '--anonym=' ) ) {
		$anonym_pfad = substr( $arg, 9 );
	} elseif ( null === $pfad ) {
		$pfad = $arg;
	}
}
if ( null === $pfad || ! is_file( $pfad ) ) {
	fwrite( STDERR, "Datei nicht gefunden.\nAufruf: php tools/gpu_pruefen.php \"GPU002.TXT\" [--anonym=\"ausgabe.csv\"]\n" );
	exit( 1 );
}

// ---------------------------------------------------------------------------
// Einlesen: Kodierung und Trennzeichen erkennen
// ---------------------------------------------------------------------------
$inhalt = (string) file_get_contents( $pfad );
$bom    = str_starts_with( $inhalt, "\xEF\xBB\xBF" );
if ( $bom ) {
	$inhalt = substr( $inhalt, 3 );
}
$kodierung = mb_check_encoding( $inhalt, 'UTF-8' ) ? 'UTF-8' : 'Windows-1252 (ANSI)';
if ( 'UTF-8' !== $kodierung ) {
	$inhalt = mb_convert_encoding( $inhalt, 'UTF-8', 'Windows-1252' );
}

$zeilen_text = preg_split( '/\r\n|\n|\r/', $inhalt );
$zeilen_text = array_values( array_filter( $zeilen_text, static fn( $z ) => '' !== trim( $z ) ) );

// Trennzeichen: das mit den meisten und gleichmäßigsten Spalten in den ersten Zeilen.
$trennzeichen = ',';
$beste_wertung = -1;
foreach ( [ ',', ';', "\t" ] as $kandidat ) {
	$anzahlen = array_map(
		static fn( $z ) => count( str_getcsv( $z, $kandidat, '"', '' ) ),
		array_slice( $zeilen_text, 0, 50 )
	);
	$wertung = min( $anzahlen ) * ( count( array_unique( $anzahlen ) ) === 1 ? 2 : 1 );
	if ( $wertung > $beste_wertung ) {
		$beste_wertung = $wertung;
		$trennzeichen  = $kandidat;
	}
}

$zeilen = array_map( static fn( $z ) => array_map( 'trim', str_getcsv( $z, $trennzeichen, '"', '' ) ), $zeilen_text );

// Kopfzeile? Untis exportiert normalerweise ohne. Erkennbar daran, dass Spalte 1 keine Zahl ist.
$kopfzeile = isset( $zeilen[0][0] ) && ! is_numeric( $zeilen[0][0] );
if ( $kopfzeile ) {
	array_shift( $zeilen );
}

// ---------------------------------------------------------------------------
// Pseudonyme für Lehrerkürzel
// ---------------------------------------------------------------------------
$kuerzel = [];
foreach ( $zeilen as $z ) {
	$wert = $z[ SPALTE_LEHRER - 1 ] ?? '';
	if ( '' !== $wert ) {
		$kuerzel[ $wert ] = true;
	}
}
$kuerzel = array_keys( $kuerzel );
shuffle( $kuerzel );
$pseudonym = [];
$breite    = max( 2, strlen( (string) count( $kuerzel ) ) );
foreach ( $kuerzel as $i => $k ) {
	$pseudonym[ (string) $k ] = 'L' . str_pad( (string) ( $i + 1 ), $breite, '0', STR_PAD_LEFT );
}

/** Maskiert Lehrerkürzel in beliebigen Spalten. */
$sicher = static function ( string $wert ) use ( $pseudonym ): string {
	return $pseudonym[ $wert ] ?? $wert;
};

/** Form eines Wertes ohne Inhalt: Buchstaben → A, Ziffern → 9. */
$form = static function ( string $wert ): string {
	return (string) preg_replace( [ '/\p{L}/u', '/\d/' ], [ 'A', '9' ], $wert );
};

// ---------------------------------------------------------------------------
// Auswertung
// ---------------------------------------------------------------------------
$ausgabe = [];
$p = static function ( string $text = '' ) use ( &$ausgabe ): void {
	$ausgabe[] = $text;
};

$spaltenzahlen = array_count_values( array_map( 'count', $zeilen ) );
ksort( $spaltenzahlen );

$p( '=== GPU002-Prüfung ===' );
$p( 'Kodierung:      ' . $kodierung . ( $bom ? ' mit BOM' : '' ) );
$p( 'Trennzeichen:   ' . match ( $trennzeichen ) { ',' => 'Komma', ';' => 'Semikolon', default => 'Tabulator' } );
$p( 'Kopfzeile:      ' . ( $kopfzeile ? 'ja (übersprungen)' : 'nein' ) );
$p( 'Datenzeilen:    ' . count( $zeilen ) );
$p( 'Spalten/Zeile:  ' . implode( ', ', array_map( static fn( $n, $c ) => "$n Spalten × $c", array_keys( $spaltenzahlen ), $spaltenzahlen ) ) );
$p( 'Lehrkräfte:     ' . count( $kuerzel ) . ' verschiedene Kürzel (pseudonymisiert)' );
$p();

$genutzt   = [ SPALTE_UNR, SPALTE_WSTD, SPALTE_KLASSE, SPALTE_LEHRER, SPALTE_FACH, SPALTE_GRUPPE ];
$max_spalte = max( array_keys( $spaltenzahlen ) ?: [ 0 ] );

$p( '--- Spalten (★ = vom Plugin genutzt) ---' );
$leere_spalten = [];
for ( $s = 1; $s <= $max_spalte; $s++ ) {
	$werte     = array_map( static fn( $z ) => (string) ( $z[ $s - 1 ] ?? '' ), $zeilen );
	$gefuellt  = array_values( array_filter( $werte, static fn( $w ) => '' !== $w ) );
	$distinct  = array_unique( $gefuellt );
	$numerisch = count( array_filter( $gefuellt, static fn( $w ) => is_numeric( str_replace( ',', '.', $w ) ) ) );
	$max_len   = $gefuellt ? max( array_map( 'mb_strlen', $gefuellt ) ) : 0;

	if ( ! $gefuellt && ! in_array( $s, $genutzt, true ) ) {
		$leere_spalten[] = $s;
		continue;
	}

	// sprintf zählt Bytes, daher von Hand auffüllen (Umlaute in den Spaltennamen).
	$name = SPALTEN_NAMEN[ $s ] ?? '–';
	$name .= str_repeat( ' ', max( 0, 24 - mb_strlen( $name ) ) );
	$kopf = sprintf( '%s %2d %s gefüllt %5d | verschieden %5d | numerisch %5d | max. Länge %3d',
		in_array( $s, $genutzt, true ) ? '★' : ' ', $s, $name, count( $gefuellt ), count( $distinct ), $numerisch, $max_len );
	$p( $kopf );

	if ( ! in_array( $s, $genutzt, true ) || ! $gefuellt ) {
		continue;
	}
	if ( SPALTE_LEHRER === $s ) {
		$formen = array_count_values( array_map( $form, $distinct ) );
		arsort( $formen );
		$p( '      Form der Kürzel: ' . implode( ', ', array_map( static fn( $f, $c ) => "$f ($c)", array_keys( $formen ), $formen ) ) );
		$p( '      Beispiele: ' . implode( ', ', array_slice( array_map( $sicher, $distinct ), 0, 5 ) ) );
		continue;
	}
	$beispiele = array_slice( array_values( array_unique( array_map( $sicher, $distinct ) ) ), 0, 8 );
	$p( '      Beispiele: ' . implode( ' | ', $beispiele ) );
}
if ( $leere_spalten ) {
	$p( '  Immer leer: Spalten ' . implode( ', ', $leere_spalten ) );
}
$p();

// Gruppe: alle Werte mit Häufigkeit (Halbjahreslogik).
$gruppen = array_count_values( array_map( static fn( $z ) => $sicher( (string) ( $z[ SPALTE_GRUPPE - 1 ] ?? '' ) ), $zeilen ) );
arsort( $gruppen );
$p( '--- Werte in Spalte ' . SPALTE_GRUPPE . ' (Gruppe) ---' );
foreach ( array_slice( $gruppen, 0, 25, true ) as $wert => $anzahl ) {
	$p( sprintf( '  %-20s %5d', '' === (string) $wert ? '(leer)' : $wert, $anzahl ) );
}
if ( count( $gruppen ) > 25 ) {
	$p( '  … ' . ( count( $gruppen ) - 25 ) . ' weitere' );
}
$p();

// Kennzahlen für die Pipeline.
$kombi       = [];
$unr_klassen = [];
foreach ( $zeilen as $z ) {
	$klasse = $z[ SPALTE_KLASSE - 1 ] ?? '';
	$fach   = $z[ SPALTE_FACH - 1 ] ?? '';
	$lehrer = $z[ SPALTE_LEHRER - 1 ] ?? '';
	$unr    = $z[ SPALTE_UNR - 1 ] ?? '';
	$kombi[ $klasse . "\0" . $fach ][ $lehrer ] = true;
	$unr_klassen[ $unr ][ $klasse ] = true;
}
$mehrfach = count( array_filter( $kombi, static fn( $l ) => count( $l ) > 1 ) );
$kopplung = count( array_filter( $unr_klassen, static fn( $k ) => count( $k ) > 1 ) );
$leer     = static fn( int $s ) => count( array_filter( $zeilen, static fn( $z ) => '' === ( $z[ $s - 1 ] ?? '' ) ) );

$p( '--- Kennzahlen ---' );
$p( 'Klasse+Fach-Kombinationen:            ' . count( $kombi ) );
$p( '  davon mit mehreren Lehrkräften:     ' . $mehrfach );
$p( 'Unterrichtsnummern über mehrere Kl.:  ' . $kopplung . ' (Kopplungen)' );
$p( 'Zeilen ohne Klasse / Lehrer / Fach:   ' . $leer( SPALTE_KLASSE ) . ' / ' . $leer( SPALTE_LEHRER ) . ' / ' . $leer( SPALTE_FACH ) );
$p( 'Form der Wochenstunden:               ' . implode( ', ', array_slice( array_keys( array_count_values( array_map(
	static fn( $z ) => $form( (string) ( $z[ SPALTE_WSTD - 1 ] ?? '' ) ), $zeilen ) ) ), 0, 6 ) ) );

echo implode( PHP_EOL, $ausgabe ), PHP_EOL;

// ---------------------------------------------------------------------------
// Optional: pseudonymisierte Kopie mit den benötigten Spalten
// ---------------------------------------------------------------------------
if ( null !== $anonym_pfad ) {
	$fh = fopen( $anonym_pfad, 'w' );
	if ( false === $fh ) {
		fwrite( STDERR, "Ausgabedatei konnte nicht geschrieben werden.\n" );
		exit( 1 );
	}
	fputcsv( $fh, [ 'U-Nr', 'Wochenstunden', 'Wochenstd. Klasse', 'Wochenstd. Lehrer', 'Klasse', 'Lehrer', 'Fach', 'Gruppe' ], ';', '"', '' );
	foreach ( $zeilen as $z ) {
		fputcsv( $fh, [
			$z[ SPALTE_UNR - 1 ] ?? '',
			$z[ SPALTE_WSTD - 1 ] ?? '',
			$z[ 2 ] ?? '',
			$z[ 3 ] ?? '',
			$sicher( $z[ SPALTE_KLASSE - 1 ] ?? '' ),
			$pseudonym[ $z[ SPALTE_LEHRER - 1 ] ?? '' ] ?? '',
			$sicher( $z[ SPALTE_FACH - 1 ] ?? '' ),
			$sicher( $z[ SPALTE_GRUPPE - 1 ] ?? '' ),
		], ';', '"', '' );
	}
	fclose( $fh );
	echo PHP_EOL, 'Pseudonymisierte Kopie geschrieben: ', $anonym_pfad, PHP_EOL;
}
