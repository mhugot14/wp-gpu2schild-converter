<?php

declare(strict_types=1);

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;
use Mh\UntisSchild\Model\Konflikt;
use Mh\UntisSchild\Model\Protokoll_Eintrag as P;
use Mh\UntisSchild\Service\Pipeline;
use Mh\UntisSchild\Service\Schild_Export;

/**
 * Fachliche Tests der Pipeline. Alle Daten sind erfunden; Lehrkraft-Kürzel sind Fantasiewerte.
 * Tests mit „Altfehler“ im Namen sichern Fehler der Version 0.9.x ab.
 */
final class Pipeline_Test extends Test_Case {

	private function lauf( array $zeilen, int $hj = 1, array $k = [], array $f = [], array $e = [], ?array $schild = null ) {
		return ( new Pipeline() )->ausfuehren( $zeilen, $hj, $k, $f, $e, $schild );
	}

	// --- Halbjahr -------------------------------------------------------------

	public function test_halbjahr_1_entfernt_nur_2hj(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'D', 'AAA', gruppe: '1. HJ' ),
			$this->z( 'AK1', 'E', 'BBB', gruppe: '2. HJ' ),
			$this->z( 'AK1', 'M', 'CCC', gruppe: 'WU' ),
		], 1 );
		$this->gleich( [ 'AK1|D|AAA|2', 'AK1|M|CCC|2' ], $this->kurz( $r->zeilen ) );
		$this->gleich( 1, count( $r->protokoll_fuer( P::SCHRITT_HALBJAHR ) ) );
	}

	public function test_halbjahr_2_behaelt_1hj_faecher(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'D', 'AAA', gruppe: '1. HJ' ),
			$this->z( 'AK1', 'E', 'BBB', gruppe: '2. HJ' ),
		], 2 );
		$this->gleich( [ 'AK1|D|AAA|2', 'AK1|E|BBB|2' ], $this->kurz( $r->zeilen ) );
	}

	public function test_halbjahr_2_lehrkraftwechsel_ist_kein_konflikt(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'D', 'AAA', gruppe: '1. HJ', wl: 4.0 ),
			$this->z( 'AK1', 'D', 'BBB', gruppe: '2. HJ', wl: 2.0 ),
		], 2 );
		$this->gleich( [ 'AK1|D|BBB|2' ], $this->kurz( $r->zeilen ), 'im 2. HJ zählt die aktuelle Lehrkraft' );
		$this->gleich( [], $r->konflikte );
	}

	// --- Klassenregeln --------------------------------------------------------

	public function test_klassen_loeschen_und_umbenennen(): void {
		$r = $this->lauf( [
			$this->z( 'BERAT', 'BER', 'AAA' ),
			$this->z( 'VR1', 'VR', 'BBB' ),
			$this->z( 'EC1', 'D', 'CCC' ),
			$this->z( 'AK1', 'D', 'DDD' ),
		], 1, [
			new Klassen_Regel( 1, 'BERAT' ),
			new Klassen_Regel( 2, 'VR*' ),
			new Klassen_Regel( 3, 'EC1', 'ECKU1' ),
			new Klassen_Regel( 4, 'AK1', aktiv: false ),
		] );
		$this->gleich( [ 'AK1|D|DDD|2', 'ECKU1|D|CCC|2' ], $this->kurz( $r->zeilen ) );
		$this->gleich( 1, $r->treffer_fuer( 'K2' ) );
		$this->gleich( 0, $r->treffer_fuer( 'K4' ), 'inaktive Regel wirkt nicht' );
		$this->gleich( 3, count( $r->protokoll_fuer( P::SCHRITT_KLASSEN ) ) );
	}

	public function test_klassen_erste_passende_regel_nach_position(): void {
		$r = $this->lauf( [ $this->z( 'EC1', 'D', 'AAA' ) ], 1, [
			new Klassen_Regel( 1, 'EC*', position: 20 ),
			new Klassen_Regel( 2, 'EC1', 'ECKU1', position: 10 ),
		] );
		$this->gleich( [ 'ECKU1|D|AAA|2' ], $this->kurz( $r->zeilen ) );
	}

	// --- Fachregeln -----------------------------------------------------------

	public function test_fach_loeschen_mit_klassenmuster(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'VERT', 'AAA' ),
			$this->z( 'EC1', 'VERT', 'AAA' ),
		], 1, [], [ Fach_Regel::aus_text( 1, 'VERT', '', 'AK%' ) ] );
		$this->gleich( [ 'EC1|VERT|AAA|2' ], $this->kurz( $r->zeilen ) );
	}

	public function test_fachregel_wirkt_auf_umbenannte_klasse(): void {
		$r = $this->lauf( [ $this->z( 'EC1', 'ER', 'AAA' ) ], 1,
			[ new Klassen_Regel( 1, 'EC1', 'ECKU1' ) ],
			[ Fach_Regel::aus_text( 1, 'ER', 'Reli', 'ECKU1' ) ] );
		$this->gleich( [ 'ECKU1|Reli|AAA|2' ], $this->kurz( $r->zeilen ) );
	}

	public function test_altfehler_aufteilung_verliert_keine_lehrkraft(): void {
		// LF1 bei AAA (4 Std.), LF2 bei BBB (2 Std.) → WSP, KUP, KPA.
		// Altversion: alle drei Schild-Fächer bekamen die Lehrkraft der ersten Zeile, BBB verschwand still.
		$r = $this->lauf( [
			$this->z( 'AK1', 'LFU1', 'AAA', wk: 4.0, wl: 4.0 ),
			$this->z( 'AK1', 'LFU2', 'BBB', wk: 2.0, wl: 2.0 ),
		], 1, [], [ Fach_Regel::aus_text( 1, 'LFU1|LFU2', 'WSP|KUP|KPA', 'AK%' ) ] );

		$this->gleich( [ 'AK1|KPA|AAA|-', 'AK1|KUP|AAA|-', 'AK1|WSP|AAA|-' ], $this->kurz( $r->zeilen ),
			'Stunden bei Aufteilung unbekannt, AAA hat mehr Stunden' );
		$this->gleich( 3, count( $r->konflikte ), 'je Schild-Fach ein entschiedener Konflikt' );
		$this->gleich( Konflikt::GRUND_WOCHENSTUNDEN, $r->konflikte[0]->grund );
		$this->gleich( [ 'AAA' => 4.0, 'BBB' => 2.0 ], $r->konflikte[0]->kandidaten, 'BBB ist sichtbar' );
	}

	public function test_zusammenfassen_summiert_stunden(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'LF1', 'AAA', wk: 4.0 ),
			$this->z( 'AK1', 'LF2', 'AAA', wk: 2.0 ),
		], 1, [], [ Fach_Regel::aus_text( 1, 'LF1|LF2', 'LF' ) ] );
		$this->gleich( [ 'AK1|LF|AAA|6' ], $this->kurz( $r->zeilen ) );
	}

	public function test_mehrdeutige_fachregeln_hinweis(): void {
		$r = $this->lauf( [ $this->z( 'AK1', 'ER', 'AAA' ) ], 1, [], [
			Fach_Regel::aus_text( 1, 'ER', 'Reli', '%', position: 10 ),
			Fach_Regel::aus_text( 2, 'ER', '', 'AK*', position: 20 ),
		] );
		$this->gleich( [ 'AK1|Reli|AAA|2' ], $this->kurz( $r->zeilen ), 'erste Regel gilt' );
		$hinweise = array_filter( $r->protokoll_fuer( P::SCHRITT_FAECHER ), static fn( $e ) => P::AKTION_HINWEIS === $e->aktion );
		$this->gleich( 1, count( $hinweise ) );
	}

	public function test_fachmuster_mit_platzhalter(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'FÖ1', 'AAA' ),
			$this->z( 'AK1', 'FÖ2', 'AAA' ),
			$this->z( 'AK1', 'D', 'AAA' ),
		], 1, [], [ Fach_Regel::aus_text( 1, 'FÖ*', '' ) ] );
		$this->gleich( [ 'AK1|D|AAA|2' ], $this->kurz( $r->zeilen ) );
	}

	// --- Lehrkräfte -----------------------------------------------------------

	public function test_altfehler_doppelbesetzung_meiste_stunden(): void {
		// Altversion: die alphabetisch hintere Lehrkraft gewann. Hier: ZZZ hat weniger Stunden.
		$r = $this->lauf( [
			$this->z( 'AK1', 'D', 'AAA', wl: 3.0 ),
			$this->z( 'AK1', 'D', 'ZZZ', wl: 1.0 ),
		] );
		$this->gleich( [ 'AK1|D|AAA|2' ], $this->kurz( $r->zeilen ) );
		$this->gleich( false, $r->export_gesperrt() );
	}

	public function test_gleichstand_bleibt_offen_und_sperrt_export(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'D', 'AAA' ),
			$this->z( 'AK1', 'D', 'BBB' ),
		] );
		$this->gleich( 2, count( $r->zeilen ), 'beide Zeilen bleiben sichtbar' );
		$this->gleich( 1, count( $r->offene_konflikte() ) );
		$this->gleich( true, $r->export_gesperrt() );
	}

	public function test_gespeicherte_entscheidung(): void {
		$zeilen = [ $this->z( 'AK1', 'D', 'AAA', wl: 3.0 ), $this->z( 'AK1', 'D', 'BBB', wl: 1.0 ) ];
		$r      = $this->lauf( $zeilen, 1, [], [], [ Pipeline::schluessel( 'ak1', 'd' ) => 'BBB' ] );
		$this->gleich( [ 'AK1|D|BBB|2' ], $this->kurz( $r->zeilen ), 'Entscheidung schlägt Stunden' );
		$this->gleich( Konflikt::GRUND_ENTSCHEIDUNG, $r->konflikte[0]->grund );

		$r = $this->lauf( $zeilen, 1, [], [], [ Pipeline::schluessel( 'AK1', 'D' ) => 'XXX' ] );
		$this->gleich( [ 'AK1|D|AAA|2' ], $this->kurz( $r->zeilen ), 'veraltete Entscheidung wird ignoriert' );
	}

	public function test_zeile_ohne_lehrkraft_verliert(): void {
		$r = $this->lauf( [ $this->z( 'AK1', 'D', '' ), $this->z( 'AK1', 'D', 'AAA' ) ] );
		$this->gleich( [ 'AK1|D|AAA|2' ], $this->kurz( $r->zeilen ) );
		$this->gleich( [], $r->konflikte );
	}

	// --- Dubletten ------------------------------------------------------------

	public function test_altfehler_dubletten_nach_umbenennung(): void {
		// Altversion entfernte Dubletten vor den Regeln – ER und KR → Reli blieben doppelt.
		$r = $this->lauf( [
			$this->z( 'AK1', 'ER', 'AAA', unr: '1', wk: 1.0 ),
			$this->z( 'AK1', 'KR', 'AAA', unr: '2', wk: 1.0 ),
		], 1, [], [ Fach_Regel::aus_text( 1, 'ER', 'Reli' ), Fach_Regel::aus_text( 2, 'KR', 'Reli' ) ] );
		$this->gleich( [ 'AK1|Reli|AAA|2' ], $this->kurz( $r->zeilen ) );
		$this->gleich( 1, count( $r->protokoll_fuer( P::SCHRITT_DUBLETTEN ) ) );
	}

	public function test_gleiche_unterrichtsnummer_zaehlt_einmal(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'D', 'AAA', unr: '7', wk: 2.0 ),
			$this->z( 'AK1', 'D', 'AAA', unr: '7', wk: 2.0 ),
			$this->z( 'AK1', 'D', 'AAA', unr: '8', wk: 1.0 ),
		] );
		$this->gleich( [ 'AK1|D|AAA|3' ], $this->kurz( $r->zeilen ) );
		$this->gleich( '7+8', $r->zeilen[0]->unr );
	}

	public function test_kopplung_bleibt_je_klasse(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'D', 'AAA', unr: '7', wk: 2.0 ),
			$this->z( 'AK2', 'D', 'AAA', unr: '7', wk: 2.0 ),
		] );
		$this->gleich( [ 'AK1|D|AAA|2', 'AK2|D|AAA|2' ], $this->kurz( $r->zeilen ) );
	}

	// --- Prüfung und Export ---------------------------------------------------

	public function test_schild_abgleich(): void {
		$r = $this->lauf( [
			$this->z( 'AK1', 'D', 'AAA' ),
			$this->z( 'AK1', 'XY', 'AAA' ),
			$this->z( 'AK2', 'XY', 'BBB' ),
		], 1, [], [], [], [ 'd', 'E' ] );
		$this->gleich( [ 'XY' => [ 'AK1', 'AK2' ] ], $r->unbekannte_faecher );
		$this->gleich( true, $r->abgleich );

		$ohne = $this->lauf( [ $this->z( 'AK1', 'XY', 'AAA' ) ] );
		$this->gleich( [], $ohne->unbekannte_faecher, 'ohne Fächerliste kein Abgleich' );
		$this->gleich( false, $ohne->abgleich );
	}

	public function test_auffaelliges_kuerzel(): void {
		$r = $this->lauf( [ $this->z( 'AK1', 'D', '???' ) ] );
		$this->gleich( 1, count( $r->protokoll_fuer( P::SCHRITT_PRUEFUNG ) ) );
	}

	public function test_export_format_wie_altversion(): void {
		$r      = $this->lauf( [ $this->z( 'AK1', 'D', 'AAA', wk: 4.0, wl: 2.5 ) ] );
		$zeilen = Schild_Export::zeilen( $r->zeilen, 2026, 1 );
		$this->gleich( 10, count( Schild_Export::KOPFZEILE ) );
		$this->gleich( [ '', 'AK1', 2026, 1, '', 'D', '', 4, 2.5, 'AAA' ], $zeilen[0] );
	}

	public function test_statistik_je_schritt(): void {
		$r = $this->lauf( [ $this->z( 'AK1', 'D', 'AAA' ), $this->z( 'BERAT', 'B', 'AAA' ) ], 1, [ new Klassen_Regel( 1, 'BERAT' ) ] );
		$this->gleich( [ 'vorher' => 2, 'nachher' => 1 ], $r->statistik[ P::SCHRITT_KLASSEN ] );
		$this->gleich( array_keys( Pipeline::SCHRITTE ), array_keys( $r->statistik ) );
	}
}
