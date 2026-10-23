<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

/**
 * Wer darf den Converter benutzen? Administrator*innen immer, sonst die in den
 * Einstellungen freigegebenen Benutzer.
 */
final class Zugriff {

	public const OPTION = 'mh_usc_settings';

	public static function darf( ?int $user_id = null ): bool {
		$user_id ??= get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		return in_array( $user_id, self::freigegeben(), true );
	}

	/**
	 * @return int[]
	 */
	public static function freigegeben(): array {
		$opt = get_option( self::OPTION, [] );
		return array_values( array_map( 'intval', (array) ( is_array( $opt ) ? ( $opt['user_ids'] ?? [] ) : [] ) ) );
	}

	/**
	 * @param int[] $ids
	 */
	public static function speichern( array $ids ): void {
		$opt             = get_option( self::OPTION, [] );
		$opt             = is_array( $opt ) ? $opt : [];
		$opt['user_ids'] = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		update_option( self::OPTION, $opt, false );
	}
}
