<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use wpdb;

/**
 * Zugriffsprotokoll (Rechenschaftspflicht, Art. 5 Abs. 2 DSGVO): wer hat wann welche
 * Aktion ausgeführt. Im Detail stehen nur technische Angaben wie Anzahlen – nie Lehrkraft-
 * Kürzel oder andere Inhalte.
 */
class Protokoll_Repository {

	private string $table;

	public function __construct( private wpdb $db ) {
		$this->table = $db->prefix . 'usc_protokoll';
	}

	public function log( string $aktion, string $detail = '', ?int $user_id = null ): void {
		$this->db->insert( $this->table, [
			'user_id' => $user_id ?? get_current_user_id(),
			'aktion'  => mb_substr( $aktion, 0, 40 ),
			'detail'  => mb_substr( $detail, 0, 255 ),
			'zeit'    => current_time( 'mysql' ),
		] );
	}

	/**
	 * @return object[]
	 */
	public function neueste( int $anzahl = 50 ): array {
		return $this->db->get_results( $this->db->prepare(
			"SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d",
			$anzahl
		) );
	}

	public function loesche_aelter_als( int $tage ): int {
		return (int) $this->db->query( $this->db->prepare(
			"DELETE FROM {$this->table} WHERE zeit < %s",
			gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $tage * DAY_IN_SECONDS )
		) );
	}
}
