<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

/**
 * Eine Unterrichtszeile auf dem Weg von der GPU002 zur Schild-Datei.
 *
 * Unveränderlich: Jeder Pipeline-Schritt erzeugt mit mit() eine neue Zeile, damit das
 * Protokoll Vorher und Nachher getrennt festhalten kann.
 */
final class Unterricht_Zeile {

	public const GILT_1           = '1';
	public const GILT_2           = '2';
	public const GILT_GANZJAEHRIG = '1+2';

	/**
	 * @param string   $unr         Unterrichtsnummer; nach dem Zusammenfassen z. B. "101+102".
	 * @param string   $gruppe      Originalwert der Spalte „Gruppe“ (z. B. „1. HJ“, „WU“).
	 * @param string   $gilt        Halbjahr, für das der Unterricht gilt (GILT_*).
	 * @param ?float   $wstd_klasse Wochenstunden der Klasse (GPU002 Spalte 3); null = unbekannt.
	 * @param ?float   $wstd_lehrer Wochenstunden der Lehrkraft (GPU002 Spalte 4); null = unbekannt.
	 * @param float    $gewicht     Wochenstunden der Lehrkraft für den Konfliktvorschlag. Bleibt
	 *                              erhalten, auch wenn die Stunden beim Aufteilen unbekannt werden.
	 * @param int[]    $quelle      Zeilennummern in der GPU002, aus denen die Zeile entstanden ist.
	 */
	public function __construct(
		public readonly string $klasse,
		public readonly string $fach,
		public readonly string $lehrer,
		public readonly string $unr = '',
		public readonly string $gruppe = '',
		public readonly string $gilt = self::GILT_GANZJAEHRIG,
		public readonly ?float $wstd_klasse = null,
		public readonly ?float $wstd_lehrer = null,
		public readonly float $gewicht = 0.0,
		public readonly array $quelle = [],
	) {}

	/**
	 * Halbjahr aus der Untis-Gruppe: „1. HJ“, „1.HJ“, „1 Halbjahr“ … → '1'.
	 * Alle anderen Gruppen (z. B. „WU“, „GBO“) gelten ganzjährig.
	 */
	public static function gilt_aus_gruppe( string $gruppe ): string {
		if ( preg_match( '/^\s*([12])\s*\.?\s*(hj|halbjahr)\s*$/iu', $gruppe, $m ) ) {
			return $m[1];
		}
		return self::GILT_GANZJAEHRIG;
	}

	/**
	 * Kopie mit geänderten Feldern, z. B. $zeile->mit( [ 'klasse' => 'ECKU1' ] ).
	 *
	 * @param array<string, mixed> $aenderungen
	 */
	public function mit( array $aenderungen ): self {
		return new self( ...array_merge( get_object_vars( $this ), $aenderungen ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function als_array(): array {
		return get_object_vars( $this );
	}
}
