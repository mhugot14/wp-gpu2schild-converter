<?php

declare(strict_types=1);

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;
use Mh\UntisSchild\Service\Pipeline;
use Mh\UntisSchild\Service\Webuntis_Abgleich as A;

/**
 * Abgleich GPU002 ↔ WebUntis. Alle Klassen, Fächer und Kürzel sind erfunden.
 */
final class Webuntis_Abgleich_Test extends Test_Case {

	private function abgleich( array $gpu_zeilen, array $stunden, array $wu_klassen = null, array $k = [], array $f = [], array $e = [] ): array {
		$gpu = ( new Pipeline() )->ausfuehren( $gpu_zeilen, 1, $k, $f, $e );
		return ( new A() )->vergleiche( $gpu, $wu_klassen ?? array_keys( $stunden ), $stunden, 1, $k, $f );
	}

	private static function std( float $ges, ?float $neu = null ): array {
		return [ 'ges' => $ges, 'neu' => $neu ?? $ges ];
	}

	private static function befunde( array $erg, string $klasse ): array {
		return array_map( static fn( $f ) => $f['befund'], $erg['klassen'][ mb_strtolower( $klasse ) ]['faecher'] );
	}

	public function test_gleich_abweichend_fehlend_und_nur_gpu(): void {
		$erg = $this->abgleich(
			[ $this->z( 'AK1', 'D', 'AAA' ), $this->z( 'AK1', 'E', 'BBB' ), $this->z( 'AK1', 'M', 'CCC' ) ],
			[ 'AK1' => [
				'D'  => [ 'AAA' => self::std( 8 ) ],
				'E'  => [ 'DDD' => self::std( 6 ), 'BBB' => self::std( 2 ) ],
				'SP' => [ 'EEE' => self::std( 4 ) ],
			] ]
		);
		$this->gleich( [ 'd' => A::GLEICH, 'e' => A::LEHRKRAFT, 'm' => A::NUR_GPU, 'sp' => A::FEHLT_GPU ], self::befunde( $erg, 'AK1' ) );
		$this->gleich( 'DDD', $erg['klassen']['ak1']['faecher']['e']['wu'] );
		$this->gleich( [ 'BBB' ], $erg['klassen']['ak1']['faecher']['e']['gpu'] );
		$this->gleich( 1, $erg['zaehler'][ A::LEHRKRAFT ] );
	}

	public function test_regeln_wirken_auf_beiden_seiten(): void {
		// Untis-Fach "ER" wird zu "RELI", "VR" wird gelöscht, Klasse "AK1x" heißt in Schild "AK1".
		$k = [ new Klassen_Regel( 1, 'AK1x', 'AK1' ), new Klassen_Regel( 2, 'WG*' ) ];
		$f = [ new Fach_Regel( 1, [ 'ER' ], [ 'RELI' ] ), new Fach_Regel( 2, [ 'VR' ] ) ];
		$erg = $this->abgleich(
			[ $this->z( 'AK1x', 'ER', 'AAA' ), $this->z( 'AK1x', 'VR', 'BBB' ) ],
			[
				'AK1x' => [ 'ER' => [ 'AAA' => self::std( 2 ) ], 'VR' => [ 'BBB' => self::std( 2 ) ] ],
				'WG12' => [ 'D' => [ 'CCC' => self::std( 4 ) ] ],
			],
			null, $k, $f
		);
		$this->gleich( [ 'reli' => A::GLEICH ], self::befunde( $erg, 'AK1' ), 'gelöschtes Fach fällt auf beiden Seiten weg' );
		$this->gleich( [ 'AK1x' ], $erg['klassen']['ak1']['untis'] );
		$this->wahr( ! isset( $erg['klassen']['wg12'] ), 'per Klassenregel entfernte Klasse wird nicht verglichen' );
	}

	public function test_offener_konflikt_und_webuntis_gleichstand(): void {
		$erg = $this->abgleich(
			[
				$this->z( 'AK1', 'D', 'AAA', wl: 2.0 ), $this->z( 'AK1', 'D', 'BBB', wl: 2.0 ),
				$this->z( 'AK1', 'S', 'CCC' ),
			],
			[ 'AK1' => [
				'D' => [ 'BBB' => self::std( 6 ), 'AAA' => self::std( 2 ) ],
				'S' => [ 'CCC' => self::std( 2 ), 'DDD' => self::std( 2 ) ],
			] ]
		);
		$d = $erg['klassen']['ak1']['faecher']['d'];
		$this->gleich( A::KONFLIKT, $d['befund'] );
		$this->gleich( [ 'AAA', 'BBB' ], $d['gpu_offen'] );
		$this->gleich( 'BBB', $d['wu'], 'WebUntis-Mehrheit als Hinweis für den Konflikt' );
		$s = $erg['klassen']['ak1']['faecher']['s'];
		$this->gleich( null, $s['wu'] );
		$this->gleich( A::GLEICH, $s['befund'], 'GPU-Lehrkraft gehört zu den gleichauf Liegenden' );
	}

	public function test_zuletzt_anders(): void {
		$erg = $this->abgleich(
			[ $this->z( 'AK1', 'D', 'AAA' ) ],
			[ 'AK1' => [ 'D' => [ 'AAA' => self::std( 10, 0 ), 'BBB' => self::std( 4, 4 ) ] ] ]
		);
		$d = $erg['klassen']['ak1']['faecher']['d'];
		$this->gleich( A::GLEICH, $d['befund'] );
		$this->gleich( 'BBB', $d['zuletzt'] );
	}

	public function test_klassenstatus(): void {
		$erg = $this->abgleich(
			[ $this->z( 'AK1', 'D', 'AAA' ), $this->z( 'AK2', 'D', 'AAA' ), $this->z( 'ALT9', 'D', 'AAA' ) ],
			[ 'AK1' => [ 'D' => [ 'AAA' => self::std( 2 ) ] ], 'NEU1' => [ 'M' => [ 'BBB' => self::std( 4 ) ] ] ],
			[ 'AK1', 'AK2', 'NEU1', 'LEER1' ]
		);
		$st = array_map( static fn( $k ) => $k['status'], $erg['klassen'] );
		$this->gleich( [
			'ak1'  => A::KLASSE_OK,
			'ak2'  => A::KLASSE_NICHT_GELADEN,
			'alt9' => A::KLASSE_NICHT_IN_WEBUNTIS,
			'neu1' => A::KLASSE_FEHLT_GPU,
		], $st );
		$this->gleich( [ 'm' => A::FEHLT_GPU ], self::befunde( $erg, 'NEU1' ) );
	}

	public function test_unbekannte_lehrkraft_wird_ignoriert(): void {
		$erg = $this->abgleich(
			[ $this->z( 'AK1', 'D', 'AAA' ) ],
			[ 'AK1' => [ 'D' => [ 'AAA' => self::std( 2 ), '#123' => self::std( 8 ) ] ] ]
		);
		$this->gleich( 'AAA', $erg['klassen']['ak1']['faecher']['d']['wu'] );
	}
}
