<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use wpdb;

/**
 * Kennzahlen für die Übersichtsseite im Backend. Liest nur Anzahlen, keine Inhalte.
 */
class Status_Repository {

	public function __construct( private wpdb $db ) {}

	/**
	 * @return array{klassen_regeln:int, klassen_regeln_aktiv:int, fach_regeln:int, fach_regeln_aktiv:int, schild_faecher:int, importe:int, letzter_import:?string}
	 */
	public function counts(): array {
		$p = $this->db->prefix;
		return [
			'klassen_regeln'       => $this->count( "SELECT COUNT(*) FROM {$p}usc_klassen_regeln" ),
			'klassen_regeln_aktiv' => $this->count( "SELECT COUNT(*) FROM {$p}usc_klassen_regeln WHERE aktiv = 1" ),
			'fach_regeln'          => $this->count( "SELECT COUNT(*) FROM {$p}usc_fach_regeln" ),
			'fach_regeln_aktiv'    => $this->count( "SELECT COUNT(*) FROM {$p}usc_fach_regeln WHERE aktiv = 1" ),
			'schild_faecher'       => $this->count( "SELECT COUNT(*) FROM {$p}usc_schildfaecher" ),
			'importe'              => $this->count( "SELECT COUNT(*) FROM {$p}usc_importe" ),
			'letzter_import'       => $this->db->get_var( "SELECT MAX(erstellt_am) FROM {$p}usc_importe" ),
		];
	}

	/**
	 * Seiten und Beiträge (auch Entwürfe und private), in denen der Shortcode steht.
	 *
	 * @return array<int, object{ID:string, post_title:string, post_status:string, post_type:string}>
	 */
	public function pages_with_shortcode( string $shortcode ): array {
		return $this->db->get_results( $this->db->prepare(
			"SELECT ID, post_title, post_status, post_type FROM {$this->db->posts}
			 WHERE post_content LIKE %s
			   AND post_status IN ('publish', 'private', 'draft', 'pending', 'future')
			   AND post_type NOT IN ('revision', 'nav_menu_item')
			 ORDER BY post_title",
			'%' . $this->db->esc_like( '[' . $shortcode ) . '%'
		) );
	}

	private function count( string $sql ): int {
		return (int) $this->db->get_var( $sql );
	}
}
