<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

/**
 * Ein Fach wird in einer Klasse von mehreren Lehrkräften unterrichtet. Schild nimmt je
 * Fach und Klasse nur eine Lehrkraft auf, daher muss eine ausgewählt werden.
 */
final class Konflikt {

	/** Gespeicherte Entscheidung aus einem früheren Import. */
	public const GRUND_ENTSCHEIDUNG = 'entscheidung';
	/** Eindeutig meiste Wochenstunden. */
	public const GRUND_WOCHENSTUNDEN = 'wochenstunden';
	/** Gleichstand – muss entschieden werden. */
	public const GRUND_OFFEN = 'offen';

	/**
	 * @param array<string, float> $kandidaten Lehrkraft => Wochenstunden, absteigend sortiert.
	 * @param ?string              $lehrer     Ausgewählte Lehrkraft, null = offen.
	 */
	public function __construct(
		public readonly string $klasse,
		public readonly string $fach,
		public readonly array $kandidaten,
		public readonly ?string $lehrer,
		public readonly string $grund,
	) {}

	public function ist_offen(): bool {
		return null === $this->lehrer;
	}
}
