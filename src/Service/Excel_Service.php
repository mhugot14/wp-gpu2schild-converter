<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel lesen (Schild-Fächerliste) und schreiben (Importdatei für Schild).
 */
final class Excel_Service {

	public static function verfuegbar(): bool {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Liest den Schild-Export „Unterrichtsfächer“. Die Spalten werden über die Kopfzeile
	 * gefunden (Kurzform, ASD, Bezeichnung); ohne passende Kopfzeile gelten die ersten drei.
	 *
	 * @return array<int, array{kurzform:string, asd:string, bezeichnung:string}>
	 */
	public function lies_schild_faecher( string $pfad ): array {
		if ( ! self::verfuegbar() ) {
			throw new Import_Fehler( 'Die PHP-Erweiterung zip fehlt – Excel-Dateien können nicht gelesen werden.' );
		}
		try {
			$reader = IOFactory::createReaderForFile( $pfad );
			$reader->setReadDataOnly( true );
			$zeilen = $reader->load( $pfad )->getActiveSheet()->toArray( null, true, false, false );
		} catch ( \Throwable $e ) {
			throw new Import_Fehler( 'Die Datei konnte nicht als Excel-Tabelle gelesen werden.' );
		}
		if ( count( $zeilen ) < 2 ) {
			throw new Import_Fehler( 'Die Tabelle enthält keine Fächer.' );
		}

		$kopf = array_map( static fn( $w ) => mb_strtolower( trim( (string) $w ) ), $zeilen[0] );
		$such = static function ( array $begriffe, int $standard ) use ( $kopf ): int {
			foreach ( $kopf as $i => $titel ) {
				foreach ( $begriffe as $b ) {
					if ( str_contains( $titel, $b ) ) {
						return $i;
					}
				}
			}
			return $standard;
		};
		// Schild-Kopfzeile: „interne Kurzform“, „ASD-Kürzel“, „Bezeichnung“, „Zeugnis“, …
		$sp_kurz = $such( [ 'kurzform' ], 0 );
		$sp_asd  = $such( [ 'asd' ], 1 );
		$sp_bez  = $such( [ 'bezeichnung' ], 2 );

		$faecher = [];
		foreach ( array_slice( $zeilen, 1 ) as $z ) {
			$kurz = trim( (string) ( $z[ $sp_kurz ] ?? '' ) );
			if ( '' === $kurz ) {
				continue;
			}
			$faecher[ mb_strtolower( $kurz ) ] = [
				'kurzform'    => $kurz,
				'asd'         => trim( (string) ( $z[ $sp_asd ] ?? '' ) ),
				'bezeichnung' => trim( (string) ( $z[ $sp_bez ] ?? '' ) ),
			];
		}
		if ( [] === $faecher ) {
			throw new Import_Fehler( 'In der Tabelle wurde keine Spalte mit Fachkürzeln gefunden.' );
		}
		return array_values( $faecher );
	}

	/**
	 * Schreibt eine Tabelle als XLSX direkt in die Ausgabe (Download), ohne Datei auf dem Server.
	 *
	 * @param string[]                                $kopfzeile
	 * @param array<int, array<int, string|int|float>> $zeilen
	 */
	public function sende_xlsx( string $dateiname, array $kopfzeile, array $zeilen ): void {
		$book  = new Spreadsheet();
		$sheet = $book->getActiveSheet();
		$sheet->setTitle( 'Schildimport' );

		foreach ( array_merge( [ $kopfzeile ], $zeilen ) as $r => $zeile ) {
			foreach ( array_values( $zeile ) as $c => $wert ) {
				$zelle = Coordinate::stringFromColumnIndex( $c + 1 ) . ( $r + 1 );
				// Texte ausdrücklich als Text, damit z. B. Klasse „05“ nicht zur Zahl 5 wird.
				if ( is_string( $wert ) ) {
					$sheet->setCellValueExplicit( $zelle, $wert, DataType::TYPE_STRING );
				} else {
					$sheet->setCellValue( $zelle, $wert );
				}
			}
		}
		$sheet->getStyle( 'A1:' . Coordinate::stringFromColumnIndex( count( $kopfzeile ) ) . '1' )->getFont()->setBold( true );

		// Enthält Lehrkraft-Kürzel: nicht zwischenspeichern (Browser, Proxy).
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $dateiname ) . '"' );
		( new Xlsx( $book ) )->save( 'php://output' );
	}
}
