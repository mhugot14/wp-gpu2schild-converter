<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Model;

use Mh\UntisSchild\Service\Muster;

/**
 * Fachregel: Untis-Fächer werden in den passenden Klassen
 * - gelöscht        (keine Schild-Fächer),
 * - umbenannt       (ein Untis-Fach → ein Schild-Fach),
 * - zusammengefasst (mehrere Untis-Fächer → ein Schild-Fach) oder
 * - aufgeteilt      (→ mehrere Schild-Fächer).
 */
final class Fach_Regel {

	public const ART_LOESCHEN        = 'loeschen';
	public const ART_UMBENENNEN      = 'umbenennen';
	public const ART_ZUSAMMENFASSEN  = 'zusammenfassen';
	public const ART_AUFTEILEN       = 'aufteilen';

	/**
	 * @param string[] $untis_faecher  Muster für Untis-Fächer (Platzhalter * oder % erlaubt).
	 * @param string[] $schild_faecher Schild-Fächer; leer = löschen.
	 * @param string   $klassen_muster Muster für Klassen; leer oder * = alle Klassen.
	 */
	public function __construct(
		public readonly int $id,
		public readonly array $untis_faecher,
		public readonly array $schild_faecher = [],
		public readonly string $klassen_muster = '',
		public readonly string $bemerkung = '',
		public readonly bool $aktiv = true,
		public readonly int $position = 0,
	) {}

	/**
	 * Baut eine Regel aus der Textform "LF1|LF2", "WSP|KUP".
	 */
	public static function aus_text( int $id, string $untis, string $schild, string $klassen_muster = '', string $bemerkung = '', bool $aktiv = true, int $position = 0 ): self {
		return new self( $id, self::faecher_liste( $untis ), self::faecher_liste( $schild ), trim( $klassen_muster ), trim( $bemerkung ), $aktiv, $position );
	}

	/**
	 * @return string[]
	 */
	public static function faecher_liste( string $text ): array {
		$teile = array_filter( array_map( 'trim', explode( '|', $text ) ), static fn( $t ) => '' !== $t );
		return array_values( array_unique( $teile ) );
	}

	public function art(): string {
		if ( [] === $this->schild_faecher ) {
			return self::ART_LOESCHEN;
		}
		if ( count( $this->schild_faecher ) > 1 ) {
			return self::ART_AUFTEILEN;
		}
		$mehrere_untis = count( $this->untis_faecher ) > 1 || Muster::hat_platzhalter( implode( '', $this->untis_faecher ) );
		return $mehrere_untis ? self::ART_ZUSAMMENFASSEN : self::ART_UMBENENNEN;
	}

	public function passt( Unterricht_Zeile $zeile ): bool {
		if ( ! Muster::passt( $this->klassen_muster, $zeile->klasse ) ) {
			return false;
		}
		foreach ( $this->untis_faecher as $muster ) {
			if ( Muster::passt( $muster, $zeile->fach ) ) {
				return true;
			}
		}
		return false;
	}

	public function kennung(): string {
		return 'F' . $this->id;
	}

	/**
	 * @return array<string, string> Feld => Fehlermeldung
	 */
	public function fehler(): array {
		$fehler = [];
		if ( [] === $this->untis_faecher ) {
			$fehler['untis_faecher'] = 'Bitte mindestens ein Untis-Fach angeben.';
		} elseif ( in_array( true, array_map( static fn( $f ) => Muster::ist_alle( $f ), $this->untis_faecher ), true ) ) {
			$fehler['untis_faecher'] = 'Ein Untis-Fach aus nur * oder % trifft jedes Fach – das ist sicher nicht gemeint.';
		} elseif ( $m = Muster::pruefe( implode( ',', $this->untis_faecher ) ) ) {
			$fehler['untis_faecher'] = $m;
		}

		foreach ( $this->schild_faecher as $fach ) {
			if ( Muster::hat_platzhalter( $fach ) || str_contains( $fach, ',' ) ) {
				$fehler['schild_faecher'] = 'Schild-Fächer sind feste Kürzel ohne Platzhalter; mehrere mit | trennen.';
				break;
			}
			if ( mb_strlen( $fach ) > 40 ) {
				$fehler['schild_faecher'] = 'Ein Schild-Fach darf höchstens 40 Zeichen lang sein.';
				break;
			}
		}

		if ( '' !== trim( $this->klassen_muster ) && ( $m = Muster::pruefe( $this->klassen_muster ) ) ) {
			$fehler['klassen_muster'] = $m;
		}
		return $fehler;
	}
}
