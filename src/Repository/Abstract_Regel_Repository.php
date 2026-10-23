<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use wpdb;

/**
 * Gemeinsame Speicherlogik für Klassen- und Fachregeln: Reihenfolge (position),
 * Aktiv-Schalter, Löschen und Änderungsvermerk.
 */
abstract class Abstract_Regel_Repository {

	public function __construct( protected wpdb $db, protected string $table ) {}

	/**
	 * Wer hat zuletzt geändert (für die Anzeige)?
	 *
	 * @return array<int, array{von:int, am:string}>
	 */
	public function aenderungen(): array {
		$out = [];
		foreach ( $this->db->get_results( "SELECT id, geaendert_von, geaendert_am FROM {$this->table}" ) as $r ) {
			$out[ (int) $r->id ] = [ 'von' => (int) $r->geaendert_von, 'am' => (string) $r->geaendert_am ];
		}
		return $out;
	}

	public function count(): int {
		return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	public function delete( int $id ): void {
		$this->db->delete( $this->table, [ 'id' => $id ] );
	}

	public function delete_all(): void {
		$this->db->query( "DELETE FROM {$this->table}" );
	}

	public function set_aktiv( int $id, bool $aktiv, int $user_id ): void {
		$this->db->update( $this->table, [ 'aktiv' => $aktiv ? 1 : 0, 'geaendert_von' => $user_id, 'geaendert_am' => current_time( 'mysql' ) ], [ 'id' => $id ] );
	}

	/**
	 * Tauscht die Regel mit ihrer Nachbarin ober- oder unterhalb.
	 */
	public function verschieben( int $id, bool $nach_oben ): void {
		$this->neu_nummerieren();
		$ids = array_map( 'intval', $this->db->get_col( "SELECT id FROM {$this->table} ORDER BY position, id" ) );
		$i   = array_search( $id, $ids, true );
		if ( false === $i ) {
			return;
		}
		$j = $nach_oben ? $i - 1 : $i + 1;
		if ( $j < 0 || $j >= count( $ids ) ) {
			return;
		}
		$this->db->update( $this->table, [ 'position' => ( $j + 1 ) * 10 ], [ 'id' => $ids[ $i ] ] );
		$this->db->update( $this->table, [ 'position' => ( $i + 1 ) * 10 ], [ 'id' => $ids[ $j ] ] );
	}

	/**
	 * Setzt die Regel direkt vor eine andere (z. B. damit eine Ausnahme vor der allgemeinen Regel greift).
	 */
	public function verschieben_vor( int $id, int $vor_id ): void {
		$ids = array_map( 'intval', $this->db->get_col( "SELECT id FROM {$this->table} ORDER BY position, id" ) );
		if ( ! in_array( $id, $ids, true ) || ! in_array( $vor_id, $ids, true ) || $id === $vor_id ) {
			return;
		}
		$ids = array_values( array_diff( $ids, [ $id ] ) );
		array_splice( $ids, (int) array_search( $vor_id, $ids, true ), 0, [ $id ] );
		foreach ( $ids as $i => $rid ) {
			$this->db->update( $this->table, [ 'position' => ( $i + 1 ) * 10 ], [ 'id' => $rid ] );
		}
	}

	/**
	 * @return object[]
	 */
	protected function rows(): array {
		return $this->db->get_results( "SELECT * FROM {$this->table} ORDER BY position, id" );
	}

	protected function row( int $id ): ?object {
		return $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) ) ?: null;
	}

	/**
	 * Neue Regeln kommen ans Ende.
	 *
	 * @param array<string, mixed> $daten
	 */
	protected function write( int $id, array $daten, int $user_id ): int {
		$daten['geaendert_von'] = $user_id;
		$daten['geaendert_am']  = current_time( 'mysql' );
		if ( $id > 0 ) {
			$this->db->update( $this->table, $daten, [ 'id' => $id ] );
			return $id;
		}
		$daten['position'] = (int) $this->db->get_var( "SELECT COALESCE(MAX(position), 0) FROM {$this->table}" ) + 10;
		$this->db->insert( $this->table, $daten );
		return (int) $this->db->insert_id;
	}

	/** Positionen lückenlos in Zehnerschritten, damit Verschieben eindeutig ist. */
	private function neu_nummerieren(): void {
		$ids = $this->db->get_col( "SELECT id FROM {$this->table} ORDER BY position, id" );
		foreach ( $ids as $i => $id ) {
			$this->db->update( $this->table, [ 'position' => ( $i + 1 ) * 10 ], [ 'id' => (int) $id ] );
		}
	}
}
