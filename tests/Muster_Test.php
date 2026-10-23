<?php

declare(strict_types=1);

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;
use Mh\UntisSchild\Service\Muster;

final class Muster_Test extends Test_Case {

	public function test_exakt_ohne_gross_klein(): void {
		$this->wahr( Muster::passt( 'AK21A', 'ak21a' ), 'Groß/klein egal' );
		$this->wahr( ! Muster::passt( 'AK21', 'AK21A' ), 'kein Teiltreffer ohne Platzhalter' );
		$this->wahr( Muster::passt( ' AK21A ', 'AK21A' ), 'Leerzeichen am Rand egal' );
	}

	public function test_platzhalter_stern_und_prozent(): void {
		$this->wahr( Muster::passt( 'AK*', 'AK21A' ), 'Stern' );
		$this->wahr( Muster::passt( 'AK%', 'AK21A' ), 'Prozent wie in der Altversion' );
		$this->wahr( Muster::passt( '*1', 'MAKU1' ), 'Stern am Anfang' );
		$this->wahr( ! Muster::passt( 'AK*', 'MAKU1' ), 'kein Treffer' );
	}

	public function test_sonderzeichen_werden_nicht_als_regex_gelesen(): void {
		$this->wahr( ! Muster::passt( 'A.1', 'AB1' ), 'Punkt ist kein Regex-Platzhalter' );
		$this->wahr( Muster::passt( 'A.1', 'A.1' ), 'Punkt wörtlich' );
		$this->wahr( ! Muster::passt( 'A_1', 'AB1' ), 'Unterstrich ist kein Platzhalter (anders als SQL-LIKE)' );
	}

	public function test_liste_und_alle(): void {
		$this->wahr( Muster::passt( 'AK21A, EC*', 'ECKU1' ), 'Liste' );
		$this->wahr( Muster::ist_alle( '' ) && Muster::ist_alle( '%' ) && Muster::ist_alle( ' * ' ), 'alle' );
		$this->wahr( Muster::passt( '', 'X' ), 'leer = alle' );
	}

	public function test_umlaute(): void {
		$this->wahr( Muster::passt( 'dör', 'DÖR' ), 'Umlaut ohne Groß/klein' );
		$this->wahr( Muster::passt( 'Ö*', 'ÖKO' ), 'Umlaut mit Platzhalter' );
	}

	public function test_pruefung(): void {
		$this->gleich( null, Muster::pruefe( 'AK*, EC1' ), 'gültig' );
		$this->wahr( null !== Muster::pruefe( 'AK1,,EC1' ), 'doppeltes Komma' );
		$this->wahr( null !== Muster::pruefe( 'AK1|EC1' ), 'Strich gehört nicht ins Klassenmuster' );
	}

	public function test_klassenregel_validierung(): void {
		$this->gleich( [], ( new Klassen_Regel( 1, 'BERAT' ) )->fehler(), 'Löschen einer Klasse' );
		$this->gleich( [], ( new Klassen_Regel( 1, 'EC1', 'ECKU1' ) )->fehler(), 'Umbenennen' );
		$this->wahr( isset( ( new Klassen_Regel( 1, '%' ) )->fehler()['muster'] ), 'alle Klassen löschen verboten' );
		$this->wahr( isset( ( new Klassen_Regel( 1, 'EC*', 'ECKU1' ) )->fehler()['ziel'] ), 'Umbenennen mit Platzhalter verboten' );
		$this->wahr( isset( ( new Klassen_Regel( 1, 'EC1', 'EC*' ) )->fehler()['ziel'] ), 'Ziel mit Platzhalter verboten' );
	}

	public function test_fachregel_art_und_validierung(): void {
		$this->gleich( Fach_Regel::ART_LOESCHEN, Fach_Regel::aus_text( 1, 'VERT', '' )->art() );
		$this->gleich( Fach_Regel::ART_UMBENENNEN, Fach_Regel::aus_text( 1, 'ER', 'Reli' )->art() );
		$this->gleich( Fach_Regel::ART_ZUSAMMENFASSEN, Fach_Regel::aus_text( 1, 'LF1|LF2', 'LF' )->art() );
		$this->gleich( Fach_Regel::ART_AUFTEILEN, Fach_Regel::aus_text( 1, 'LFU1|LFU2', 'WSP|KUP|KPA' )->art() );
		$this->gleich( [ 'LF1', 'LF2' ], Fach_Regel::faecher_liste( ' LF1 | LF2 || LF1 ' ), 'Liste bereinigt' );
		$this->wahr( isset( Fach_Regel::aus_text( 1, '*', 'X' )->fehler()['untis_faecher'] ), 'alle Fächer verboten' );
		$this->wahr( isset( Fach_Regel::aus_text( 1, 'LF1', 'W*' )->fehler()['schild_faecher'] ), 'Schild-Fach ohne Platzhalter' );
		$this->gleich( [], Fach_Regel::aus_text( 1, 'LFU1|LFU2', 'WSP|KUP|KPA', 'AK%' )->fehler(), 'gültige Regel aus der Altversion' );
	}
}
