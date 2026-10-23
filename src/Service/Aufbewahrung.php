<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use Mh\UntisSchild\Repository\Import_Repository;
use Mh\UntisSchild\Repository\Protokoll_Repository;

/**
 * Speicherbegrenzung (Art. 5 Abs. 1 lit. e DSGVO): Ein täglicher Cron löscht Importe nach
 * Ablauf der eingestellten Frist und Protokolleinträge nach einem Jahr.
 *
 * Unabhängig davon werden beim Hochladen nur die neuesten Importe behalten
 * (Import_Repository::BEHALTEN).
 */
final class Aufbewahrung {

	public const CRON_HOOK        = 'mh_usc_aufraeumen';
	public const STANDARD_TAGE    = 365;
	public const MIN_TAGE         = 30;
	public const MAX_TAGE         = 1095;
	public const PROTOKOLL_TAGE   = 365;

	public function __construct(
		private Import_Repository $imports,
		private Protokoll_Repository $protokoll,
	) {}

	public static function planen(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function abbestellen(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Aufbewahrungsfrist für Importe in Tagen (Einstellung, sonst Standard).
	 */
	public static function tage(): int {
		$opt  = get_option( Zugriff::OPTION, [] );
		$tage = is_array( $opt ) ? (int) ( $opt['aufbewahrung_tage'] ?? 0 ) : 0;
		return $tage > 0 ? max( self::MIN_TAGE, min( self::MAX_TAGE, $tage ) ) : self::STANDARD_TAGE;
	}

	public static function tage_speichern( int $tage ): void {
		$opt                      = get_option( Zugriff::OPTION, [] );
		$opt                      = is_array( $opt ) ? $opt : [];
		$opt['aufbewahrung_tage'] = max( self::MIN_TAGE, min( self::MAX_TAGE, $tage ) );
		update_option( Zugriff::OPTION, $opt, false );
	}

	public function ausfuehren(): void {
		$importe   = $this->imports->loesche_aelter_als( self::tage() );
		$eintraege = $this->protokoll->loesche_aelter_als( self::PROTOKOLL_TAGE );
		if ( $importe > 0 ) {
			$this->protokoll->log( 'aufbewahrung', sprintf( '%d Importe nach Fristablauf gelöscht', $importe ), 0 );
		}
		if ( $importe > 0 || $eintraege > 0 ) {
			update_option( 'mh_usc_stand', (string) microtime( true ), false );
		}
	}
}
