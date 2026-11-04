<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use Mh\UntisSchild\Model\Konflikt;
use Mh\UntisSchild\Model\Pipeline_Ergebnis;
use Mh\UntisSchild\Model\Unterricht_Zeile;

/**
 * Vergleicht das Converter-Ergebnis (GPU002) mit dem tatsächlichen Unterricht laut
 * WebUntis-Stundenplan.
 *
 * Die WebUntis-Stunden (je Untis-Klasse, Untis-Fach und Lehrkraft) laufen durch dieselbe
 * Pipeline mit denselben Klassen- und Fachregeln wie die GPU002 - auch den Löschregeln.
 * Verglichen wird also genau das, was in den Schild-Export geht: Klassen und Fächer, die
 * eine Regel entfernt, fallen auf beiden Seiten weg und erzeugen keinen Fehlalarm.
 * Gespeicherte Lehrkraft-Entscheidungen gelten nur für die GPU-Seite; auf der
 * WebUntis-Seite zählt allein die Mehrheit der gehaltenen Stunden.
 *
 * Wie die Pipeline ohne Datenbank und ohne WordPress.
 */
final class Webuntis_Abgleich {

	public const GLEICH    = 'gleich';
	public const LEHRKRAFT = 'lehrkraft';
	public const FEHLT_GPU = 'fehlt_gpu';
	public const NUR_GPU   = 'nur_gpu';
	public const KONFLIKT  = 'konflikt';

	/** Klasse: Daten liegen vor */
	public const KLASSE_OK = 'ok';
	/** Klasse gibt es in WebUntis, aber es wurden (noch) keine Stunden geladen */
	public const KLASSE_NICHT_GELADEN = 'nicht_geladen';
	/** Klasse steht in der GPU002, keine WebUntis-Klasse führt zu ihr */
	public const KLASSE_NICHT_IN_WEBUNTIS = 'nicht_in_webuntis';
	/** Klasse hat laut WebUntis Unterricht, fehlt aber in der GPU002 */
	public const KLASSE_FEHLT_GPU = 'fehlt_gpu';

	/**
	 * @param Pipeline_Ergebnis $gpu        Ergebnis des Converters für den aktuellen Import.
	 * @param string[]          $wu_klassen Namen aller aktiven WebUntis-Klassen (= Untis-Namen).
	 * @param array<string, array<string, array<string, array{ges:float, neu:float}>>> $stunden
	 *        Geladene Klassen: Untis-Klasse => Untis-Fach => Kürzel => Stunden.
	 * @param int $halbjahr Halbjahr des Imports (für die Pipeline; WebUntis-Zeilen gelten ganzjährig).
	 * @return array{klassen: array<string, array{klasse:string, status:string, untis:string[], faecher:array<string, array>}>, zaehler: array<string,int>}
	 *         Schlüssel der Klassen und Fächer: klein geschrieben (wie Pipeline::schluessel()).
	 */
	public function vergleiche( Pipeline_Ergebnis $gpu, array $wu_klassen, array $stunden, int $halbjahr, array $klassen_regeln, array $fach_regeln ): array {
		$pipeline = new Pipeline();

		// --- Welche Schild-Klasse entsteht aus welcher WebUntis-Klasse? -------
		// Nur die Klassenregeln - ohne Fachregeln, damit eine Fach-Löschung die Probe nicht
		// verschluckt. Entfernt eine Klassenregel die Klasse, gehört sie nicht zum Export.
		$schild_von = [];
		foreach ( array_unique( array_merge( $wu_klassen, array_map( 'strval', array_keys( $stunden ) ) ) ) as $untis ) {
			$probe = $pipeline->ausfuehren( [ new Unterricht_Zeile( (string) $untis, 'X', 'X', 'P' ) ], $halbjahr, $klassen_regeln, [] );
			if ( [] !== $probe->zeilen ) {
				$schild_von[ (string) $untis ] = $probe->zeilen[0]->klasse;
			}
		}

		// --- WebUntis-Seite: einmal über den ganzen Zeitraum, einmal nur zuletzt -----
		$wu_ges = $this->webuntis_seite( $stunden, 'ges', $halbjahr, $klassen_regeln, $fach_regeln );
		$wu_neu = $this->webuntis_seite( $stunden, 'neu', $halbjahr, $klassen_regeln, $fach_regeln );

		// --- GPU-Seite --------------------------------------------------------
		$gpu_faecher = [];
		foreach ( $gpu->zeilen as $z ) {
			$k = mb_strtolower( $z->klasse );
			$f = mb_strtolower( $z->fach );
			$gpu_faecher[ $k ]['klasse']                = $z->klasse;
			$gpu_faecher[ $k ]['faecher'][ $f ]['fach'] = $z->fach;
			if ( '' !== $z->lehrer ) {
				$gpu_faecher[ $k ]['faecher'][ $f ]['lehrer'][ $z->lehrer ] = true;
			}
		}
		$offen = [];
		foreach ( $gpu->konflikte as $kf ) {
			if ( $kf->ist_offen() ) {
				$offen[ Pipeline::schluessel( $kf->klasse, $kf->fach ) ] = array_map( 'strval', array_keys( $kf->kandidaten ) );
			}
		}

		// --- Klassen zusammenführen -------------------------------------------
		$klassen = [];
		foreach ( $gpu_faecher as $k => $g ) {
			$klassen[ $k ] = [ 'klasse' => $g['klasse'], 'status' => self::KLASSE_NICHT_IN_WEBUNTIS, 'untis' => [], 'faecher' => [] ];
		}
		foreach ( $schild_von as $untis => $schild ) {
			$k = mb_strtolower( $schild );
			$geladen = array_key_exists( $untis, $stunden );
			if ( ! isset( $klassen[ $k ] ) ) {
				// Nicht in der GPU002: nur auffällig, wenn WebUntis dort Unterricht kennt.
				if ( ! $geladen || empty( $wu_ges[ $k ] ) ) {
					continue;
				}
				$klassen[ $k ] = [ 'klasse' => $schild, 'status' => self::KLASSE_FEHLT_GPU, 'untis' => [], 'faecher' => [] ];
			}
			$klassen[ $k ]['untis'][] = $untis;
			if ( self::KLASSE_NICHT_IN_WEBUNTIS === $klassen[ $k ]['status'] || self::KLASSE_NICHT_GELADEN === $klassen[ $k ]['status'] ) {
				$klassen[ $k ]['status'] = $geladen ? self::KLASSE_OK : self::KLASSE_NICHT_GELADEN;
			}
		}

		// --- Fächer vergleichen -----------------------------------------------
		$zaehler = array_fill_keys( [ self::GLEICH, self::LEHRKRAFT, self::FEHLT_GPU, self::NUR_GPU, self::KONFLIKT ], 0 );
		foreach ( $klassen as $k => &$kl ) {
			if ( ! in_array( $kl['status'], [ self::KLASSE_OK, self::KLASSE_FEHLT_GPU ], true ) ) {
				continue;
			}
			$g_f = $gpu_faecher[ $k ]['faecher'] ?? [];
			$w_f = $wu_ges[ $k ] ?? [];
			foreach ( array_unique( array_merge( array_keys( $g_f ), array_keys( $w_f ) ) ) as $f ) {
				$f    = (string) $f;
				$g    = $g_f[ $f ] ?? null;
				$w    = $w_f[ $f ] ?? null;
				$gl   = null !== $g ? array_map( 'strval', array_keys( $g['lehrer'] ?? [] ) ) : [];
				$key  = Pipeline::schluessel( $kl['klasse'], (string) ( $g['fach'] ?? $w['fach'] ) );

				if ( null === $w ) {
					$befund = self::NUR_GPU;
				} elseif ( null === $g ) {
					$befund = self::FEHLT_GPU;
				} elseif ( isset( $offen[ $key ] ) ) {
					$befund = self::KONFLIKT;
				} elseif ( null !== $w['wahl'] ) {
					$befund = in_array( $w['wahl'], $gl, true ) ? self::GLEICH : self::LEHRKRAFT;
				} else {
					// Gleichstand in WebUntis: passt, wenn die GPU-Lehrkraft zu den Spitzenreitern gehört.
					$spitze = array_keys( $w['kandidaten'], max( $w['kandidaten'] ), true );
					$befund = [] !== array_intersect( $gl, array_map( 'strval', $spitze ) ) ? self::GLEICH : self::LEHRKRAFT;
				}
				$zaehler[ $befund ]++;

				$kl['faecher'][ $f ] = [
					'fach'           => (string) ( $g['fach'] ?? $w['fach'] ),
					'gpu'            => $gl,
					'gpu_offen'      => $offen[ $key ] ?? null,
					'wu'             => $w['wahl'] ?? null,
					'wu_kandidaten'  => $w['kandidaten'] ?? [],
					'zuletzt'        => $wu_neu[ $k ][ $f ]['wahl'] ?? null,
					'befund'         => $befund,
				];
			}
			ksort( $kl['faecher'], SORT_NATURAL );
		}
		unset( $kl );
		uasort( $klassen, static fn( $a, $b ) => strnatcasecmp( $a['klasse'], $b['klasse'] ) );

		return [ 'klassen' => $klassen, 'zaehler' => $zaehler ];
	}

	/**
	 * Welche WebUntis-Klassen muss ein Aktualisieren laden? Alle, die nicht per
	 * Klassenregel aus dem Export fallen - die übrigen würden nie verglichen und kosten
	 * nur WebUntis-Abrufe.
	 *
	 * @param string[] $wu_klassen
	 * @return string[]
	 */
	public function zu_laden( array $wu_klassen, int $halbjahr, array $klassen_regeln ): array {
		$pipeline = new Pipeline();
		return array_values( array_filter( $wu_klassen, static function ( $untis ) use ( $pipeline, $halbjahr, $klassen_regeln ): bool {
			return [] !== $pipeline->ausfuehren( [ new Unterricht_Zeile( (string) $untis, 'X', 'X', 'P' ) ], $halbjahr, $klassen_regeln, [] )->zeilen;
		} ) );
	}

	/**
	 * WebUntis-Stunden durch die Pipeline: Schild-Klasse => Schild-Fach => Kandidaten und Wahl.
	 *
	 * @return array<string, array<string, array{fach:string, kandidaten:array<string,float>, wahl:?string}>>
	 */
	private function webuntis_seite( array $stunden, string $feld, int $halbjahr, array $klassen_regeln, array $fach_regeln ): array {
		$zeilen = [];
		$nr     = 0;
		foreach ( $stunden as $klasse => $faecher ) {
			foreach ( (array) $faecher as $fach => $lehrer ) {
				foreach ( (array) $lehrer as $kuerzel => $std ) {
					$wert = (float) ( $std[ $feld ] ?? 0 );
					if ( $wert <= 0 || '' === (string) $kuerzel || str_starts_with( (string) $kuerzel, '#' ) ) {
						continue; // keine Stunden, oder Lehrkraft ohne Kürzel in den Stammdaten
					}
					$nr++;
					$zeilen[] = new Unterricht_Zeile(
						klasse: (string) $klasse,
						fach: (string) $fach,
						lehrer: (string) $kuerzel,
						unr: 'W' . $nr,
						gewicht: $wert,
						quelle: [ $nr ],
					);
				}
			}
		}
		if ( [] === $zeilen ) {
			return [];
		}

		$erg = ( new Pipeline() )->ausfuehren( $zeilen, $halbjahr, $klassen_regeln, $fach_regeln );

		$out = [];
		foreach ( $erg->zeilen as $z ) {
			$k = mb_strtolower( $z->klasse );
			$f = mb_strtolower( $z->fach );
			$out[ $k ][ $f ]['fach'] = $z->fach;
			$out[ $k ][ $f ]['kandidaten'][ $z->lehrer ] = ( $out[ $k ][ $f ]['kandidaten'][ $z->lehrer ] ?? 0.0 ) + $z->gewicht;
			$out[ $k ][ $f ]['wahl'] = $z->lehrer;
		}
		// Mehrere Lehrkräfte: Die Pipeline behält nur die mit den meisten Stunden (bzw. alle
		// bei Gleichstand) - die vollständigen Kandidaten stehen im Konflikt.
		foreach ( $erg->konflikte as $kf ) {
			$k = mb_strtolower( $kf->klasse );
			$f = mb_strtolower( $kf->fach );
			if ( isset( $out[ $k ][ $f ] ) ) {
				$out[ $k ][ $f ]['kandidaten'] = array_map( 'floatval', $kf->kandidaten );
				$out[ $k ][ $f ]['wahl']       = $kf->lehrer;
			}
		}
		foreach ( $out as &$faecher ) {
			foreach ( $faecher as &$r ) {
				$r['kandidaten'] = array_combine( array_map( 'strval', array_keys( $r['kandidaten'] ) ), array_values( $r['kandidaten'] ) );
				arsort( $r['kandidaten'] );
			}
		}
		unset( $faecher, $r );

		return $out;
	}
}
