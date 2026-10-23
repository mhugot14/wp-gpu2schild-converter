<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;
use Mh\UntisSchild\Model\Konflikt;
use Mh\UntisSchild\Model\Pipeline_Ergebnis;
use Mh\UntisSchild\Model\Protokoll_Eintrag as P;
use Mh\UntisSchild\Model\Unterricht_Zeile;

/**
 * Berechnet aus den GPU-Rohzeilen und den Regeln die Zeilen für Schild.
 *
 * Arbeitet ohne Datenbank und ohne WordPress: gleiche Eingaben ergeben immer dasselbe
 * Ergebnis. Die Rohzeilen werden nie verändert – wer eine Regel ändert, rechnet einfach neu.
 *
 * Schritte (Reihenfolge ist fachlich begründet):
 * 1. Halbjahr     – im 1. HJ fällt Unterricht weg, der nur im 2. HJ stattfindet.
 * 2. Klassen      – Klassenregeln, die erste passende gilt.
 * 3. Fächer       – Fachregeln auf die (ggf. umbenannte) Klasse, die erste passende gilt.
 *                   Regeln wirken einmal auf die Untis-Fächer, nicht nacheinander.
 * 4. Lehrkräfte   – je Klasse und Fach bleibt eine Lehrkraft (gespeicherte Entscheidung,
 *                   sonst meiste Wochenstunden, bei Gleichstand offen).
 * 5. Dubletten    – erst jetzt, weil Umbenennungen neue Dubletten erzeugen können.
 * 6. Prüfung      – Abgleich mit der Schild-Fächerliste, auffällige Kürzel.
 */
final class Pipeline {

	public const SCHRITTE = [
		P::SCHRITT_HALBJAHR  => 'Halbjahr',
		P::SCHRITT_KLASSEN   => 'Klassenregeln',
		P::SCHRITT_FAECHER   => 'Fachregeln',
		P::SCHRITT_KONFLIKTE => 'Lehrkräfte',
		P::SCHRITT_DUBLETTEN => 'Dubletten',
		P::SCHRITT_PRUEFUNG  => 'Prüfung',
	];

	/** @var P[] */
	private array $protokoll = [];
	/** @var Konflikt[] */
	private array $konflikte = [];
	/** @var array<string, int> */
	private array $treffer = [];
	/** @var array<string, string[]> */
	private array $unbekannte = [];

	/**
	 * Schlüssel für gespeicherte Lehrkraft-Entscheidungen.
	 */
	public static function schluessel( string $klasse, string $fach ): string {
		return mb_strtolower( trim( $klasse ) ) . '|' . mb_strtolower( trim( $fach ) );
	}

	/**
	 * @param Unterricht_Zeile[]     $zeilen          Rohzeilen aus der GPU002.
	 * @param int                    $halbjahr        1 oder 2 – für welches Halbjahr importiert wird.
	 * @param Klassen_Regel[]        $klassen_regeln
	 * @param Fach_Regel[]           $fach_regeln
	 * @param array<string, string>  $entscheidungen  schluessel(klasse, fach) => Lehrkraft.
	 * @param ?string[]              $schild_faecher  Interne Kurzformen aus Schild; null = kein Abgleich.
	 */
	public function ausfuehren(
		array $zeilen,
		int $halbjahr,
		array $klassen_regeln,
		array $fach_regeln,
		array $entscheidungen = [],
		?array $schild_faecher = null,
	): Pipeline_Ergebnis {
		$this->protokoll  = [];
		$this->konflikte  = [];
		$this->treffer    = [];
		$this->unbekannte = [];

		$klassen_regeln = self::aktiv_sortiert( $klassen_regeln );
		$fach_regeln    = self::aktiv_sortiert( $fach_regeln );

		$schritte = [
			P::SCHRITT_HALBJAHR  => fn( array $z ) => $this->halbjahr( $z, $halbjahr ),
			P::SCHRITT_KLASSEN   => fn( array $z ) => $this->klassenregeln( $z, $klassen_regeln ),
			P::SCHRITT_FAECHER   => fn( array $z ) => $this->fachregeln( $z, $fach_regeln ),
			P::SCHRITT_KONFLIKTE => fn( array $z ) => $this->lehrkraefte( $z, $halbjahr, $entscheidungen ),
			P::SCHRITT_DUBLETTEN => fn( array $z ) => $this->dubletten( $z ),
			P::SCHRITT_PRUEFUNG  => fn( array $z ) => $this->pruefen( $z, $schild_faecher ),
		];

		$statistik = [];
		foreach ( $schritte as $schritt => $ausfuehren ) {
			$vorher                 = count( $zeilen );
			$zeilen                 = $ausfuehren( $zeilen );
			$statistik[ $schritt ]  = [ 'vorher' => $vorher, 'nachher' => count( $zeilen ) ];
		}

		usort( $zeilen, static fn( Unterricht_Zeile $a, Unterricht_Zeile $b ) =>
			[ mb_strtolower( $a->klasse ), mb_strtolower( $a->fach ), $a->lehrer ]
			<=> [ mb_strtolower( $b->klasse ), mb_strtolower( $b->fach ), $b->lehrer ]
		);
		ksort( $this->unbekannte, SORT_NATURAL | SORT_FLAG_CASE );

		return new Pipeline_Ergebnis( $zeilen, $this->protokoll, $this->konflikte, $this->unbekannte, $statistik, $this->treffer, null !== $schild_faecher );
	}

	// -------------------------------------------------------------------------
	// 1. Halbjahr
	// -------------------------------------------------------------------------

	/**
	 * Im 1. Halbjahr entfällt Unterricht, der nur im 2. HJ stattfindet. Im 2. Halbjahr bleibt
	 * alles (auch reine 1.-HJ-Fächer), so wie in der Altversion.
	 */
	private function halbjahr( array $zeilen, int $halbjahr ): array {
		if ( 1 !== $halbjahr ) {
			return $zeilen;
		}
		$behalten = [];
		$entfernt = [];
		foreach ( $zeilen as $z ) {
			if ( Unterricht_Zeile::GILT_2 === $z->gilt ) {
				$entfernt[ $z->klasse ][] = $z;
			} else {
				$behalten[] = $z;
			}
		}
		foreach ( $entfernt as $klasse => $gruppe ) {
			$this->protokolliere( P::SCHRITT_HALBJAHR, P::AKTION_ENTFERNT,
				sprintf( '%s: %s nur im 2. Halbjahr – im 1. Halbjahr nicht übernommen', $klasse, self::liste( $gruppe, 'fach' ) ),
				$gruppe, [], null, (string) $klasse );
		}
		return $behalten;
	}

	// -------------------------------------------------------------------------
	// 2. Klassenregeln
	// -------------------------------------------------------------------------

	/**
	 * @param Klassen_Regel[] $regeln
	 */
	private function klassenregeln( array $zeilen, array $regeln ): array {
		$ergebnis = [];
		$gruppen  = [];
		foreach ( $zeilen as $z ) {
			$regel = null;
			foreach ( $regeln as $r ) {
				if ( Muster::passt( $r->muster, $z->klasse ) ) {
					$regel = $r;
					break;
				}
			}
			if ( null === $regel ) {
				$ergebnis[] = $z;
				continue;
			}

			$this->zaehle( $regel->kennung() );
			$key                       = $regel->id . "\0" . $z->klasse;
			$gruppen[ $key ]['regel']  = $regel;
			$gruppen[ $key ]['klasse'] = $z->klasse;
			$gruppen[ $key ]['vorher'][] = $z;
			if ( $regel->ist_loeschung() ) {
				continue;
			}
			$neu                          = $z->mit( [ 'klasse' => $regel->ziel ] );
			$gruppen[ $key ]['nachher'][] = $neu;
			$ergebnis[]                   = $neu;
		}

		foreach ( $gruppen as $g ) {
			$r = $g['regel'];
			$this->protokolliere(
				P::SCHRITT_KLASSEN,
				$r->ist_loeschung() ? P::AKTION_ENTFERNT : P::AKTION_GEAENDERT,
				$r->ist_loeschung()
					? sprintf( 'Klasse %s entfernt', $g['klasse'] )
					: sprintf( 'Klasse %s → %s', $g['klasse'], $r->ziel ),
				$g['vorher'], $g['nachher'] ?? [], $r->kennung(), $g['klasse']
			);
		}
		return $ergebnis;
	}

	// -------------------------------------------------------------------------
	// 3. Fachregeln
	// -------------------------------------------------------------------------

	/**
	 * Je Regel und Klasse werden die betroffenen Zeilen nach Lehrkraft gruppiert. Für jedes
	 * Schild-Fach entsteht je Lehrkraft eine Zeile – so geht keine Lehrkraft verloren (in der
	 * Altversion bekam beim Aufteilen nur die erste Zeile alle Schild-Fächer).
	 *
	 * @param Fach_Regel[] $regeln
	 */
	private function fachregeln( array $zeilen, array $regeln ): array {
		$ergebnis    = [];
		$zuordnung   = [];
		$regel_by_id = [];
		$mehrdeutig  = [];

		foreach ( $zeilen as $z ) {
			$passend = array_values( array_filter( $regeln, static fn( Fach_Regel $r ) => $r->passt( $z ) ) );
			if ( [] === $passend ) {
				$ergebnis[] = $z;
				continue;
			}
			$r = $passend[0];
			if ( count( $passend ) > 1 ) {
				$key                         = $z->klasse . "\0" . $z->fach;
				$mehrdeutig[ $key ]['regeln'] = array_map( static fn( Fach_Regel $x ) => $x->kennung(), $passend );
				$mehrdeutig[ $key ]['zeilen'][] = $z;
			}
			$this->zaehle( $r->kennung() );
			$regel_by_id[ $r->id ] = $r;
			$zuordnung[ $r->id ][ $z->klasse ][ $z->lehrer . "\0" . $z->gilt ][] = $z;
		}

		foreach ( $zuordnung as $id => $klassen ) {
			$r = $regel_by_id[ $id ];
			foreach ( $klassen as $klasse => $teilgruppen ) {
				$vorher  = [];
				$nachher = [];
				foreach ( $teilgruppen as $gruppe ) {
					array_push( $vorher, ...$gruppe );
					// Bei Aufteilung ist unbekannt, wie sich die Stunden verteilen.
					$stunden = 1 === count( $r->schild_faecher );
					foreach ( $r->schild_faecher as $fach ) {
						$neu        = $this->zusammenfassen( $gruppe, [ 'fach' => $fach ], $stunden );
						$nachher[]  = $neu;
						$ergebnis[] = $neu;
					}
				}
				$this->protokolliere(
					P::SCHRITT_FAECHER,
					[] === $nachher ? P::AKTION_ENTFERNT : P::AKTION_GEAENDERT,
					self::fach_text( $r, (string) $klasse, $vorher ),
					$vorher, $nachher, $r->kennung(), (string) $klasse, self::liste( $vorher, 'fach' )
				);
			}
		}

		foreach ( $mehrdeutig as $m ) {
			$z = $m['zeilen'][0];
			$this->protokolliere( P::SCHRITT_FAECHER, P::AKTION_HINWEIS,
				sprintf( '%s · %s passt auf mehrere Regeln (%s) – angewendet wurde nur %s',
					$z->klasse, $z->fach, implode( ', ', $m['regeln'] ), $m['regeln'][0] ),
				$m['zeilen'], [], $m['regeln'][0], $z->klasse, $z->fach );
		}
		return $ergebnis;
	}

	private static function fach_text( Fach_Regel $r, string $klasse, array $vorher ): string {
		$untis = self::liste( $vorher, 'fach' );
		return match ( $r->art() ) {
			Fach_Regel::ART_LOESCHEN => sprintf( '%s: %s entfernt', $klasse, $untis ),
			Fach_Regel::ART_AUFTEILEN => sprintf( '%s: %s aufgeteilt in %s', $klasse, $untis, implode( ', ', $r->schild_faecher ) ),
			default => sprintf( '%s: %s → %s', $klasse, $untis, $r->schild_faecher[0] ),
		};
	}

	// -------------------------------------------------------------------------
	// 4. Lehrkräfte
	// -------------------------------------------------------------------------

	/**
	 * @param array<string, string> $entscheidungen
	 */
	private function lehrkraefte( array $zeilen, int $halbjahr, array $entscheidungen ): array {
		$gruppen = [];
		foreach ( $zeilen as $z ) {
			$gruppen[ self::schluessel( $z->klasse, $z->fach ) ][] = $z;
		}

		$ergebnis = [];
		foreach ( $gruppen as $key => $gruppe ) {
			if ( 2 === $halbjahr ) {
				$gruppe = $this->halbjahreswechsel( $gruppe );
			}
			$gruppe = $this->ohne_leere_lehrkraft( $gruppe );

			$gewichte = [];
			foreach ( $gruppe as $z ) {
				$gewichte[ $z->lehrer ][] = $z;
			}
			if ( count( $gewichte ) < 2 ) {
				array_push( $ergebnis, ...$gruppe );
				continue;
			}
			// Array-Schlüssel können zu int werden (Kürzel "123"), daher zurück zu string.
			$gewichte = array_combine(
				array_map( 'strval', array_keys( $gewichte ) ),
				array_map( fn( array $zz ) => $this->summe( $zz, 'gewicht' ) ?? 0.0, $gewichte )
			);
			arsort( $gewichte );

			$erste       = $gruppe[0];
			$gespeichert = $entscheidungen[ $key ] ?? null;
			if ( null !== $gespeichert && array_key_exists( $gespeichert, $gewichte ) ) {
				$wahl  = $gespeichert;
				$grund = Konflikt::GRUND_ENTSCHEIDUNG;
			} else {
				if ( null !== $gespeichert ) {
					$this->protokolliere( P::SCHRITT_KONFLIKTE, P::AKTION_HINWEIS,
						sprintf( '%s · %s: gespeicherte Auswahl %s unterrichtet das Fach nicht mehr – neu entschieden', $erste->klasse, $erste->fach, $gespeichert ),
						[], [], null, $erste->klasse, $erste->fach );
				}
				$werte = array_values( $gewichte );
				$wahl  = $werte[0] > $werte[1] ? (string) array_key_first( $gewichte ) : null;
				$grund = null === $wahl ? Konflikt::GRUND_OFFEN : Konflikt::GRUND_WOCHENSTUNDEN;
			}

			$this->konflikte[] = new Konflikt( $erste->klasse, $erste->fach, $gewichte, $wahl, $grund );

			if ( null === $wahl ) {
				array_push( $ergebnis, ...$gruppe );
				$this->protokolliere( P::SCHRITT_KONFLIKTE, P::AKTION_OFFEN,
					sprintf( '%s · %s: %s – gleich viele Stunden, bitte auswählen', $erste->klasse, $erste->fach, self::kandidaten_text( $gewichte ) ),
					$gruppe, [], null, $erste->klasse, $erste->fach );
				continue;
			}

			$behalten = array_values( array_filter( $gruppe, static fn( Unterricht_Zeile $z ) => $z->lehrer === $wahl ) );
			array_push( $ergebnis, ...$behalten );
			$this->protokolliere( P::SCHRITT_KONFLIKTE, P::AKTION_ENTSCHIEDEN,
				sprintf( '%s · %s: %s übernommen (%s; %s)', $erste->klasse, $erste->fach, $wahl,
					Konflikt::GRUND_ENTSCHEIDUNG === $grund ? 'gespeicherte Auswahl' : 'meiste Wochenstunden',
					self::kandidaten_text( $gewichte ) ),
				$gruppe, $behalten, null, $erste->klasse, $erste->fach );
		}
		return $ergebnis;
	}

	/**
	 * Im 2. Halbjahr: Gibt es für Klasse+Fach Unterricht, der im 2. HJ stattfindet, fällt
	 * Unterricht weg, der nur im 1. HJ stattfand. Sonst würde z. B. ein Lehrkraftwechsel zum
	 * Halbjahr als Konflikt erscheinen und die Stunden würden doppelt gezählt.
	 */
	private function halbjahreswechsel( array $gruppe ): array {
		$aktuell = array_values( array_filter( $gruppe, static fn( Unterricht_Zeile $z ) => Unterricht_Zeile::GILT_1 !== $z->gilt ) );
		if ( [] === $aktuell || count( $aktuell ) === count( $gruppe ) ) {
			return $gruppe;
		}
		$alt = array_values( array_filter( $gruppe, static fn( Unterricht_Zeile $z ) => Unterricht_Zeile::GILT_1 === $z->gilt ) );
		$z   = $gruppe[0];
		$this->protokolliere( P::SCHRITT_KONFLIKTE, P::AKTION_ENTFERNT,
			sprintf( '%s · %s: Unterricht nur im 1. Halbjahr (%s) nicht übernommen – im 2. Halbjahr unterrichtet %s',
				$z->klasse, $z->fach, self::liste( $alt, 'lehrer' ), self::liste( $aktuell, 'lehrer' ) ),
			$alt, [], null, $z->klasse, $z->fach );
		return $aktuell;
	}

	/**
	 * Zeilen ohne Lehrkraft fallen weg, wenn es für Klasse+Fach eine Lehrkraft gibt.
	 */
	private function ohne_leere_lehrkraft( array $gruppe ): array {
		$mit = array_values( array_filter( $gruppe, static fn( Unterricht_Zeile $z ) => '' !== $z->lehrer ) );
		if ( [] === $mit || count( $mit ) === count( $gruppe ) ) {
			return $gruppe;
		}
		$z = $gruppe[0];
		$this->protokolliere( P::SCHRITT_KONFLIKTE, P::AKTION_ENTFERNT,
			sprintf( '%s · %s: Zeile ohne Lehrkraft entfernt, da %s eingetragen ist', $z->klasse, $z->fach, self::liste( $mit, 'lehrer' ) ),
			array_values( array_filter( $gruppe, static fn( Unterricht_Zeile $x ) => '' === $x->lehrer ) ), [], null, $z->klasse, $z->fach );
		return $mit;
	}

	/**
	 * @param array<string, float> $gewichte
	 */
	private static function kandidaten_text( array $gewichte ): string {
		$teile = [];
		foreach ( $gewichte as $lehrer => $std ) {
			$teile[] = sprintf( '%s %s Std.', $lehrer, self::zahl( $std ) );
		}
		return implode( ', ', $teile );
	}

	// -------------------------------------------------------------------------
	// 5. Dubletten
	// -------------------------------------------------------------------------

	/**
	 * Gleiche Klasse, gleiches Fach, gleiche Lehrkraft → eine Zeile. Die Stunden werden je
	 * Unterrichtsnummer einmal gezählt.
	 */
	private function dubletten( array $zeilen ): array {
		$gruppen = [];
		foreach ( $zeilen as $z ) {
			$gruppen[ self::schluessel( $z->klasse, $z->fach ) . '|' . $z->lehrer ][] = $z;
		}
		$ergebnis = [];
		foreach ( $gruppen as $gruppe ) {
			if ( 1 === count( $gruppe ) ) {
				$ergebnis[] = $gruppe[0];
				continue;
			}
			$neu        = $this->zusammenfassen( $gruppe, [], true );
			$ergebnis[] = $neu;
			$this->protokolliere( P::SCHRITT_DUBLETTEN, P::AKTION_ZUSAMMENGEFASST,
				sprintf( '%s · %s · %s: %d Zeilen zusammengefasst', $neu->klasse, $neu->fach, '' === $neu->lehrer ? 'ohne Lehrkraft' : $neu->lehrer, count( $gruppe ) ),
				$gruppe, [ $neu ], null, $neu->klasse, $neu->fach );
		}
		return $ergebnis;
	}

	// -------------------------------------------------------------------------
	// 6. Prüfung
	// -------------------------------------------------------------------------

	/**
	 * Ändert nichts, sammelt nur Hinweise.
	 *
	 * @param ?string[] $schild_faecher
	 */
	private function pruefen( array $zeilen, ?array $schild_faecher ): array {
		if ( null !== $schild_faecher ) {
			$bekannt = array_fill_keys( array_map( static fn( $f ) => mb_strtolower( trim( (string) $f ) ), $schild_faecher ), true );
			$fehlend = [];
			foreach ( $zeilen as $z ) {
				if ( ! isset( $bekannt[ mb_strtolower( $z->fach ) ] ) ) {
					$fehlend[ $z->fach ][] = $z;
				}
			}
			foreach ( $fehlend as $fach => $gruppe ) {
				$klassen                            = array_values( array_unique( array_map( static fn( $z ) => $z->klasse, $gruppe ) ) );
				$this->unbekannte[ (string) $fach ] = $klassen;
				$this->protokolliere( P::SCHRITT_PRUEFUNG, P::AKTION_HINWEIS,
					sprintf( 'Fach %s fehlt in der Schild-Fächerliste (%d %s)', $fach, count( $klassen ), 1 === count( $klassen ) ? 'Klasse' : 'Klassen' ),
					$gruppe, [], null, '', (string) $fach );
			}
		}

		$auffaellig = [];
		foreach ( $zeilen as $z ) {
			if ( '' === $z->lehrer || 1 !== preg_match( '/^[\p{L}\p{N}]+$/u', $z->lehrer ) ) {
				$auffaellig[ $z->lehrer ][] = $z;
			}
		}
		foreach ( $auffaellig as $lehrer => $gruppe ) {
			$this->protokolliere( P::SCHRITT_PRUEFUNG, P::AKTION_HINWEIS,
				'' === (string) $lehrer
					? sprintf( '%d Zeilen ohne Lehrkraft', count( $gruppe ) )
					: sprintf( 'Ungewöhnliches Lehrkraft-Kürzel „%s“ in %d Zeilen (Platzhalter?)', $lehrer, count( $gruppe ) ),
				$gruppe );
		}
		return $zeilen;
	}

	// -------------------------------------------------------------------------
	// Hilfen
	// -------------------------------------------------------------------------

	/**
	 * Fasst Zeilen zu einer zusammen. Stunden werden je Unterrichtsnummer einmal gezählt
	 * (gleiche U-Nr = derselbe Unterricht); ist ein Wert unbekannt, bleibt die Summe unbekannt.
	 *
	 * @param Unterricht_Zeile[]   $zeilen
	 * @param array<string, mixed> $aenderungen
	 */
	private function zusammenfassen( array $zeilen, array $aenderungen, bool $stunden ): Unterricht_Zeile {
		$gilt   = array_unique( array_map( static fn( $z ) => $z->gilt, $zeilen ) );
		$quelle = array_unique( array_merge( ...array_map( static fn( $z ) => $z->quelle, $zeilen ) ) );
		sort( $quelle );
		$unr = array_unique( array_filter( array_map( static fn( $z ) => $z->unr, $zeilen ), static fn( $u ) => '' !== $u ) );

		return $zeilen[0]->mit( array_merge( [
			'unr'         => implode( '+', $unr ),
			'gilt'        => 1 === count( $gilt ) ? reset( $gilt ) : Unterricht_Zeile::GILT_GANZJAEHRIG,
			'gruppe'      => implode( ', ', array_unique( array_filter( array_map( static fn( $z ) => $z->gruppe, $zeilen ) ) ) ),
			'wstd_klasse' => $stunden ? $this->summe( $zeilen, 'wstd_klasse' ) : null,
			'wstd_lehrer' => $stunden ? $this->summe( $zeilen, 'wstd_lehrer' ) : null,
			'gewicht'     => $this->summe( $zeilen, 'gewicht' ) ?? 0.0,
			'quelle'      => $quelle,
		], $aenderungen ) );
	}

	/**
	 * @param Unterricht_Zeile[] $zeilen
	 */
	private function summe( array $zeilen, string $feld ): ?float {
		$je_unr = [];
		foreach ( $zeilen as $i => $z ) {
			$key = '' === $z->unr ? '#' . $i : $z->unr;
			if ( ! array_key_exists( $key, $je_unr ) ) {
				$je_unr[ $key ] = $z->$feld;
			}
		}
		if ( in_array( null, $je_unr, true ) ) {
			return null;
		}
		return (float) array_sum( $je_unr );
	}

	private function zaehle( string $kennung ): void {
		$this->treffer[ $kennung ] = ( $this->treffer[ $kennung ] ?? 0 ) + 1;
	}

	private function protokolliere( string $schritt, string $aktion, string $text, array $vorher = [], array $nachher = [], ?string $regel = null, string $klasse = '', string $fach = '' ): void {
		$this->protokoll[] = new P( $schritt, $aktion, $text, $vorher, $nachher, $regel, $klasse, $fach );
	}

	/**
	 * "LF1, LF2" aus den Werten eines Feldes.
	 */
	private static function liste( array $zeilen, string $feld ): string {
		$werte = array_unique( array_map( static fn( Unterricht_Zeile $z ) => $z->$feld, $zeilen ) );
		natcasesort( $werte );
		return implode( ', ', $werte );
	}

	public static function zahl( float $wert ): string {
		return rtrim( rtrim( number_format( $wert, 2, ',', '' ), '0' ), ',' );
	}

	/**
	 * @template T of Klassen_Regel|Fach_Regel
	 * @param T[] $regeln
	 * @return T[]
	 */
	private static function aktiv_sortiert( array $regeln ): array {
		$regeln = array_values( array_filter( $regeln, static fn( $r ) => $r->aktiv ) );
		usort( $regeln, static fn( $a, $b ) => [ $a->position, $a->id ] <=> [ $b->position, $b->id ] );
		return $regeln;
	}
}
