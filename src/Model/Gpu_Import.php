<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

/**
 * Ergebnis des Einlesens einer GPU002: die Zeilen und was beim Einlesen aufgefallen ist.
 */
final class Gpu_Import {

	/**
	 * @param Unterricht_Zeile[]                     $zeilen
	 * @param array<int, array{zeile:int, grund:string}> $uebersprungen
	 * @param array<string, int>                     $gruppen  Werte der Spalte „Gruppe“ => Anzahl.
	 */
	public function __construct(
		public readonly array $zeilen,
		public readonly string $kodierung,
		public readonly string $trennzeichen,
		public readonly bool $kopfzeile,
		public readonly int $zeilen_gesamt,
		public readonly array $uebersprungen,
		public readonly array $gruppen,
	) {}

	public function trennzeichen_name(): string {
		return match ( $this->trennzeichen ) {
			','     => 'Komma',
			';'     => 'Semikolon',
			"\t"    => 'Tabulator',
			default => $this->trennzeichen,
		};
	}
}
