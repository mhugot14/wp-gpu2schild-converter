<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use Mh\UntisSchild\Service\Pipeline;
use wpdb;

/**
 * Gemerkte Auswahl der Lehrkraft je Klasse und Fach.
 */
class Entscheidung_Repository {

	private string $table;

	public function __construct( private wpdb $db ) {
		$this->table = $db->prefix . 'usc_lehrkraft_entscheidungen';
	}

	/**
	 * Für die Pipeline: Pipeline::schluessel( klasse, fach ) => Lehrkraft.
	 *
	 * @return array<string, string>
	 */
	public function map(): array {
		$out = [];
		foreach ( $this->all() as $r ) {
			$out[ Pipeline::schluessel( (string) $r->klasse, (string) $r->fach ) ] = (string) $r->lehrer;
		}
		return $out;
	}

	/**
	 * @return object[]
	 */
	public function all(): array {
		return $this->db->get_results( "SELECT * FROM {$this->table} ORDER BY klasse, fach" );
	}

	public function save( string $klasse, string $fach, string $lehrer, int $user_id ): void {
		$this->db->query( $this->db->prepare(
			"INSERT INTO {$this->table} (klasse, fach, lehrer, entschieden_von, entschieden_am) VALUES (%s, %s, %s, %d, %s)
			 ON DUPLICATE KEY UPDATE lehrer = VALUES(lehrer), entschieden_von = VALUES(entschieden_von), entschieden_am = VALUES(entschieden_am)",
			$klasse, $fach, $lehrer, $user_id, current_time( 'mysql' )
		) );
	}

	public function delete( int $id ): void {
		$this->db->delete( $this->table, [ 'id' => $id ] );
	}
}
