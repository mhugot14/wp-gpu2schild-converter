<?php

declare(strict_types=1);

use Mh\UntisSchild\Service\Gpu_Parser;
use Mh\UntisSchild\Service\Import_Fehler;

final class Gpu_Parser_Test extends Test_Case {

	/**
	 * Erfundene GPU002-Zeile mit 47 Spalten wie aus Untis.
	 */
	private function gpu_zeile( int $unr, string $klasse, string $lehrer, string $fach, string $gruppe = '', string $wstd = '2', string $trenn = ',' ): string {
		$wstd    = str_contains( $wstd, $trenn ) ? '"' . $wstd . '"' : $wstd;
		$c       = array_fill( 0, 47, '' );
		$c[0]    = (string) $unr;
		$c[1]    = $wstd;
		$c[2]    = $wstd;
		$c[3]    = $wstd;
		$c[4]    = '"' . $klasse . '"';
		$c[5]    = '"' . $lehrer . '"';
		$c[6]    = '"' . $fach . '"';
		$c[11]   = '' === $gruppe ? '' : '"' . $gruppe . '"';
		$c[41]   = '"Freitext, mit Komma"';
		return implode( $trenn, $c );
	}

	public function test_ansi_komma_ohne_kopfzeile(): void {
		$text = implode( "\r\n", [
			$this->gpu_zeile( 101, 'AK21A', 'MUST', 'LF1' ),
			$this->gpu_zeile( 102, 'ECKU1', 'FANT', 'Dör', '1. HJ', '2,5' ),
			$this->gpu_zeile( 103, '', 'FANT', 'BER' ),
		] ) . "\r\n";
		$import = ( new Gpu_Parser() )->parse( mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' ) );

		$this->gleich( 'Windows-1252', $import->kodierung );
		$this->gleich( ',', $import->trennzeichen );
		$this->gleich( false, $import->kopfzeile );
		$this->gleich( 3, $import->zeilen_gesamt );
		$this->gleich( 2, count( $import->zeilen ) );
		$this->gleich( [ [ 'zeile' => 3, 'grund' => 'ohne Klasse' ] ], $import->uebersprungen );

		$z = $import->zeilen[1];
		$this->gleich( 'Dör', $z->fach, 'Umlaut nach UTF-8' );
		$this->gleich( 'FANT', $z->lehrer );
		$this->gleich( '102', $z->unr );
		$this->gleich( '1', $z->gilt );
		$this->gleich( 2.5, $z->wstd_klasse, 'Dezimalkomma' );
		$this->gleich( 2.5, $z->gewicht );
		$this->gleich( [ 2 ], $z->quelle );
	}

	public function test_semikolon_utf8_mit_bom_und_kopfzeile(): void {
		$kopf = implode( ';', array_map( static fn( $i ) => 'Spalte' . $i, range( 1, 47 ) ) );
		$text = "\xEF\xBB\xBF" . $kopf . "\n" . $this->gpu_zeile( 5, 'MAKU1', 'ABCD', 'WSP', 'WU', '4', ';' ) . "\n";
		$import = ( new Gpu_Parser() )->parse( $text );

		$this->gleich( 'UTF-8', $import->kodierung );
		$this->gleich( ';', $import->trennzeichen );
		$this->gleich( true, $import->kopfzeile );
		$this->gleich( 1, count( $import->zeilen ) );
		$this->gleich( '1+2', $import->zeilen[0]->gilt, 'Gruppe WU gilt ganzjährig' );
		$this->gleich( [ 'WU' => 1 ], $import->gruppen );
	}

	public function test_falsche_datei(): void {
		$this->wirft( Import_Fehler::class, static fn() => ( new Gpu_Parser() )->parse( "Klasse;Fach\nAK1;D\n" ), 'zu wenige Spalten' );
		$this->wirft( Import_Fehler::class, static fn() => ( new Gpu_Parser() )->parse( "  \n" ), 'leere Datei' );
	}

	public function test_gruppe_halbjahr_varianten(): void {
		foreach ( [ '1. HJ' => '1', '1.HJ' => '1', '2 hj' => '2', '2. Halbjahr' => '2', 'GBO' => '1+2', '' => '1+2', '12. HJ' => '1+2' ] as $gruppe => $gilt ) {
			$this->gleich( $gilt, \Mh\UntisSchild\Model\Unterricht_Zeile::gilt_aus_gruppe( (string) $gruppe ), "Gruppe „{$gruppe}“" );
		}
	}
}
