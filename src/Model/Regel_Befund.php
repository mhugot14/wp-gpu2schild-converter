<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

/**
 * Ein Befund des Regelprüfers zu einer oder mehreren Regeln.
 */
final class Regel_Befund {

	/** Eine Regel weiter oben macht dasselbe – diese ist überflüssig. */
	public const DOPPELT = 'doppelt';
	/** Eine Regel weiter oben greift immer vorher, macht aber etwas anderes – diese wirkt nie. */
	public const WIRKUNGSLOS = 'wirkungslos';
	/** Mehrere Regeln lassen sich ohne Änderung des Ergebnisses zu einer zusammenfassen. */
	public const ZUSAMMENFASSBAR = 'zusammenfassbar';
	/** Die Regel erzeugt ein Fach, das nicht in der Schild-Fächerliste steht. */
	public const SCHILD_UNBEKANNT = 'schild_unbekannt';
	/** Die Regel trifft im aktuellen Import keine Zeile. */
	public const OHNE_TREFFER = 'ohne_treffer';

	/**
	 * @param int[]                          $regel_ids Betroffene Regeln (bei ZUSAMMENFASSBAR alle der Gruppe).
	 * @param ?int                           $bezug_id  Regel, auf die sich der Befund bezieht (die weiter oben).
	 * @param Fach_Regel|Klassen_Regel|null  $vorschlag Zusammengefasste Regel.
	 * @param string[]                       $details   z. B. unbekannte Schild-Fächer.
	 */
	public function __construct(
		public readonly string $typ,
		public readonly array $regel_ids,
		public readonly ?int $bezug_id = null,
		public readonly Fach_Regel|Klassen_Regel|null $vorschlag = null,
		public readonly int $zeilen = 0,
		public readonly array $details = [],
	) {}
}
