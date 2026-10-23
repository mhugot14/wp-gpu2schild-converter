<?php

declare(strict_types=1);

use Mh\UntisSchild\Model\Unterricht_Zeile;

final class Test_Fehler extends RuntimeException {
}

/**
 * Minimale Basisklasse mit Prüfungen und Hilfen für erfundene Testdaten.
 */
abstract class Test_Case {

	protected function gleich( mixed $erwartet, mixed $ist, string $text = '' ): void {
		if ( $erwartet !== $ist ) {
			throw new Test_Fehler( ( '' !== $text ? $text . ': ' : '' )
				. 'erwartet ' . var_export( $erwartet, true ) . ', ist ' . var_export( $ist, true ) );
		}
	}

	protected function wahr( bool $bedingung, string $text ): void {
		if ( ! $bedingung ) {
			throw new Test_Fehler( $text );
		}
	}

	protected function wirft( string $klasse, callable $f, string $text ): void {
		try {
			$f();
		} catch ( Throwable $e ) {
			if ( $e instanceof $klasse ) {
				return;
			}
			throw new Test_Fehler( $text . ': falsche Ausnahme ' . get_class( $e ) . ' – ' . $e->getMessage() );
		}
		throw new Test_Fehler( $text . ': keine Ausnahme' );
	}

	/**
	 * Erfundene Unterrichtszeile. Lehrkraft-Kürzel sind Fantasiewerte.
	 */
	protected function z( string $klasse, string $fach, string $lehrer, string $unr = '', string $gruppe = '', ?float $wk = 2.0, ?float $wl = 2.0 ): Unterricht_Zeile {
		static $nr = 0;
		$nr++;
		return new Unterricht_Zeile(
			klasse: $klasse,
			fach: $fach,
			lehrer: $lehrer,
			unr: '' !== $unr ? $unr : (string) ( 900 + $nr ),
			gruppe: $gruppe,
			gilt: Unterricht_Zeile::gilt_aus_gruppe( $gruppe ),
			wstd_klasse: $wk,
			wstd_lehrer: $wl,
			gewicht: $wl ?? 0.0,
			quelle: [ $nr ],
		);
	}

	/**
	 * Ergebniszeilen kompakt als "Klasse|Fach|Lehrer|WstdKlasse" zum Vergleichen.
	 *
	 * @param Unterricht_Zeile[] $zeilen
	 * @return string[]
	 */
	protected function kurz( array $zeilen ): array {
		$out = array_map( static fn( Unterricht_Zeile $z ) => implode( '|', [
			$z->klasse, $z->fach, $z->lehrer, null === $z->wstd_klasse ? '-' : (string) $z->wstd_klasse,
		] ), $zeilen );
		sort( $out );
		return $out;
	}
}
