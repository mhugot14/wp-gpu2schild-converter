<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Repository;

use wpdb;

/**
 * Gespeicherter WebUntis-Datenstand für den Abgleich: je Untis-Klasse die Stunden je
 * Untis-Fach und Lehrkraft (verdichtet, kein Stundenplan) samt Abrufzeit.
 *
 * Ein fester, datierter Stand statt eines Tageszwischenspeichers: Der Abgleich ist eine
 * Prüfliste, die oft über mehrere Tage abgearbeitet wird. Aktualisiert wird auf Knopfdruck;
 * eine Zeile je Klasse wird dabei ersetzt, nicht angehäuft. Enthält Lehrkraft-Kürzel -
 * gelöscht wie die Importe (Aufbewahrung) und beim Deinstallieren.
 */
class Webuntis_Stand_Repository {

	private string $table;

	public function __construct( private wpdb $db ) {
		$this->table = $db->prefix . 'usc_webuntis_stand';
	}

	/**
	 * @param array<string, array<string, array{ges:float, neu:float}>> $stunden Untis-Fach => Kürzel => Stunden
	 */
	public function speichern( string $klasse, int $wu_id, array $stunden, string $fehler, int $abgerufen ): void {
		$this->db->query( $this->db->prepare(
			"INSERT INTO {$this->table} (klasse, wu_id, stunden, fehler, abgerufen) VALUES (%s, %d, %s, %s, %s)
			 ON DUPLICATE KEY UPDATE wu_id = VALUES(wu_id), stunden = VALUES(stunden), fehler = VALUES(fehler), abgerufen = VALUES(abgerufen)",
			$klasse,
			$wu_id,
			(string) wp_json_encode( $stunden ),
			mb_substr( $fehler, 0, 255 ),
			wp_date( 'Y-m-d H:i:s', $abgerufen )
		) );
	}

	/**
	 * @return array<string, array{wu_id:int, stunden:array, fehler:string, abgerufen:string}> Untis-Klasse => Datensatz
	 */
	public function alle(): array {
		$out = [];
		foreach ( (array) $this->db->get_results( "SELECT klasse, wu_id, stunden, fehler, abgerufen FROM {$this->table} ORDER BY klasse" ) as $r ) {
			$stunden = json_decode( (string) $r->stunden, true );
			$out[ (string) $r->klasse ] = [
				'wu_id'     => (int) $r->wu_id,
				'stunden'   => is_array( $stunden ) ? $stunden : [],
				'fehler'    => (string) $r->fehler,
				'abgerufen' => (string) $r->abgerufen,
			];
		}
		return $out;
	}

	/**
	 * Ältester und neuester Abruf - für "Datenstand vom …".
	 *
	 * @return array{von:?string, bis:?string, anzahl:int}
	 */
	public function stand(): array {
		$r = $this->db->get_row( "SELECT MIN(abgerufen) AS von, MAX(abgerufen) AS bis, COUNT(*) AS anzahl FROM {$this->table}" );
		return [
			'von'    => null !== $r && null !== $r->von ? (string) $r->von : null,
			'bis'    => null !== $r && null !== $r->bis ? (string) $r->bis : null,
			'anzahl' => null !== $r ? (int) $r->anzahl : 0,
		];
	}

	/**
	 * Nach einem vollständigen Aktualisieren: Klassen entfernen, die dabei nicht mehr
	 * vorkamen (in WebUntis aufgelöst oder per Klassenregel nicht mehr übertragen).
	 *
	 * @param string[] $behalten Untis-Klassen dieses Laufs
	 */
	public function entferne_ausser( array $behalten ): int {
		if ( [] === $behalten ) {
			return (int) $this->db->query( "DELETE FROM {$this->table}" );
		}
		$platz = implode( ',', array_fill( 0, count( $behalten ), '%s' ) );
		return (int) $this->db->query( $this->db->prepare( "DELETE FROM {$this->table} WHERE klasse NOT IN ($platz)", ...$behalten ) );
	}

	/** Speicherbegrenzung: Datenstände älter als die Aufbewahrungsfrist. */
	public function loesche_aelter_als( int $tage ): int {
		return (int) $this->db->query( $this->db->prepare(
			"DELETE FROM {$this->table} WHERE abgerufen < %s",
			wp_date( 'Y-m-d H:i:s', time() - $tage * DAY_IN_SECONDS )
		) );
	}
}
