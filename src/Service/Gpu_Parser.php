<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use Mh\UntisSchild\Model\Gpu_Import;
use Mh\UntisSchild\Model\Unterricht_Zeile;

/**
 * Liest die GPU002 (Unterricht) aus Untis.
 *
 * Kodierung (UTF-8 oder ANSI) und Trennzeichen (Komma, Semikolon, Tabulator) werden
 * erkannt, damit beim Hochladen nichts falsch eingestellt werden kann.
 */
final class Gpu_Parser {

	// Spalten der GPU002 nach Untis-Spezifikation (0-basiert).
	public const SPALTE_UNR         = 0;
	public const SPALTE_WSTD_KLASSE = 2;
	public const SPALTE_WSTD_LEHRER = 3;
	public const SPALTE_KLASSE      = 4;
	public const SPALTE_LEHRER      = 5;
	public const SPALTE_FACH        = 6;
	public const SPALTE_GRUPPE      = 11;

	/** Weniger Spalten hat keine GPU002 – dann ist es eine andere Datei oder das Trennzeichen passt nicht. */
	private const MIN_SPALTEN = 12;

	public function parse( string $inhalt ): Gpu_Import {
		if ( str_starts_with( $inhalt, "\xEF\xBB\xBF" ) ) {
			$inhalt = substr( $inhalt, 3 );
		}
		$kodierung = 'UTF-8';
		if ( ! mb_check_encoding( $inhalt, 'UTF-8' ) ) {
			$kodierung = 'Windows-1252';
			$inhalt    = mb_convert_encoding( $inhalt, 'UTF-8', 'Windows-1252' );
		}

		if ( '' === trim( $inhalt ) ) {
			throw new Import_Fehler( 'Die Datei ist leer.' );
		}

		$trennzeichen = $this->erkenne_trennzeichen( $inhalt );
		$zeilen_roh   = $this->lies_csv( $inhalt, $trennzeichen );

		$max_spalten = max( array_map( 'count', $zeilen_roh ) );
		if ( $max_spalten < self::MIN_SPALTEN ) {
			throw new Import_Fehler( sprintf(
				'Die Datei hat nur %d Spalten. Eine GPU002 aus Untis hat über 40 – bitte prüfen, ob die richtige Datei (Unterricht, GPU002) gewählt wurde.',
				$max_spalten
			) );
		}

		// Untis exportiert ohne Kopfzeile. Steht in Spalte 1 keine Unterrichtsnummer, ist es eine.
		$kopfzeile = ! is_numeric( trim( (string) ( $zeilen_roh[0][ self::SPALTE_UNR ] ?? '' ) ) );
		$start     = $kopfzeile ? 1 : 0;

		$zeilen        = [];
		$uebersprungen = [];
		$gruppen       = [];
		for ( $i = $start, $n = count( $zeilen_roh ); $i < $n; $i++ ) {
			$z  = array_map( static fn( $w ) => trim( (string) $w ), $zeilen_roh[ $i ] );
			$nr = $i + 1;

			if ( count( $z ) < self::MIN_SPALTEN ) {
				$uebersprungen[] = [ 'zeile' => $nr, 'grund' => 'zu wenige Spalten' ];
				continue;
			}
			$klasse = $z[ self::SPALTE_KLASSE ];
			$fach   = $z[ self::SPALTE_FACH ];
			if ( '' === $klasse || '' === $fach ) {
				$uebersprungen[] = [ 'zeile' => $nr, 'grund' => '' === $klasse ? 'ohne Klasse' : 'ohne Fach' ];
				continue;
			}

			$gruppe       = $z[ self::SPALTE_GRUPPE ];
			$wstd_lehrer  = self::zahl( $z[ self::SPALTE_WSTD_LEHRER ] );
			$gruppen[ $gruppe ] = ( $gruppen[ $gruppe ] ?? 0 ) + 1;

			$zeilen[] = new Unterricht_Zeile(
				klasse: $klasse,
				fach: $fach,
				lehrer: $z[ self::SPALTE_LEHRER ],
				unr: $z[ self::SPALTE_UNR ],
				gruppe: $gruppe,
				gilt: Unterricht_Zeile::gilt_aus_gruppe( $gruppe ),
				wstd_klasse: self::zahl( $z[ self::SPALTE_WSTD_KLASSE ] ),
				wstd_lehrer: $wstd_lehrer,
				gewicht: $wstd_lehrer ?? 0.0,
				quelle: [ $nr ],
			);
		}

		if ( [] === $zeilen ) {
			throw new Import_Fehler( 'Die Datei enthält keine Unterrichtszeilen mit Klasse und Fach.' );
		}
		arsort( $gruppen );

		return new Gpu_Import( $zeilen, $kodierung, $trennzeichen, $kopfzeile, count( $zeilen_roh ) - $start, $uebersprungen, $gruppen );
	}

	/**
	 * Das Trennzeichen, das in den ersten Zeilen die meisten und gleichmäßigsten Spalten ergibt.
	 */
	private function erkenne_trennzeichen( string $inhalt ): string {
		$probe = array_slice( preg_split( '/\r\n|\n|\r/', $inhalt ), 0, 30 );
		$probe = array_filter( $probe, static fn( $z ) => '' !== trim( $z ) );

		$bestes  = ',';
		$wertung = -1;
		foreach ( [ ',', ';', "\t" ] as $kandidat ) {
			$anzahlen = array_map( static fn( $z ) => count( str_getcsv( $z, $kandidat, '"', '' ) ), $probe );
			$w        = min( $anzahlen ) * ( 1 === count( array_unique( $anzahlen ) ) ? 2 : 1 );
			if ( $w > $wertung ) {
				$wertung = $w;
				$bestes  = $kandidat;
			}
		}
		return $bestes;
	}

	/**
	 * Über einen Stream statt zeilenweise, damit Zeilenumbrüche in Anführungszeichen nicht stören.
	 *
	 * @return array<int, array<int, ?string>>
	 */
	private function lies_csv( string $inhalt, string $trennzeichen ): array {
		$stream = fopen( 'php://temp', 'r+' );
		fwrite( $stream, $inhalt );
		rewind( $stream );

		$zeilen = [];
		while ( false !== ( $z = fgetcsv( $stream, 0, $trennzeichen, '"', '' ) ) ) {
			if ( [ null ] === $z ) {
				continue; // Leerzeile
			}
			$zeilen[] = $z;
		}
		fclose( $stream );
		return $zeilen;
	}

	private static function zahl( string $wert ): ?float {
		$wert = str_replace( ',', '.', trim( $wert ) );
		return is_numeric( $wert ) ? (float) $wert : null;
	}
}
