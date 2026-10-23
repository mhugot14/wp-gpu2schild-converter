<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Service;

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;

/**
 * Regeln als Textblock: zum Massen-Einfügen und zum Kopieren.
 *
 * Eine Regel pro Zeile, Felder mit Semikolon getrennt. Zeilen ohne Semikolon werden mit
 * Komma getrennt gelesen – so passen auch Listen aus der Vorversion. Mit Komma kann das
 * Klassenmuster dann aber keine Liste sein.
 *
 *   Klassen: Untis-Klasse ; Schild-Klasse (leer = löschen) ; Bemerkung
 *   Fächer:  Untis-Fächer ; Schild-Fächer (leer = löschen) ; Klassen ; Bemerkung
 */
final class Regel_Text {

	/**
	 * @return array{regeln: Klassen_Regel[], fehler: string[]}
	 */
	public static function klassen( string $text ): array {
		$regeln = [];
		$fehler = [];
		foreach ( self::zeilen( $text ) as $nr => $f ) {
			$r = new Klassen_Regel( 0, $f[0] ?? '', $f[1] ?? '', $f[2] ?? '' );
			foreach ( $r->fehler() as $meldung ) {
				$fehler[] = sprintf( 'Zeile %d: %s', $nr, $meldung );
			}
			$regeln[] = $r;
		}
		return [ 'regeln' => $regeln, 'fehler' => $fehler ];
	}

	/**
	 * @return array{regeln: Fach_Regel[], fehler: string[]}
	 */
	public static function faecher( string $text ): array {
		$regeln = [];
		$fehler = [];
		foreach ( self::zeilen( $text ) as $nr => $f ) {
			$r = Fach_Regel::aus_text( 0, $f[0] ?? '', $f[1] ?? '', $f[2] ?? '', $f[3] ?? '' );
			foreach ( $r->fehler() as $meldung ) {
				$fehler[] = sprintf( 'Zeile %d: %s', $nr, $meldung );
			}
			$regeln[] = $r;
		}
		return [ 'regeln' => $regeln, 'fehler' => $fehler ];
	}

	/**
	 * @param Klassen_Regel[] $regeln
	 */
	public static function klassen_als_text( array $regeln ): string {
		return implode( "\n", array_map( static fn( Klassen_Regel $r ) => implode( ' ; ', [ $r->muster, $r->ziel, $r->bemerkung ] ), $regeln ) );
	}

	/**
	 * @param Fach_Regel[] $regeln
	 */
	public static function faecher_als_text( array $regeln ): string {
		return implode( "\n", array_map( static fn( Fach_Regel $r ) => implode( ' ; ', [
			implode( '|', $r->untis_faecher ), implode( '|', $r->schild_faecher ), $r->klassen_muster, $r->bemerkung,
		] ), $regeln ) );
	}

	/**
	 * Die Regel als Satz, z. B. „LF1 und LF2 werden in allen Klassen zu LF zusammengefasst.“
	 */
	public static function fach_satz( Fach_Regel $r ): string {
		$was = self::aufzaehlung( $r->untis_faecher, 'Fächer' );
		$wo  = Muster::ist_alle( $r->klassen_muster ) ? 'in allen Klassen' : 'in ' . self::aufzaehlung( Muster::teile( $r->klassen_muster ), 'Klassen' );
		$ziel = self::aufzaehlung( $r->schild_faecher, '' );
		$viele = count( $r->untis_faecher ) > 1 || Muster::hat_platzhalter( implode( '', $r->untis_faecher ) );
		return match ( $r->art() ) {
			Fach_Regel::ART_LOESCHEN       => sprintf( '%s %s %s entfernt.', $was, $viele ? 'werden' : 'wird', $wo ),
			Fach_Regel::ART_UMBENENNEN     => sprintf( '%s heißt %s in Schild %s.', $was, $wo, $ziel ),
			Fach_Regel::ART_ZUSAMMENFASSEN => sprintf( '%s %s %s zu %s zusammengefasst.', $was, $viele ? 'werden' : 'wird', $wo, $ziel ),
			default                        => sprintf( '%s %s %s aufgeteilt in %s.', $was, $viele ? 'werden' : 'wird', $wo, $ziel ),
		};
	}

	public static function klassen_satz( Klassen_Regel $r ): string {
		$teile = Muster::teile( $r->muster );
		$was   = self::aufzaehlung( $teile, 'Klassen' );
		$viele = count( $teile ) > 1 || Muster::hat_platzhalter( $r->muster );
		return $r->ist_loeschung()
			? sprintf( 'Klasse%s %s %s entfernt.', $viele ? 'n' : '', $was, $viele ? 'werden' : 'wird' )
			: sprintf( 'Klasse %s heißt in Schild %s.', $was, $r->ziel );
	}

	/**
	 * „A, B und C“; Platzhalter lesbar: „AK*“ → „alle Klassen mit „AK““.
	 *
	 * @param string[] $muster
	 */
	public static function aufzaehlung( array $muster, string $was ): string {
		$teile = array_map( static function ( string $m ) use ( $was ): string {
			if ( preg_match( '/^([^*%]+)[*%]$/u', $m, $t ) ) {
				return trim( "alle $was mit „{$t[1]}“" );
			}
			return $m;
		}, $muster );
		if ( count( $teile ) < 2 ) {
			return $teile[0] ?? '';
		}
		$letzte = array_pop( $teile );
		return implode( ', ', $teile ) . ' und ' . $letzte;
	}

	/**
	 * @return array<int, string[]> Zeilennummer => Felder
	 */
	private static function zeilen( string $text ): array {
		$out = [];
		foreach ( preg_split( '/\r\n|\n|\r/', $text ) as $i => $zeile ) {
			if ( '' === trim( $zeile ) || str_starts_with( trim( $zeile ), '#' ) ) {
				continue;
			}
			$trenner     = str_contains( $zeile, ';' ) ? ';' : ',';
			$out[ $i + 1 ] = array_map( 'trim', explode( $trenner, $zeile ) );
		}
		return $out;
	}
}
