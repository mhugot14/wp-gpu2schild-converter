<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use wpdb;

/**
 * Fächerliste aus Schild (Excel-Export „Unterrichtsfächer“).
 */
class Schild_Fach_Repository {

	private string $table;

	public function __construct( private wpdb $db ) {
		$this->table = $db->prefix . 'usc_schildfaecher';
	}

	/**
	 * @return object[]
	 */
	public function all(): array {
		return $this->db->get_results( "SELECT interne_kurzform, asd_kuerzel, bezeichnung FROM {$this->table} ORDER BY interne_kurzform" );
	}

	/**
	 * Für Vorschläge: Kurzform => Bezeichnung.
	 *
	 * @return array<string, string>
	 */
	public function bezeichnungen(): array {
		$out = [];
		foreach ( $this->all() as $f ) {
			$out[ (string) $f->interne_kurzform ] = (string) $f->bezeichnung;
		}
		return $out;
	}

	/**
	 * @return string[]
	 */
	public function kurzformen(): array {
		return $this->db->get_col( "SELECT interne_kurzform FROM {$this->table}" );
	}

	/**
	 * Ersetzt die komplette Liste.
	 *
	 * @param array<int, array{kurzform:string, asd:string, bezeichnung:string}> $faecher
	 */
	public function replace_all( array $faecher ): void {
		$this->db->query( "DELETE FROM {$this->table}" );
		foreach ( $faecher as $f ) {
			$this->db->insert( $this->table, [
				'interne_kurzform' => mb_substr( $f['kurzform'], 0, 20 ),
				'asd_kuerzel'      => mb_substr( $f['asd'], 0, 20 ),
				'bezeichnung'      => mb_substr( $f['bezeichnung'], 0, 150 ),
			] );
		}
	}
}
