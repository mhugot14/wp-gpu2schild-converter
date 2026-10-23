<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

/**
 * Einheitliche Mustersprache für Klassen und Untis-Fächer.
 *
 * - exakter Name:        AK21A
 * - Platzhalter * oder %: AK*  (% bleibt erlaubt, weil die Altversion SQL-LIKE benutzt hat)
 * - Liste mit Komma:      AK21A, AK21B, EC*
 * - alle:                 leer, * oder %
 *
 * Groß-/Kleinschreibung spielt keine Rolle, Leerzeichen am Rand werden ignoriert.
 */
final class Muster {

	/**
	 * Zerlegte Muster: Die Pipeline vergleicht jede Zeile mit jeder Regel, daher wird jedes
	 * Muster nur einmal zerlegt und übersetzt.
	 *
	 * @var array<string, array{teile: string[], alle: bool, regex: string[]}>
	 */
	private static array $cache = [];

	/**
	 * @return string[]
	 */
	public static function teile( string $muster ): array {
		return self::zerlegt( $muster )['teile'];
	}

	public static function ist_alle( string $muster ): bool {
		return self::zerlegt( $muster )['alle'];
	}

	public static function hat_platzhalter( string $muster ): bool {
		return false !== strpbrk( $muster, '*%' );
	}

	public static function passt( string $muster, string $wert ): bool {
		$m = self::zerlegt( $muster );
		if ( $m['alle'] ) {
			return true;
		}
		$wert = trim( $wert );
		foreach ( $m['regex'] as $regex ) {
			if ( 1 === preg_match( $regex, $wert ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Formale Prüfung eines Musters.
	 *
	 * @return ?string Fehlermeldung oder null, wenn das Muster in Ordnung ist.
	 */
	public static function pruefe( string $muster ): ?string {
		if ( mb_strlen( $muster ) > 255 ) {
			return 'Das Muster ist zu lang (höchstens 255 Zeichen).';
		}
		if ( preg_match( '/,\s*,|^\s*,|,\s*$/', $muster ) ) {
			return 'Die Liste enthält einen leeren Eintrag (doppeltes Komma?).';
		}
		if ( preg_match( '/[|;"\']/', $muster ) ) {
			return 'Erlaubt sind Namen, Platzhalter (* oder %) und Komma als Trenner – keine Zeichen wie | ; oder Anführungszeichen.';
		}
		return null;
	}

	/**
	 * @return array{teile: string[], alle: bool, regex: string[]}
	 */
	private static function zerlegt( string $muster ): array {
		if ( isset( self::$cache[ $muster ] ) ) {
			return self::$cache[ $muster ];
		}
		$teile = array_values( array_filter( array_map( 'trim', explode( ',', $muster ) ), static fn( $t ) => '' !== $t ) );
		return self::$cache[ $muster ] = [
			'teile' => $teile,
			'alle'  => [] === $teile || in_array( '*', $teile, true ) || in_array( '%', $teile, true ),
			'regex' => array_map(
				static fn( $t ) => '/^' . str_replace( [ '\*', '%' ], '.*', preg_quote( $t, '/' ) ) . '$/iu',
				$teile
			),
		];
	}
}
