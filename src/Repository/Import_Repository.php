<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use Mh\UntisSchild\Model\Gpu_Import;
use Mh\UntisSchild\Model\Unterricht_Zeile;
use wpdb;

/**
 * Importläufe und ihre GPU-Rohzeilen. Rohzeilen werden nie verändert.
 */
class Import_Repository {

	/** Ältere Importe werden beim Hochladen eines neuen entfernt (Datenminimierung). */
	public const BEHALTEN = 3;

	private string $t_importe;
	private string $t_zeilen;

	public function __construct( private wpdb $db ) {
		$this->t_importe = $db->prefix . 'usc_importe';
		$this->t_zeilen  = $db->prefix . 'usc_import_zeilen';
	}

	public function create( Gpu_Import $import, int $schuljahr, int $halbjahr, string $dateiname, int $user_id ): int {
		$this->db->insert( $this->t_importe, [
			'schuljahr'     => $schuljahr,
			'halbjahr'      => $halbjahr,
			'dateiname'     => mb_substr( $dateiname, 0, 255 ),
			'kodierung'     => $import->kodierung,
			'trennzeichen'  => $import->trennzeichen_name(),
			'zeilen'        => count( $import->zeilen ),
			'uebersprungen' => count( $import->uebersprungen ),
			'erstellt_von'  => $user_id,
			'erstellt_am'   => current_time( 'mysql' ),
		] );
		$id = (int) $this->db->insert_id;
		if ( 0 === $id ) {
			throw new \RuntimeException( 'Der Import konnte nicht gespeichert werden: ' . $this->db->last_error );
		}

		// Mehrzeilige INSERTs in Blöcken – 1.500 Einzel-INSERTs wären spürbar langsam.
		foreach ( array_chunk( $import->zeilen, 250 ) as $block ) {
			$werte = [];
			$sql   = [];
			foreach ( $block as $z ) {
				$sql[] = '(%d, %d, %s, %s, %s, %s, %s, ' . ( null === $z->wstd_klasse ? 'NULL' : '%f' ) . ', ' . ( null === $z->wstd_lehrer ? 'NULL' : '%f' ) . ')';
				array_push( $werte, $id, (int) ( $z->quelle[0] ?? 0 ), mb_substr( $z->unr, 0, 20 ), mb_substr( $z->klasse, 0, 40 ),
					mb_substr( $z->lehrer, 0, 40 ), mb_substr( $z->fach, 0, 40 ), mb_substr( $z->gruppe, 0, 40 ) );
				if ( null !== $z->wstd_klasse ) {
					$werte[] = $z->wstd_klasse;
				}
				if ( null !== $z->wstd_lehrer ) {
					$werte[] = $z->wstd_lehrer;
				}
			}
			$this->db->query( $this->db->prepare(
				"INSERT INTO {$this->t_zeilen} (import_id, zeile_nr, unr, klasse, lehrer, fach, gruppe, wstd_klasse, wstd_lehrer) VALUES " . implode( ',', $sql ),
				$werte
			) );
		}

		$this->aufraeumen();
		return $id;
	}

	public function latest(): ?object {
		return $this->db->get_row( "SELECT * FROM {$this->t_importe} ORDER BY id DESC LIMIT 1" ) ?: null;
	}

	public function get( int $id ): ?object {
		return $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->t_importe} WHERE id = %d", $id ) ) ?: null;
	}

	/**
	 * @return object[]
	 */
	public function all(): array {
		return $this->db->get_results( "SELECT * FROM {$this->t_importe} ORDER BY id DESC" );
	}

	/**
	 * @return Unterricht_Zeile[]
	 */
	public function zeilen( int $import_id ): array {
		$rows = $this->db->get_results( $this->db->prepare(
			"SELECT zeile_nr, unr, klasse, lehrer, fach, gruppe, wstd_klasse, wstd_lehrer FROM {$this->t_zeilen} WHERE import_id = %d ORDER BY zeile_nr",
			$import_id
		) );
		return array_map( static function ( $r ) {
			$wl = null === $r->wstd_lehrer ? null : (float) $r->wstd_lehrer;
			return new Unterricht_Zeile(
				klasse: (string) $r->klasse,
				fach: (string) $r->fach,
				lehrer: (string) $r->lehrer,
				unr: (string) $r->unr,
				gruppe: (string) $r->gruppe,
				gilt: Unterricht_Zeile::gilt_aus_gruppe( (string) $r->gruppe ),
				wstd_klasse: null === $r->wstd_klasse ? null : (float) $r->wstd_klasse,
				wstd_lehrer: $wl,
				gewicht: $wl ?? 0.0,
				quelle: [ (int) $r->zeile_nr ],
			);
		}, $rows );
	}

	/**
	 * Werte der Spalte „Gruppe“ mit Anzahl.
	 *
	 * @return array<string, int>
	 */
	public function gruppen( int $import_id ): array {
		$rows = $this->db->get_results( $this->db->prepare(
			"SELECT gruppe, COUNT(*) AS anzahl FROM {$this->t_zeilen} WHERE import_id = %d GROUP BY gruppe ORDER BY anzahl DESC",
			$import_id
		) );
		$out = [];
		foreach ( $rows as $r ) {
			$out[ (string) $r->gruppe ] = (int) $r->anzahl;
		}
		return $out;
	}

	/**
	 * @return string[]
	 */
	public function klassen( int $import_id ): array {
		return $this->distinct( $import_id, 'klasse' );
	}

	/**
	 * @return string[]
	 */
	public function faecher( int $import_id ): array {
		return $this->distinct( $import_id, 'fach' );
	}

	/**
	 * Welche Fächer kommen in welchen Klassen vor (Rohdaten, vor den Regeln)?
	 * Für die Live-Vorschau beim Anlegen von Fachregeln.
	 *
	 * @return array<string, array<string, int>> Fach => [ Klasse => Zeilen ]
	 */
	public function fach_klassen( int $import_id ): array {
		$rows = $this->db->get_results( $this->db->prepare(
			"SELECT fach, klasse, COUNT(*) AS anzahl FROM {$this->t_zeilen} WHERE import_id = %d GROUP BY fach, klasse",
			$import_id
		) );
		$out = [];
		foreach ( $rows as $r ) {
			$out[ (string) $r->fach ][ (string) $r->klasse ] = (int) $r->anzahl;
		}
		return $out;
	}

	/**
	 * @return array<string, int> Klasse => Zeilen (Rohdaten)
	 */
	public function klassen_zeilen( int $import_id ): array {
		$out = [];
		foreach ( $this->fach_klassen( $import_id ) as $klassen ) {
			foreach ( $klassen as $k => $n ) {
				$out[ $k ] = ( $out[ $k ] ?? 0 ) + $n;
			}
		}
		return $out;
	}

	public function update_meta( int $id, int $schuljahr, int $halbjahr ): void {
		$this->db->update( $this->t_importe, [ 'schuljahr' => $schuljahr, 'halbjahr' => $halbjahr ], [ 'id' => $id ] );
	}

	public function delete( int $id ): void {
		$this->db->delete( $this->t_zeilen, [ 'import_id' => $id ] );
		$this->db->delete( $this->t_importe, [ 'id' => $id ] );
	}

	/**
	 * Löscht Importe, die älter als die Aufbewahrungsfrist sind.
	 *
	 * @return int Anzahl gelöschter Importe
	 */
	public function loesche_aelter_als( int $tage ): int {
		$ids = $this->db->get_col( $this->db->prepare(
			"SELECT id FROM {$this->t_importe} WHERE erstellt_am < %s",
			gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $tage * DAY_IN_SECONDS )
		) );
		foreach ( $ids as $id ) {
			$this->delete( (int) $id );
		}
		return count( $ids );
	}

	private function aufraeumen(): void {
		$alt = $this->db->get_col( $this->db->prepare(
			"SELECT id FROM {$this->t_importe} ORDER BY id DESC LIMIT 1000 OFFSET %d",
			self::BEHALTEN
		) );
		foreach ( $alt as $id ) {
			$this->delete( (int) $id );
		}
	}

	/**
	 * @return string[]
	 */
	private function distinct( int $import_id, string $spalte ): array {
		$werte = $this->db->get_col( $this->db->prepare(
			"SELECT DISTINCT {$spalte} FROM {$this->t_zeilen} WHERE import_id = %d",
			$import_id
		) );
		natcasesort( $werte );
		return array_values( $werte );
	}
}
