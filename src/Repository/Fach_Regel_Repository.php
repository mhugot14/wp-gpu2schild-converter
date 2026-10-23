<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use Mh\UntisSchild\Model\Fach_Regel;
use wpdb;

/**
 * Fachregeln. Fächerlisten werden als "LF1|LF2" gespeichert.
 */
class Fach_Regel_Repository extends Abstract_Regel_Repository {

	public function __construct( wpdb $db ) {
		parent::__construct( $db, $db->prefix . 'usc_fach_regeln' );
	}

	/**
	 * @return Fach_Regel[]
	 */
	public function all(): array {
		return array_map( [ $this, 'map' ], $this->rows() );
	}

	public function get( int $id ): ?Fach_Regel {
		$row = $this->row( $id );
		return null === $row ? null : $this->map( $row );
	}

	public function save( Fach_Regel $r, int $user_id ): int {
		return $this->write( $r->id, [
			'untis_faecher'  => implode( '|', $r->untis_faecher ),
			'schild_faecher' => implode( '|', $r->schild_faecher ),
			'klassen_muster' => trim( $r->klassen_muster ),
			'bemerkung'      => mb_substr( trim( $r->bemerkung ), 0, 255 ),
			'aktiv'          => $r->aktiv ? 1 : 0,
		], $user_id );
	}

	private function map( object $row ): Fach_Regel {
		return Fach_Regel::aus_text( (int) $row->id, (string) $row->untis_faecher, (string) $row->schild_faecher,
			(string) $row->klassen_muster, (string) $row->bemerkung, (bool) $row->aktiv, (int) $row->position );
	}
}
