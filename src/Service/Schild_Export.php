<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use Mh\UntisSchild\Model\Unterricht_Zeile;

/**
 * Bildet die Ergebniszeilen auf das Schild-Importformat ab.
 *
 * Kopfzeile und Spaltenfolge entsprechen exakt der Altversion (auch die Kleinschreibung
 * von „klasse“ und „lehrer“), damit die gespeicherte Importvorlage in Schild weiter passt.
 */
final class Schild_Export {

	public const KOPFZEILE = [
		'Kursbezeichnung',
		'klasse',
		'Jahr',
		'Halbjahr',
		'Jahrgang',
		'Fach',
		'Kursart',
		'Wochenstunden',
		'Wochenstunden Lehrer',
		'lehrer',
	];

	/**
	 * @param Unterricht_Zeile[] $zeilen
	 * @return array<int, array<int, string|int|float>>
	 */
	public static function zeilen( array $zeilen, int $schuljahr, int $halbjahr ): array {
		$ergebnis = [];
		foreach ( $zeilen as $z ) {
			$ergebnis[] = [
				'',
				$z->klasse,
				$schuljahr,
				$halbjahr,
				'',
				$z->fach,
				'',
				self::stunden( $z->wstd_klasse ),
				self::stunden( $z->wstd_lehrer ),
				$z->lehrer,
			];
		}
		return $ergebnis;
	}

	/**
	 * Ganze Stunden als int, damit Excel/Schild keine „4.0“ sieht; unbekannt bleibt leer.
	 */
	private static function stunden( ?float $wert ): string|int|float {
		if ( null === $wert ) {
			return '';
		}
		return floor( $wert ) === $wert ? (int) $wert : $wert;
	}
}
