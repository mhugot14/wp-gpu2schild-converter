<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

use Mh\UntisSchild\Service\Muster;

/**
 * Klassenregel: Untis-Klassen, auf die das Muster passt, werden gelöscht (Ziel leer)
 * oder in die Schild-Klasse umbenannt.
 */
final class Klassen_Regel {

	public function __construct(
		public readonly int $id,
		public readonly string $muster,
		public readonly string $ziel = '',
		public readonly string $bemerkung = '',
		public readonly bool $aktiv = true,
		public readonly int $position = 0,
	) {}

	public function ist_loeschung(): bool {
		return '' === $this->ziel;
	}

	public function kennung(): string {
		return 'K' . $this->id;
	}

	/**
	 * Prüft die Regel vor dem Speichern.
	 *
	 * @return array<string, string> Feld => Fehlermeldung
	 */
	public function fehler(): array {
		$fehler = [];
		if ( [] === Muster::teile( $this->muster ) ) {
			$fehler['muster'] = 'Bitte eine Untis-Klasse oder ein Muster angeben.';
		} elseif ( Muster::ist_alle( $this->muster ) ) {
			$fehler['muster'] = 'Das Muster trifft alle Klassen – das ist sicher nicht gemeint.';
		} elseif ( $m = Muster::pruefe( $this->muster ) ) {
			$fehler['muster'] = $m;
		}

		if ( '' !== $this->ziel ) {
			if ( Muster::hat_platzhalter( $this->ziel ) || str_contains( $this->ziel, ',' ) ) {
				$fehler['ziel'] = 'Die Schild-Klasse ist ein einzelner Name ohne Platzhalter und ohne Komma.';
			} elseif ( mb_strlen( $this->ziel ) > 40 ) {
				$fehler['ziel'] = 'Die Schild-Klasse darf höchstens 40 Zeichen lang sein.';
			} elseif ( Muster::hat_platzhalter( $this->muster ) || count( Muster::teile( $this->muster ) ) > 1 ) {
				$fehler['ziel'] = 'Umbenennen geht nur für eine einzelne Klasse – sonst würden mehrere Klassen zu einer zusammengelegt.';
			}
		}
		return $fehler;
	}
}
