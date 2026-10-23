<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;
use Mh\UntisSchild\Model\Regel_Befund as B;
use Mh\UntisSchild\Model\Unterricht_Zeile;

/**
 * Prüft Regeln auf Doppelungen, Widersprüche und Zusammenfassbarkeit.
 *
 * Zwei Sichten:
 * - statisch (ohne Import): Eine Regel ist vollständig in einer früheren enthalten.
 * - mit Daten (aktueller Import): Eine Regel passt auf Zeilen, die aber immer schon eine
 *   frühere Regel erfasst – oder sie passt auf gar nichts.
 *
 * Nur aktive Regeln werden geprüft; es gilt wie in der Pipeline die erste passende Regel.
 * Zusammenfassen schlägt der Prüfer nur vor, der Controller prüft vor dem Speichern mit dem
 * aktuellen Import nach, dass sich das Ergebnis nicht ändert.
 */
final class Regel_Pruefer {

	private const MAX_LAENGE = 255;

	// =========================================================================
	// Fachregeln
	// =========================================================================

	/**
	 * @param Fach_Regel[]       $regeln
	 * @param Unterricht_Zeile[] $zeilen         Rohzeilen des aktuellen Imports (leer = ohne Datenprüfung).
	 * @param Klassen_Regel[]    $klassen_regeln Werden vorher angewendet, wie in der Pipeline.
	 * @param ?string[]          $schild_faecher null = keine Fächerliste vorhanden.
	 * @return B[]
	 */
	public function fachregeln( array $regeln, array $zeilen = [], array $klassen_regeln = [], ?array $schild_faecher = null ): array {
		$aktiv    = self::aktiv_sortiert( $regeln );
		$befunde  = [];
		$markiert = [];

		// 1. Vollständig in einer früheren Regel enthalten.
		foreach ( $aktiv as $j => $r2 ) {
			foreach ( array_slice( $aktiv, 0, $j ) as $r1 ) {
				if ( isset( $markiert[ $r1->id ] ) || ! self::fach_enthalten( $r2, $r1 ) ) {
					continue;
				}
				$gleich               = self::menge( $r2->schild_faecher ) === self::menge( $r1->schild_faecher );
				$befunde[]            = new B( $gleich ? B::DOPPELT : B::WIRKUNGSLOS, [ $r2->id ], $r1->id );
				$markiert[ $r2->id ] = true;
				break;
			}
		}

		// 2. Mit Daten: wirkt nie oder trifft nichts.
		$ohne = [];
		if ( [] !== $zeilen ) {
			$zeilen = self::klassen_anwenden( $zeilen, $klassen_regeln );
			[ $passt, $gewinnt, $verdraengt ] = self::erste_passende( $zeilen, $aktiv, static fn( Fach_Regel $r, Unterricht_Zeile $z ) => $r->passt( $z ) );
			foreach ( $aktiv as $r ) {
				if ( isset( $markiert[ $r->id ] ) ) {
					continue;
				}
				if ( 0 === ( $passt[ $r->id ] ?? 0 ) ) {
					$ohne[] = $r->id;
				} elseif ( 0 === ( $gewinnt[ $r->id ] ?? 0 ) ) {
					$von = $verdraengt[ $r->id ];
					arsort( $von );
					$befunde[]           = new B( B::WIRKUNGSLOS, [ $r->id ], (int) array_key_first( $von ), null, $passt[ $r->id ] );
					$markiert[ $r->id ] = true;
				}
			}
		}

		// 3. Zusammenfassbar.
		$rest = array_values( array_filter( $aktiv, static fn( Fach_Regel $r ) => ! isset( $markiert[ $r->id ] ) ) );
		array_push( $befunde, ...$this->fach_gruppen( $rest ) );

		// 4. Schild-Fächer, die Schild nicht kennt.
		if ( null !== $schild_faecher && [] !== $schild_faecher ) {
			$bekannt = array_fill_keys( array_map( static fn( $f ) => self::norm( (string) $f ), $schild_faecher ), true );
			foreach ( $aktiv as $r ) {
				$fehlend = array_values( array_filter( $r->schild_faecher, static fn( $f ) => ! isset( $bekannt[ self::norm( $f ) ] ) ) );
				if ( [] !== $fehlend ) {
					$befunde[] = new B( B::SCHILD_UNBEKANNT, [ $r->id ], null, null, 0, $fehlend );
				}
			}
		}

		if ( [] !== $ohne ) {
			$befunde[] = new B( B::OHNE_TREFFER, $ohne );
		}
		return $befunde;
	}

	/**
	 * Gruppe A: gleiche Schild-Fächer und gleiche Klassen → Untis-Fächer vereinen.
	 * Gruppe B: gleiche Untis- und Schild-Fächer, verschiedene Klassen → Klassen vereinen.
	 *
	 * @param Fach_Regel[] $regeln
	 * @return B[]
	 */
	private function fach_gruppen( array $regeln ): array {
		$befunde = [];
		$belegt  = [];

		$gruppen = [];
		foreach ( $regeln as $r ) {
			$gruppen[ implode( '|', self::menge( $r->schild_faecher ) ) . '#' . self::klassen_schluessel( $r->klassen_muster ) ][] = $r;
		}
		foreach ( $gruppen as $gruppe ) {
			if ( count( $gruppe ) < 2 ) {
				continue;
			}
			$neu = self::fach_zusammenfuehren( $gruppe );
			if ( null !== $neu ) {
				$befunde[] = new B( B::ZUSAMMENFASSBAR, array_map( static fn( $r ) => $r->id, $gruppe ), null, $neu );
				foreach ( $gruppe as $r ) {
					$belegt[ $r->id ] = true;
				}
			}
		}

		$gruppen = [];
		foreach ( $regeln as $r ) {
			if ( isset( $belegt[ $r->id ] ) || Muster::ist_alle( $r->klassen_muster ) ) {
				continue;
			}
			$gruppen[ implode( '|', self::menge( $r->untis_faecher ) ) . '#' . implode( '|', self::menge( $r->schild_faecher ) ) ][] = $r;
		}
		foreach ( $gruppen as $gruppe ) {
			if ( count( $gruppe ) < 2 ) {
				continue;
			}
			$neu = self::fach_zusammenfuehren( $gruppe );
			if ( null !== $neu ) {
				$befunde[] = new B( B::ZUSAMMENFASSBAR, array_map( static fn( $r ) => $r->id, $gruppe ), null, $neu );
			}
		}
		return $befunde;
	}

	/**
	 * Eine Regel aus mehreren: Untis-Fächer und Klassen vereint, Bemerkungen aneinandergehängt.
	 * Übernimmt id und Position der obersten Regel. null, wenn das Ergebnis zu lang würde.
	 *
	 * @param Fach_Regel[] $gruppe Gleiche Schild-Fächer.
	 */
	public static function fach_zusammenfuehren( array $gruppe ): ?Fach_Regel {
		$gruppe = self::aktiv_sortiert( $gruppe );
		$erste  = $gruppe[0];
		$untis  = self::vereinen( array_merge( ...array_map( static fn( $r ) => $r->untis_faecher, $gruppe ) ) );
		$alle   = in_array( true, array_map( static fn( $r ) => Muster::ist_alle( $r->klassen_muster ), $gruppe ), true );
		$klassen = $alle ? '' : implode( ', ', self::vereinen( array_merge( ...array_map( static fn( $r ) => Muster::teile( $r->klassen_muster ), $gruppe ) ) ) );

		$neu = new Fach_Regel( $erste->id, $untis, $erste->schild_faecher, $klassen, self::bemerkungen( $gruppe ), true, $erste->position );
		if ( mb_strlen( implode( '|', $untis ) ) > self::MAX_LAENGE || mb_strlen( $klassen ) > self::MAX_LAENGE || [] !== $neu->fehler() ) {
			return null;
		}
		return $neu;
	}

	private static function fach_enthalten( Fach_Regel $spaeter, Fach_Regel $frueher ): bool {
		return self::alle_abgedeckt( $spaeter->untis_faecher, $frueher->untis_faecher )
			&& self::klassen_abgedeckt( $spaeter->klassen_muster, $frueher->klassen_muster );
	}

	// =========================================================================
	// Klassenregeln
	// =========================================================================

	/**
	 * @param Klassen_Regel[]    $regeln
	 * @param Unterricht_Zeile[] $zeilen Rohzeilen des aktuellen Imports (leer = ohne Datenprüfung).
	 * @return B[]
	 */
	public function klassenregeln( array $regeln, array $zeilen = [] ): array {
		$aktiv    = self::aktiv_sortiert( $regeln );
		$befunde  = [];
		$markiert = [];

		foreach ( $aktiv as $j => $r2 ) {
			foreach ( array_slice( $aktiv, 0, $j ) as $r1 ) {
				if ( isset( $markiert[ $r1->id ] ) || ! self::alle_abgedeckt( Muster::teile( $r2->muster ), Muster::teile( $r1->muster ) ) ) {
					continue;
				}
				$gleich               = self::norm( $r2->ziel ) === self::norm( $r1->ziel );
				$befunde[]            = new B( $gleich ? B::DOPPELT : B::WIRKUNGSLOS, [ $r2->id ], $r1->id );
				$markiert[ $r2->id ] = true;
				break;
			}
		}

		$ohne = [];
		if ( [] !== $zeilen ) {
			[ $passt, $gewinnt, $verdraengt ] = self::erste_passende( $zeilen, $aktiv, static fn( Klassen_Regel $r, Unterricht_Zeile $z ) => Muster::passt( $r->muster, $z->klasse ) );
			foreach ( $aktiv as $r ) {
				if ( isset( $markiert[ $r->id ] ) ) {
					continue;
				}
				if ( 0 === ( $passt[ $r->id ] ?? 0 ) ) {
					$ohne[] = $r->id;
				} elseif ( 0 === ( $gewinnt[ $r->id ] ?? 0 ) ) {
					$von = $verdraengt[ $r->id ];
					arsort( $von );
					$befunde[]           = new B( B::WIRKUNGSLOS, [ $r->id ], (int) array_key_first( $von ), null, $passt[ $r->id ] );
					$markiert[ $r->id ] = true;
				}
			}
		}

		// Lösch-Regeln lassen sich immer zu einer zusammenfassen (Muster als Liste).
		$loeschen = array_values( array_filter( $aktiv, static fn( Klassen_Regel $r ) => $r->ist_loeschung() && ! isset( $markiert[ $r->id ] ) ) );
		if ( count( $loeschen ) > 1 ) {
			$neu = self::klassen_zusammenfuehren( $loeschen );
			if ( null !== $neu ) {
				$befunde[] = new B( B::ZUSAMMENFASSBAR, array_map( static fn( $r ) => $r->id, $loeschen ), null, $neu );
			}
		}

		if ( [] !== $ohne ) {
			$befunde[] = new B( B::OHNE_TREFFER, $ohne );
		}
		return $befunde;
	}

	/**
	 * @param Klassen_Regel[] $gruppe Lösch-Regeln.
	 */
	public static function klassen_zusammenfuehren( array $gruppe ): ?Klassen_Regel {
		$gruppe = self::aktiv_sortiert( $gruppe );
		$erste  = $gruppe[0];
		$muster = implode( ', ', self::vereinen( array_merge( ...array_map( static fn( $r ) => Muster::teile( $r->muster ), $gruppe ) ) ) );
		if ( mb_strlen( $muster ) > self::MAX_LAENGE ) {
			return null;
		}
		return new Klassen_Regel( $erste->id, $muster, '', self::bemerkungen( $gruppe ), true, $erste->position );
	}

	// =========================================================================
	// Hilfen
	// =========================================================================

	/**
	 * Simuliert „erste passende Regel gewinnt“.
	 *
	 * @return array{0: array<int,int>, 1: array<int,int>, 2: array<int, array<int,int>>}
	 *         [ passt je Regel, gewinnt je Regel, verdrängt durch (Regel => [ Gewinner => Anzahl ]) ]
	 */
	private static function erste_passende( array $zeilen, array $regeln, callable $passt_fn ): array {
		$passt      = [];
		$gewinnt    = [];
		$verdraengt = [];
		foreach ( $zeilen as $z ) {
			$sieger = null;
			foreach ( $regeln as $r ) {
				if ( ! $passt_fn( $r, $z ) ) {
					continue;
				}
				$passt[ $r->id ] = ( $passt[ $r->id ] ?? 0 ) + 1;
				if ( null === $sieger ) {
					$sieger            = $r->id;
					$gewinnt[ $r->id ] = ( $gewinnt[ $r->id ] ?? 0 ) + 1;
				} else {
					$verdraengt[ $r->id ][ $sieger ] = ( $verdraengt[ $r->id ][ $sieger ] ?? 0 ) + 1;
				}
			}
		}
		return [ $passt, $gewinnt, $verdraengt ];
	}

	/**
	 * Klassenregeln auf die Rohzeilen anwenden (wie Schritt 2 der Pipeline).
	 *
	 * @param Klassen_Regel[] $regeln
	 */
	private static function klassen_anwenden( array $zeilen, array $regeln ): array {
		$regeln = self::aktiv_sortiert( $regeln );
		$out    = [];
		foreach ( $zeilen as $z ) {
			foreach ( $regeln as $r ) {
				if ( Muster::passt( $r->muster, $z->klasse ) ) {
					if ( ! $r->ist_loeschung() ) {
						$out[] = $z->mit( [ 'klasse' => $r->ziel ] );
					}
					continue 2;
				}
			}
			$out[] = $z;
		}
		return $out;
	}

	/**
	 * Ist jedes Muster aus $spaeter durch eines aus $frueher abgedeckt? Abgedeckt heißt:
	 * gleich, oder das frühere Muster passt auf den (platzhalterfreien) späteren Namen.
	 *
	 * @param string[] $spaeter
	 * @param string[] $frueher
	 */
	private static function alle_abgedeckt( array $spaeter, array $frueher ): bool {
		if ( [] === $spaeter ) {
			return false;
		}
		foreach ( $spaeter as $s ) {
			$gedeckt = false;
			foreach ( $frueher as $f ) {
				if ( self::norm( $s ) === self::norm( $f ) || ( ! Muster::hat_platzhalter( $s ) && Muster::passt( $f, $s ) ) ) {
					$gedeckt = true;
					break;
				}
			}
			if ( ! $gedeckt ) {
				return false;
			}
		}
		return true;
	}

	private static function klassen_abgedeckt( string $spaeter, string $frueher ): bool {
		if ( Muster::ist_alle( $frueher ) ) {
			return true;
		}
		if ( Muster::ist_alle( $spaeter ) ) {
			return false;
		}
		return self::alle_abgedeckt( Muster::teile( $spaeter ), Muster::teile( $frueher ) );
	}

	private static function klassen_schluessel( string $muster ): string {
		return Muster::ist_alle( $muster ) ? '*' : implode( ',', self::menge( Muster::teile( $muster ) ) );
	}

	/**
	 * Normalisierte, sortierte Menge zum Vergleichen.
	 *
	 * @param string[] $werte
	 * @return string[]
	 */
	private static function menge( array $werte ): array {
		$m = array_values( array_unique( array_map( [ self::class, 'norm' ], $werte ) ) );
		sort( $m );
		return $m;
	}

	/**
	 * Vereinigung in der Reihenfolge des ersten Auftretens, ohne Doppelte (Groß/klein egal).
	 *
	 * @param string[] $werte
	 * @return string[]
	 */
	private static function vereinen( array $werte ): array {
		$out = [];
		foreach ( $werte as $w ) {
			$out[ self::norm( $w ) ] ??= trim( $w );
		}
		return array_values( $out );
	}

	private static function bemerkungen( array $gruppe ): string {
		$b = self::vereinen( array_filter( array_map( static fn( $r ) => $r->bemerkung, $gruppe ), static fn( $t ) => '' !== trim( $t ) ) );
		return mb_substr( implode( '; ', $b ), 0, self::MAX_LAENGE );
	}

	private static function norm( string $wert ): string {
		return mb_strtolower( trim( $wert ) );
	}

	/**
	 * @template T of Fach_Regel|Klassen_Regel
	 * @param T[] $regeln
	 * @return T[]
	 */
	private static function aktiv_sortiert( array $regeln ): array {
		$regeln = array_values( array_filter( $regeln, static fn( $r ) => $r->aktiv ) );
		usort( $regeln, static fn( $a, $b ) => [ $a->position, $a->id ] <=> [ $b->position, $b->id ] );
		return $regeln;
	}
}
