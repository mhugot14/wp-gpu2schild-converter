<?php

declare(strict_types=1);

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;
use Mh\UntisSchild\Model\Regel_Befund as B;
use Mh\UntisSchild\Service\Pipeline;
use Mh\UntisSchild\Service\Regel_Pruefer;

final class Regel_Pruefer_Test extends Test_Case {

	private function f( int $id, string $untis, string $schild = '', string $klassen = '', int $pos = 0 ): Fach_Regel {
		return Fach_Regel::aus_text( $id, $untis, $schild, $klassen, '', true, $pos ?: $id * 10 );
	}

	/**
	 * @param B[] $befunde
	 * @return array<string, int[]> Typ => betroffene Regel-IDs
	 */
	private function nach_typ( array $befunde ): array {
		$out = [];
		foreach ( $befunde as $b ) {
			$out[ $b->typ ] = array_merge( $out[ $b->typ ] ?? [], $b->regel_ids );
		}
		return $out;
	}

	public function test_doppelte_fachregel(): void {
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'VERT' ), $this->f( 2, 'vert' ) ] );
		$this->gleich( [ 2 ], $this->nach_typ( $b )[ B::DOPPELT ] ?? [] );
		$this->gleich( 1, $b[0]->bezug_id );
	}

	public function test_widerspruch_ist_wirkungslos(): void {
		// Oben: ER in allen Klassen entfernen. Unten: ER in AK1 umbenennen – greift nie.
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'ER' ), $this->f( 2, 'ER', 'Reli', 'AK1' ) ] );
		$this->gleich( [ 2 ], $this->nach_typ( $b )[ B::WIRKUNGSLOS ] ?? [] );
	}

	public function test_spezielle_regel_oben_ist_kein_befund(): void {
		// Richtig herum: erst die Ausnahme (AK1), dann die allgemeine Regel.
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'ER', 'Reli', 'AK1' ), $this->f( 2, 'ER' ) ] );
		$this->gleich( [], array_diff_key( $this->nach_typ( $b ), [ B::ZUSAMMENFASSBAR => 1 ] ) );
	}

	public function test_platzhalter_deckt_einzelnes_fach(): void {
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'FÖ*' ), $this->f( 2, 'FÖ1' ) ] );
		$this->gleich( [ 2 ], $this->nach_typ( $b )[ B::DOPPELT ] ?? [] );
	}

	public function test_zusammenfassbar_gleiche_wirkung(): void {
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'VERT' ), $this->f( 2, 'BER' ), $this->f( 3, 'ER', 'Reli' ), $this->f( 4, 'KR', 'Reli' ) ] );
		$z = array_values( array_filter( $b, static fn( $x ) => B::ZUSAMMENFASSBAR === $x->typ ) );
		$this->gleich( 2, count( $z ) );
		$this->gleich( [ 'VERT', 'BER' ], $z[0]->vorschlag->untis_faecher );
		$this->gleich( [ 'ER', 'KR' ], $z[1]->vorschlag->untis_faecher );
		$this->gleich( [ 'Reli' ], $z[1]->vorschlag->schild_faecher );
		$this->gleich( 3, $z[1]->vorschlag->id, 'übernimmt die oberste Regel' );
	}

	public function test_zusammenfassbar_gleiche_faecher_andere_klassen(): void {
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'LF1', 'WSP', 'AK1' ), $this->f( 2, 'LF1', 'WSP', 'AK2' ) ] );
		$z = array_values( array_filter( $b, static fn( $x ) => B::ZUSAMMENFASSBAR === $x->typ ) );
		$this->gleich( 'AK1, AK2', $z[0]->vorschlag->klassen_muster );
	}

	public function test_zusammenfassen_aendert_ergebnis_nicht(): void {
		$zeilen = [ $this->z( 'AK1', 'VERT', 'AAA' ), $this->z( 'AK1', 'BER', 'BBB' ), $this->z( 'AK1', 'ER', 'CCC', unr: '5' ), $this->z( 'AK1', 'KR', 'CCC', unr: '6' ), $this->z( 'AK1', 'D', 'DDD' ) ];
		$regeln = [ $this->f( 1, 'VERT' ), $this->f( 2, 'BER' ), $this->f( 3, 'ER', 'Reli' ), $this->f( 4, 'KR', 'Reli' ) ];
		$vorher = ( new Pipeline() )->ausfuehren( $zeilen, 1, [], $regeln );

		$neu = [];
		foreach ( ( new Regel_Pruefer() )->fachregeln( $regeln ) as $b ) {
			if ( B::ZUSAMMENFASSBAR === $b->typ ) {
				$neu[] = $b->vorschlag;
			}
		}
		$nachher = ( new Pipeline() )->ausfuehren( $zeilen, 1, [], $neu );
		$this->gleich( 2, count( $neu ) );
		$this->gleich( $this->kurz( $vorher->zeilen ), $this->kurz( $nachher->zeilen ) );
	}

	public function test_mit_daten_wirkungslos_und_ohne_treffer(): void {
		$zeilen = [ $this->z( 'AK1', 'LF1', 'AAA' ), $this->z( 'AK2', 'LF1', 'AAA' ) ];
		// Regel 2 passt auf LF1 in AK*, aber Regel 1 (LF*, alle Klassen) greift immer vorher.
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'LF*', 'LF' ), $this->f( 2, 'LF1', 'WSP', 'AK*' ), $this->f( 3, 'GIBTSNICHT' ) ], $zeilen );
		$t = $this->nach_typ( $b );
		$this->gleich( [ 2 ], $t[ B::WIRKUNGSLOS ] ?? [] );
		$this->gleich( [ 3 ], $t[ B::OHNE_TREFFER ] ?? [] );
	}

	public function test_klassenregeln_wirken_vor_fachregeln(): void {
		// EC1 heißt nach der Klassenregel ECKU1 – die Fachregel für ECKU1 trifft also.
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'ER', 'Reli', 'ECKU1' ) ], [ $this->z( 'EC1', 'ER', 'AAA' ) ], [ new Klassen_Regel( 1, 'EC1', 'ECKU1' ) ] );
		$this->gleich( [], $b );
	}

	public function test_schild_fach_unbekannt(): void {
		$b = ( new Regel_Pruefer() )->fachregeln( [ $this->f( 1, 'ER', 'Relli' ) ], [], [], [ 'Reli', 'D' ] );
		$this->gleich( B::SCHILD_UNBEKANNT, $b[0]->typ );
		$this->gleich( [ 'Relli' ], $b[0]->details );
	}

	public function test_inaktive_regeln_zaehlen_nicht(): void {
		$b = ( new Regel_Pruefer() )->fachregeln( [ Fach_Regel::aus_text( 1, 'VERT', '', '', '', false, 10 ), $this->f( 2, 'VERT' ) ] );
		$this->gleich( [], $b );
	}

	public function test_klassenregeln(): void {
		$p = new Regel_Pruefer();
		$b = $p->klassenregeln( [ new Klassen_Regel( 1, 'VR*', position: 10 ), new Klassen_Regel( 2, 'VR1', position: 20 ), new Klassen_Regel( 3, 'BERAT', position: 30 ) ] );
		$t = $this->nach_typ( $b );
		$this->gleich( [ 2 ], $t[ B::DOPPELT ] ?? [] );
		$this->gleich( [ 1, 3 ], $t[ B::ZUSAMMENFASSBAR ] ?? [] );
		$z = array_values( array_filter( $b, static fn( $x ) => B::ZUSAMMENFASSBAR === $x->typ ) )[0];
		$this->gleich( 'VR*, BERAT', $z->vorschlag->muster );

		$b = $p->klassenregeln( [ new Klassen_Regel( 1, 'EC*', position: 10 ), new Klassen_Regel( 2, 'EC1', 'ECKU1', position: 20 ) ] );
		$this->gleich( [ 2 ], $this->nach_typ( $b )[ B::WIRKUNGSLOS ] ?? [], 'Umbenennen unter einer Löschregel wirkt nie' );
	}
}
