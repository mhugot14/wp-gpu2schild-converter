<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

/**
 * Ergebnis eines Pipeline-Laufs: die fertigen Zeilen für Schild und alles, was zur
 * Nachvollziehbarkeit gebraucht wird.
 */
final class Pipeline_Ergebnis {

	/**
	 * @param Unterricht_Zeile[]                        $zeilen
	 * @param Protokoll_Eintrag[]                       $protokoll
	 * @param Konflikt[]                                $konflikte
	 * @param array<string, string[]>                   $unbekannte_faecher Fach => Klassen; nur bei Abgleich.
	 * @param array<string, array{vorher:int, nachher:int}> $statistik     Schritt => Zeilenzahlen.
	 * @param array<string, int>                        $treffer            Regelkennung => betroffene Zeilen.
	 * @param bool                                      $abgleich           Wurde mit der Schild-Fächerliste abgeglichen?
	 */
	public function __construct(
		public readonly array $zeilen,
		public readonly array $protokoll,
		public readonly array $konflikte,
		public readonly array $unbekannte_faecher,
		public readonly array $statistik,
		public readonly array $treffer,
		public readonly bool $abgleich,
	) {}

	/**
	 * @return Konflikt[]
	 */
	public function offene_konflikte(): array {
		return array_values( array_filter( $this->konflikte, static fn( Konflikt $k ) => $k->ist_offen() ) );
	}

	/**
	 * Solange ein Konflikt offen ist, stünden für dasselbe Fach mehrere Lehrkräfte in der
	 * Datei – Schild würde dann still die letzte nehmen (der Fehler der Altversion).
	 */
	public function export_gesperrt(): bool {
		return [] !== $this->offene_konflikte();
	}

	/**
	 * @return Protokoll_Eintrag[]
	 */
	public function protokoll_fuer( string $schritt ): array {
		return array_values( array_filter( $this->protokoll, static fn( Protokoll_Eintrag $e ) => $e->schritt === $schritt ) );
	}

	public function treffer_fuer( string $regel_kennung ): int {
		return $this->treffer[ $regel_kennung ] ?? 0;
	}
}
