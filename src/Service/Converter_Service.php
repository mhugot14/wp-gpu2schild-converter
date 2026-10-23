<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use Mh\UntisSchild\Model\Pipeline_Ergebnis;
use Mh\UntisSchild\Repository\Entscheidung_Repository;
use Mh\UntisSchild\Repository\Fach_Regel_Repository;
use Mh\UntisSchild\Repository\Import_Repository;
use Mh\UntisSchild\Repository\Klassen_Regel_Repository;
use Mh\UntisSchild\Repository\Schild_Fach_Repository;

/**
 * Berechnet das Ergebnis für einen Import aus dem aktuellen Stand von Regeln,
 * Entscheidungen und Schild-Fächerliste.
 *
 * Das Ergebnis wird zwischengespeichert. Jede Änderung an Regeln, Entscheidungen,
 * Fächerliste oder Import ruft geaendert() auf; dadurch passt der Cache-Schlüssel nicht
 * mehr und es wird neu gerechnet.
 */
final class Converter_Service {

	private const OPTION_STAND = 'mh_usc_stand';
	private const CACHE_PREFIX = 'mh_usc_erg_';

	/** @var array<string, Pipeline_Ergebnis> */
	private array $memo = [];

	public function __construct(
		private Import_Repository $imports,
		private Klassen_Regel_Repository $klassen_regeln,
		private Fach_Regel_Repository $fach_regeln,
		private Entscheidung_Repository $entscheidungen,
		private Schild_Fach_Repository $schild_faecher,
		private Pipeline $pipeline,
	) {}

	/**
	 * Nach jeder Änderung: neuer Stand und veraltete Zwischenergebnisse sofort löschen – sie
	 * enthalten Lehrkraft-Kürzel und sollen nicht bis zum Ablauf liegen bleiben (z. B. nach
	 * dem Löschen eines Imports). Importe und Halbjahr werden vor der Änderung erfasst.
	 *
	 * @param object[] $importe_vorher Optional: Importe vor der Änderung (z. B. vor dem Löschen).
	 */
	public function geaendert( array $importe_vorher = [] ): void {
		$alter_stand = (string) get_option( self::OPTION_STAND, '0' );
		foreach ( array_merge( $importe_vorher, $this->imports->all() ) as $import ) {
			foreach ( [ 1, 2 ] as $hj ) {
				delete_transient( self::schluessel( (int) $import->id, $hj, $alter_stand ) );
			}
		}
		$this->memo = [];
		update_option( self::OPTION_STAND, (string) microtime( true ), false );
	}

	private static function schluessel( int $import_id, int $halbjahr, string $stand ): string {
		return self::CACHE_PREFIX . md5( $import_id . '|' . $halbjahr . '|' . $stand );
	}

	public function ergebnis( object $import ): Pipeline_Ergebnis {
		$key = self::schluessel( (int) $import->id, (int) $import->halbjahr, (string) get_option( self::OPTION_STAND, '0' ) );
		if ( isset( $this->memo[ $key ] ) ) {
			return $this->memo[ $key ];
		}

		$cache = get_transient( $key );
		if ( is_string( $cache ) ) {
			$erg = @unserialize( (string) gzuncompress( (string) base64_decode( $cache ) ), [ 'allowed_classes' => true ] );
			if ( $erg instanceof Pipeline_Ergebnis ) {
				return $this->memo[ $key ] = $erg;
			}
		}

		$kurzformen = $this->schild_faecher->kurzformen();
		$erg        = $this->pipeline->ausfuehren(
			$this->imports->zeilen( (int) $import->id ),
			(int) $import->halbjahr,
			$this->klassen_regeln->all(),
			$this->fach_regeln->all(),
			$this->entscheidungen->map(),
			[] === $kurzformen ? null : $kurzformen,
		);
		// Komprimiert, weil das Protokoll mit allen Zeilen sonst mehrere MB groß werden kann.
		set_transient( $key, base64_encode( gzcompress( serialize( $erg ), 6 ) ), HOUR_IN_SECONDS );
		return $this->memo[ $key ] = $erg;
	}

	/**
	 * Ergebnis mit anderen Regeln (ohne Cache) – um vor einer Änderung zu prüfen, ob sie
	 * das Ergebnis verändert.
	 *
	 * @param \Mh\UntisSchild\Model\Klassen_Regel[] $klassen_regeln
	 * @param \Mh\UntisSchild\Model\Fach_Regel[]    $fach_regeln
	 */
	public function ergebnis_mit( object $import, array $klassen_regeln, array $fach_regeln ): Pipeline_Ergebnis {
		$kurzformen = $this->schild_faecher->kurzformen();
		return $this->pipeline->ausfuehren(
			$this->imports->zeilen( (int) $import->id ),
			(int) $import->halbjahr,
			$klassen_regeln,
			$fach_regeln,
			$this->entscheidungen->map(),
			[] === $kurzformen ? null : $kurzformen,
		);
	}

	/**
	 * Gleiche Exportzeilen und gleiche Konflikte?
	 */
	public static function gleiches_ergebnis( Pipeline_Ergebnis $a, Pipeline_Ergebnis $b ): bool {
		$sig = static fn( Pipeline_Ergebnis $e ): string => md5( serialize( [
			array_map( static fn( $z ) => [ $z->klasse, $z->fach, $z->lehrer, $z->wstd_klasse, $z->wstd_lehrer ], $e->zeilen ),
			array_map( static fn( $k ) => [ $k->klasse, $k->fach, $k->lehrer ], $e->konflikte ),
		] ) );
		return $sig( $a ) === $sig( $b );
	}

	public function import_zeilen( object $import ): array {
		return $this->imports->zeilen( (int) $import->id );
	}
}
