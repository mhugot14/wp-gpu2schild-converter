<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

/**
 * Ein Eintrag im Verarbeitungsprotokoll: was ein Pipeline-Schritt mit welchen Zeilen
 * gemacht hat und welche Regel das ausgelöst hat.
 */
final class Protokoll_Eintrag {

	public const SCHRITT_HALBJAHR  = 'halbjahr';
	public const SCHRITT_KLASSEN   = 'klassen';
	public const SCHRITT_FAECHER   = 'faecher';
	public const SCHRITT_KONFLIKTE = 'konflikte';
	public const SCHRITT_DUBLETTEN = 'dubletten';
	public const SCHRITT_PRUEFUNG  = 'pruefung';

	public const AKTION_ENTFERNT        = 'entfernt';
	public const AKTION_GEAENDERT       = 'geaendert';
	public const AKTION_ZUSAMMENGEFASST = 'zusammengefasst';
	public const AKTION_ENTSCHIEDEN     = 'entschieden';
	public const AKTION_OFFEN           = 'offen';
	public const AKTION_HINWEIS         = 'hinweis';

	/**
	 * @param Unterricht_Zeile[] $vorher
	 * @param Unterricht_Zeile[] $nachher
	 * @param ?string            $regel   Kennung der auslösenden Regel (K12, F5) oder null.
	 */
	public function __construct(
		public readonly string $schritt,
		public readonly string $aktion,
		public readonly string $text,
		public readonly array $vorher = [],
		public readonly array $nachher = [],
		public readonly ?string $regel = null,
		public readonly string $klasse = '',
		public readonly string $fach = '',
	) {}
}
