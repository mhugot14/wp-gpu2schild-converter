<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use Mh\UntisSchild\Model\Klassen_Regel;
use wpdb;

/**
 * Klassenregeln. Gemeinsame Logik (Reihenfolge, Aktiv-Schalter) in Abstract_Regel_Repository.
 */
class Klassen_Regel_Repository extends Abstract_Regel_Repository {

	public function __construct( wpdb $db ) {
		parent::__construct( $db, $db->prefix . 'usc_klassen_regeln' );
	}

	/**
	 * @return Klassen_Regel[]
	 */
	public function all(): array {
		return array_map( [ $this, 'map' ], $this->rows() );
	}

	public function get( int $id ): ?Klassen_Regel {
		$row = $this->row( $id );
		return null === $row ? null : $this->map( $row );
	}

	public function save( Klassen_Regel $r, int $user_id ): int {
		return $this->write( $r->id, [
			'muster'    => trim( $r->muster ),
			'ziel'      => trim( $r->ziel ),
			'bemerkung' => mb_substr( trim( $r->bemerkung ), 0, 255 ),
			'aktiv'     => $r->aktiv ? 1 : 0,
		], $user_id );
	}

	private function map( object $row ): Klassen_Regel {
		return new Klassen_Regel( (int) $row->id, (string) $row->muster, (string) $row->ziel, (string) $row->bemerkung, (bool) $row->aktiv, (int) $row->position );
	}
}
