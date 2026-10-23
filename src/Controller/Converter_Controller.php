<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Controller;

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Klassen_Regel;
use Mh\UntisSchild\Model\Konflikt;
use Mh\UntisSchild\Model\Pipeline_Ergebnis;
use Mh\UntisSchild\Model\Protokoll_Eintrag;
use Mh\UntisSchild\Repository\Entscheidung_Repository;
use Mh\UntisSchild\Repository\Fach_Regel_Repository;
use Mh\UntisSchild\Repository\Import_Repository;
use Mh\UntisSchild\Repository\Klassen_Regel_Repository;
use Mh\UntisSchild\Repository\Protokoll_Repository;
use Mh\UntisSchild\Repository\Schild_Fach_Repository;
use Mh\UntisSchild\Service\Converter_Service;
use Mh\UntisSchild\Service\Excel_Service;
use Mh\UntisSchild\Service\Gpu_Parser;
use Mh\UntisSchild\Service\Import_Fehler;
use Mh\UntisSchild\Service\Pipeline;
use Mh\UntisSchild\Service\Regel_Pruefer;
use Mh\UntisSchild\Service\Regel_Text;
use Mh\UntisSchild\Service\Schild_Export;
use Mh\UntisSchild\Service\Zugriff;
use Mh\UntisSchild\Setup\Plugin_Bootstrap;

/**
 * Class Converter_Controller
 *
 * Frontend des Converters (Shortcode) und alle Formular-Aktionen. Jede Aktion läuft per
 * POST über admin-post.php, prüft Nonce und Berechtigung und leitet mit einer
 * Rückmeldung (Flash) auf die Seite zurück.
 */
class Converter_Controller {

	public const TABS = [
		'import'        => 'Import',
		'klassen'       => 'Klassen',
		'faecher'       => 'Fächer',
		'konflikte'     => 'Konflikte',
		'schildfaecher' => 'Schild-Fächer',
		'ergebnis'      => 'Ergebnis',
	];

	/** Aktion => Handler-Methode. Der Aktionsname ist zugleich die Nonce-Aktion. */
	private const AKTIONEN = [
		'usc_import'                => 'handle_import',
		'usc_import_meta'           => 'handle_import_meta',
		'usc_import_loeschen'       => 'handle_import_loeschen',
		'usc_klassen_regel'         => 'handle_klassen_regel',
		'usc_fach_regel'            => 'handle_fach_regel',
		'usc_regel_aktion'          => 'handle_regel_aktion',
		'usc_regeln_einfuegen'      => 'handle_regeln_einfuegen',
		'usc_konflikte'             => 'handle_konflikte',
		'usc_entscheidung_loeschen' => 'handle_entscheidung_loeschen',
		'usc_schildfaecher'         => 'handle_schildfaecher',
		'usc_export'                => 'handle_export',
		'usc_regeln_zusammenfassen' => 'handle_regeln_zusammenfassen',
	];

	public const OPTION_SCHILD_STAND = 'mh_usc_schildfaecher_stand';
	private const MAX_UPLOAD         = 5 * MB_IN_BYTES;

	public function __construct(
		private Import_Repository $imports,
		private Klassen_Regel_Repository $klassen_regeln,
		private Fach_Regel_Repository $fach_regeln,
		private Entscheidung_Repository $entscheidungen,
		private Schild_Fach_Repository $schild_faecher,
		private Converter_Service $converter,
		private Gpu_Parser $parser,
		private Excel_Service $excel,
		private Regel_Pruefer $pruefer,
		private Protokoll_Repository $protokoll,
	) {}

	public function register(): void {
		add_shortcode( Plugin_Bootstrap::SHORTCODE, [ $this, 'render' ] );
		add_action( 'template_redirect', [ $this, 'schuetze_seite' ] );
		foreach ( self::AKTIONEN as $aktion => $methode ) {
			// Nur admin_post_ (angemeldet), bewusst kein admin_post_nopriv_.
			add_action( 'admin_post_' . $aktion, function () use ( $aktion, $methode ): void {
				if ( ! Zugriff::darf() ) {
					wp_die( 'Keine Berechtigung für den Untis2Schild Converter.', 'Keine Berechtigung', [ 'response' => 403 ] );
				}
				check_admin_referer( $aktion );
				$this->$methode();
			} );
		}
	}

	/**
	 * Seiten mit dem Shortcode zeigen personenbezogene Daten (Lehrkraft-Kürzel): Sie dürfen
	 * weder von Cache-Plugins/Proxys zwischengespeichert (sonst sähen andere die Ansicht einer
	 * berechtigten Person) noch von Suchmaschinen erfasst werden.
	 */
	public function schuetze_seite(): void {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! has_shortcode( (string) $post->post_content, Plugin_Bootstrap::SHORTCODE ) ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		header( 'Referrer-Policy: same-origin' );
		// Übliche Signale an Cache-Plugins (WP Super Cache, W3TC, WP Rocket, LiteSpeed …).
		foreach ( [ 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ] as $konstante ) {
			if ( ! defined( $konstante ) ) {
				define( $konstante, true );
			}
		}
		do_action( 'litespeed_control_set_nocache', 'Untis2Schild: personenbezogene Daten' );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
	}

	// =========================================================================
	// Anzeige
	// =========================================================================

	public function render( $atts = [] ): string {
		if ( ! is_user_logged_in() || ! Zugriff::darf() ) {
			$login_url = wp_login_url( (string) get_permalink() );
			ob_start();
			include MH_USC_PLUGIN_DIR . 'templates/frontend/kein-zugriff.php';
			return (string) ob_get_clean();
		}

		$tab = sanitize_key( wp_unslash( $_GET['usc_tab'] ?? '' ) );
		if ( ! isset( self::TABS[ $tab ] ) ) {
			$tab = 'import';
		}
		$page_url = remove_query_arg( [ 'usc_tab', 'usc_bearbeiten' ], (string) ( get_permalink() ?: home_url( add_query_arg( [] ) ) ) );
		$tab_url  = static fn( string $t, array $args = [] ): string => add_query_arg( array_merge( [ 'usc_tab' => $t ], $args ), $page_url ) . '#usc';

		$import   = $this->imports->latest();
		$ergebnis = null !== $import ? $this->converter->ergebnis( $import ) : null;
		$flash    = $this->hole_flash();
		$benutzer = static function ( int $id ): string {
			$u = $id > 0 ? get_userdata( $id ) : false;
			return $u ? (string) $u->display_name : '–';
		};
		// Kopf jedes Formulars: Aktion, Rücksprung, Nonce. Ohne wp_nonce_field(), weil dessen
		// feste id="_wpnonce" bei vielen Formularen auf einer Seite doppelt vorkäme.
		$formular = static function ( string $aktion, string $ziel_tab ) use ( $page_url ): void {
			printf( '<input type="hidden" name="action" value="%s">', esc_attr( $aktion ) );
			printf( '<input type="hidden" name="zurueck" value="%s">', esc_attr( add_query_arg( 'usc_tab', $ziel_tab, $page_url ) ) );
			printf( '<input type="hidden" name="_wpnonce" value="%s">', esc_attr( wp_create_nonce( $aktion ) ) );
		};
		$post_url = admin_url( 'admin-post.php' );

		$daten = match ( $tab ) {
			'import'        => $this->daten_import( $import ),
			'klassen'       => $this->daten_klassen( $import, $ergebnis ),
			'faecher'       => $this->daten_faecher( $import, $ergebnis ),
			'konflikte'     => $this->daten_konflikte( $ergebnis ),
			'schildfaecher' => $this->daten_schildfaecher( $import, $ergebnis ),
			default         => [],
		};
		extract( $daten, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract

		ob_start();
		include MH_USC_PLUGIN_DIR . 'templates/frontend/layout.php';
		return (string) ob_get_clean();
	}

	private function daten_import( ?object $import ): array {
		$monat = (int) current_time( 'n' );
		$jahr  = (int) current_time( 'Y' );
		return [
			'importe'          => $this->imports->all(),
			'gruppen'          => null !== $import ? $this->imports->gruppen( (int) $import->id ) : [],
			'anzahl_klassen'   => null !== $import ? count( $this->imports->klassen( (int) $import->id ) ) : 0,
			'anzahl_faecher'   => null !== $import ? count( $this->imports->faecher( (int) $import->id ) ) : 0,
			// Schuljahr beginnt im August; Februar bis Juli = 2. Halbjahr.
			'vorschlag_jahr'   => $monat >= 8 ? $jahr : $jahr - 1,
			'vorschlag_hj'     => ( $monat >= 8 || 1 === $monat ) ? 1 : 2,
		];
	}

	private function daten_klassen( ?object $import, ?Pipeline_Ergebnis $ergebnis ): array {
		$regeln = $this->klassen_regeln->all();
		$zeilen = null !== $import ? $this->imports->zeilen( (int) $import->id ) : [];
		return [
			'regeln'         => $regeln,
			'nummern'        => self::nummern( $regeln ),
			'befunde'        => $this->pruefer->klassenregeln( $regeln, $zeilen ),
			'aenderungen'    => $this->klassen_regeln->aenderungen(),
			'regel_klassen'  => self::regel_klassen( $ergebnis ),
			'werte'          => null !== $import ? $this->imports->klassen( (int) $import->id ) : [],
			'klassen_zeilen' => null !== $import ? $this->imports->klassen_zeilen( (int) $import->id ) : [],
			'als_text'       => Regel_Text::klassen_als_text( $regeln ),
			'bearbeiten'     => absint( $_GET['usc_bearbeiten'] ?? 0 ),
			'vorlage'        => self::vorlage(),
		];
	}

	/**
	 * Laufende Nummer je Regel (wie in der Liste angezeigt), damit Befunde auf „Regel 12“ verweisen können.
	 *
	 * @param array<int, Klassen_Regel|Fach_Regel> $regeln
	 * @return array<int, int> id => Nr.
	 */
	private static function nummern( array $regeln ): array {
		$out = [];
		foreach ( array_values( $regeln ) as $i => $r ) {
			$out[ $r->id ] = $i + 1;
		}
		return $out;
	}

	private function daten_faecher( ?object $import, ?Pipeline_Ergebnis $ergebnis ): array {
		$regeln = $this->fach_regeln->all();
		$kurz   = $this->schild_faecher->kurzformen();
		return [
			'nummern'       => self::nummern( $regeln ),
			'befunde'       => $this->pruefer->fachregeln(
				$regeln,
				null !== $import ? $this->imports->zeilen( (int) $import->id ) : [],
				$this->klassen_regeln->all(),
				[] === $kurz ? null : $kurz
			),
			'regeln'        => $regeln,
			'aenderungen'   => $this->fach_regeln->aenderungen(),
			'regel_klassen' => self::regel_klassen( $ergebnis ),
			'ueberdeckt'    => self::ueberdeckungen( $ergebnis ),
			'werte'         => null !== $import ? $this->imports->faecher( (int) $import->id ) : [],
			'klassen_werte' => null !== $import ? $this->imports->klassen( (int) $import->id ) : [],
			'fach_klassen'  => null !== $import ? $this->imports->fach_klassen( (int) $import->id ) : [],
			'schild_liste'  => $this->schild_faecher->bezeichnungen(),
			'als_text'      => Regel_Text::faecher_als_text( $regeln ),
			'bearbeiten'    => absint( $_GET['usc_bearbeiten'] ?? 0 ),
			'vorlage'       => self::vorlage(),
		];
	}

	/**
	 * Vorbelegung des Regelformulars über die URL, z. B. aus „Fehlt in Schild“:
	 * ?usc_vorlage[art]=loeschen&usc_vorlage[untis]=XY&usc_vorlage[bemerkung]=…
	 *
	 * @return array<string, string>
	 */
	private static function vorlage(): array {
		$roh = isset( $_GET['usc_vorlage'] ) && is_array( $_GET['usc_vorlage'] ) ? wp_unslash( $_GET['usc_vorlage'] ) : [];
		$out = [];
		foreach ( [ 'art', 'untis', 'schild', 'klassen', 'bemerkung' ] as $feld ) {
			if ( isset( $roh[ $feld ] ) && is_string( $roh[ $feld ] ) ) {
				$out[ $feld ] = sanitize_text_field( $roh[ $feld ] );
			}
		}
		return $out;
	}

	private function daten_konflikte( ?Pipeline_Ergebnis $ergebnis ): array {
		$konflikte = null !== $ergebnis ? $ergebnis->konflikte : [];
		usort( $konflikte, static fn( Konflikt $a, Konflikt $b ) =>
			[ $a->ist_offen() ? 0 : 1, mb_strtolower( $a->klasse ), mb_strtolower( $a->fach ) ]
			<=> [ $b->ist_offen() ? 0 : 1, mb_strtolower( $b->klasse ), mb_strtolower( $b->fach ) ] );
		return [
			'konflikte'      => $konflikte,
			'gespeichert'    => $this->entscheidungen->all(),
		];
	}

	private function daten_schildfaecher( ?object $import, ?Pipeline_Ergebnis $ergebnis ): array {
		return [
			'faecher'      => $this->schild_faecher->all(),
			'schild_stand' => get_option( self::OPTION_SCHILD_STAND ),
			'herkunft'     => $this->herkunft_fehlender_faecher( $import, $ergebnis ),
		];
	}

	/**
	 * Woher kommt ein Fach, das in Schild fehlt? Aus Untis (dann hilft eine neue Regel)
	 * oder aus einer eigenen Regel (dann muss diese Regel korrigiert werden – eine neue
	 * Regel würde nicht greifen, weil Regeln auf die Untis-Fächer wirken).
	 *
	 * @return array<string, array{untis: bool, regel_id: ?int}>
	 */
	private function herkunft_fehlender_faecher( ?object $import, ?Pipeline_Ergebnis $ergebnis ): array {
		if ( null === $import || null === $ergebnis || [] === $ergebnis->unbekannte_faecher ) {
			return [];
		}
		$roh = array_fill_keys( array_map( 'mb_strtolower', $this->imports->faecher( (int) $import->id ) ), true );

		$aus_regel = [];
		foreach ( $ergebnis->protokoll_fuer( Protokoll_Eintrag::SCHRITT_FAECHER ) as $e ) {
			if ( Protokoll_Eintrag::AKTION_GEAENDERT !== $e->aktion || null === $e->regel ) {
				continue;
			}
			foreach ( $e->nachher as $z ) {
				$aus_regel[ mb_strtolower( $z->fach ) ] ??= (int) substr( $e->regel, 1 );
			}
		}

		$out = [];
		foreach ( array_keys( $ergebnis->unbekannte_faecher ) as $fach ) {
			$key          = mb_strtolower( (string) $fach );
			$out[ $fach ] = [ 'untis' => isset( $roh[ $key ] ), 'regel_id' => $aus_regel[ $key ] ?? null ];
		}
		return $out;
	}

	/**
	 * Auf welche Klassen hat eine Regel gewirkt? Aus dem Protokoll abgeleitet.
	 *
	 * @return array<string, string[]> Regelkennung => Klassen
	 */
	private static function regel_klassen( ?Pipeline_Ergebnis $ergebnis ): array {
		$map = [];
		foreach ( $ergebnis?->protokoll ?? [] as $e ) {
			if ( null !== $e->regel && Protokoll_Eintrag::AKTION_HINWEIS !== $e->aktion && '' !== $e->klasse ) {
				$map[ $e->regel ][ $e->klasse ] = true;
			}
		}
		return array_map( static function ( array $k ): array {
			$k = array_map( 'strval', array_keys( $k ) );
			natcasesort( $k );
			return array_values( $k );
		}, $map );
	}

	/**
	 * Fachregeln, die Zeilen auch für andere Regeln getroffen haben (erste passende gilt).
	 *
	 * @return array<string, int> Regelkennung => Anzahl Fälle
	 */
	private static function ueberdeckungen( ?Pipeline_Ergebnis $ergebnis ): array {
		$map = [];
		foreach ( $ergebnis?->protokoll_fuer( Protokoll_Eintrag::SCHRITT_FAECHER ) ?? [] as $e ) {
			if ( Protokoll_Eintrag::AKTION_HINWEIS === $e->aktion && null !== $e->regel ) {
				$map[ $e->regel ] = ( $map[ $e->regel ] ?? 0 ) + 1;
			}
		}
		return $map;
	}

	// =========================================================================
	// Import
	// =========================================================================

	private function handle_import(): void {
		$schuljahr = absint( $_POST['schuljahr'] ?? 0 );
		$halbjahr  = absint( $_POST['halbjahr'] ?? 0 );
		$datei     = $_FILES['gpu'] ?? null;

		if ( $schuljahr < 2000 || $schuljahr > 2100 || ! in_array( $halbjahr, [ 1, 2 ], true ) ) {
			$this->zurueck( 'import', 'fehler', 'Bitte Schuljahr und Halbjahr auswählen.' );
		}
		$fehler = $this->pruefe_upload( $datei, [ 'txt', 'csv' ] );
		if ( null !== $fehler ) {
			$this->zurueck( 'import', 'fehler', $fehler );
		}

		try {
			// Die Datei wird nur gelesen, nicht verschoben – PHP löscht sie nach der Anfrage.
			$gpu    = $this->parser->parse( (string) file_get_contents( $datei['tmp_name'] ) );
			$vorher = $this->imports->all(); // create() löscht überzählige alte Importe
			$id     = $this->imports->create( $gpu, $schuljahr, $halbjahr, sanitize_file_name( (string) $datei['name'] ), get_current_user_id() );
		} catch ( Import_Fehler $e ) {
			$this->zurueck( 'import', 'fehler', $e->getMessage() );
		}
		$this->converter->geaendert( $vorher );
		$this->protokoll->log( 'import', sprintf( 'Import #%d: %d Zeilen, %s HJ %d', $id, count( $gpu->zeilen ), self::schuljahr_text( $schuljahr ), $halbjahr ) );

		$this->zurueck( 'import', 'ok', sprintf( 'GPU002 eingelesen: %d Unterrichtszeilen%s.',
			count( $gpu->zeilen ),
			$gpu->uebersprungen ? sprintf( ', %d übersprungen', count( $gpu->uebersprungen ) ) : '' ) );
	}

	private function handle_import_meta(): void {
		$id        = absint( $_POST['id'] ?? 0 );
		$schuljahr = absint( $_POST['schuljahr'] ?? 0 );
		$halbjahr  = absint( $_POST['halbjahr'] ?? 0 );
		if ( null === $this->imports->get( $id ) || $schuljahr < 2000 || $schuljahr > 2100 || ! in_array( $halbjahr, [ 1, 2 ], true ) ) {
			$this->zurueck( 'import', 'fehler', 'Ungültige Angaben.' );
		}
		$this->imports->update_meta( $id, $schuljahr, $halbjahr );
		$this->converter->geaendert();
		$this->protokoll->log( 'import_geaendert', sprintf( 'Import #%d: %s HJ %d', $id, self::schuljahr_text( $schuljahr ), $halbjahr ) );
		$this->zurueck( 'import', 'ok', sprintf( 'Import gilt jetzt für %s, %d. Halbjahr.', self::schuljahr_text( $schuljahr ), $halbjahr ) );
	}

	private function handle_import_loeschen(): void {
		$id      = absint( $_POST['id'] ?? 0 );
		$vorher  = $this->imports->all();
		$this->imports->delete( $id );
		$this->converter->geaendert( $vorher );
		$this->protokoll->log( 'import_geloescht', sprintf( 'Import #%d', $id ) );
		$this->zurueck( 'import', 'ok', 'Import gelöscht.' );
	}

	// =========================================================================
	// Regeln
	// =========================================================================

	private function handle_klassen_regel(): void {
		$id    = absint( $_POST['id'] ?? 0 );
		$art   = sanitize_key( wp_unslash( $_POST['art'] ?? '' ) );
		$regel = new Klassen_Regel(
			$id,
			self::text( 'muster' ),
			'loeschen' === $art ? '' : self::text( 'ziel' ),
			self::text( 'bemerkung' ),
			0 === $id || ! empty( $_POST['aktiv'] ),
		);
		$fehler = $regel->fehler();
		if ( 'umbenennen' === $art && $regel->ist_loeschung() ) {
			$fehler['ziel'] = 'Bitte angeben, wie die Klasse in Schild heißt.';
		}
		if ( $fehler ) {
			$this->zurueck( 'klassen', 'fehler', 'Die Regel wurde nicht gespeichert.', $fehler, $_POST, $id );
		}
		$this->klassen_regeln->save( $regel, get_current_user_id() );
		$this->converter->geaendert();
		$this->zurueck( 'klassen', 'ok', $id > 0 ? 'Klassenregel geändert.' : 'Klassenregel angelegt.' );
	}

	private function handle_fach_regel(): void {
		$id  = absint( $_POST['id'] ?? 0 );
		$art = sanitize_key( wp_unslash( $_POST['art'] ?? '' ) );

		$regel = Fach_Regel::aus_text(
			$id,
			self::text( 'untis_faecher' ),
			// Beim Entfernen keine Schild-Fächer, auch wenn vorher welche eingetragen waren.
			Fach_Regel::ART_LOESCHEN === $art ? '' : self::text( 'schild_faecher' ),
			'alle' === ( $_POST['klassen_modus'] ?? '' ) ? '' : self::text( 'klassen_muster' ),
			self::text( 'bemerkung' ),
			0 === $id || ! empty( $_POST['aktiv'] ),
		);
		$fehler = $regel->fehler() + self::art_fehler( $art, $regel );
		if ( $fehler ) {
			$this->zurueck( 'faecher', 'fehler', 'Die Regel wurde nicht gespeichert.', $fehler, $_POST, $id );
		}
		$this->fach_regeln->save( $regel, get_current_user_id() );
		$this->converter->geaendert();
		$this->zurueck( 'faecher', 'ok', $id > 0 ? 'Fachregel geändert.' : 'Fachregel angelegt.' );
	}

	/**
	 * Passt die Eingabe zur gewählten Art? (Ohne Art – z. B. Experten-Eingabe – keine Prüfung.)
	 *
	 * @return array<string, string>
	 */
	private static function art_fehler( string $art, Fach_Regel $r ): array {
		$untis  = count( $r->untis_faecher );
		$schild = count( $r->schild_faecher );
		$muster = str_contains( implode( '', $r->untis_faecher ), '*' ) || str_contains( implode( '', $r->untis_faecher ), '%' );
		return match ( $art ) {
			Fach_Regel::ART_UMBENENNEN     => 1 !== $schild ? [ 'schild_faecher' => 'Zum Umbenennen genau ein Schild-Fach angeben.' ]
				: ( $untis > 1 ? [ 'untis_faecher' => 'Zum Umbenennen genau ein Untis-Fach angeben – für mehrere „Zusammenfassen“ wählen.' ] : [] ),
			Fach_Regel::ART_ZUSAMMENFASSEN => 1 !== $schild ? [ 'schild_faecher' => 'Zum Zusammenfassen genau ein Schild-Fach angeben.' ]
				: ( $untis < 2 && ! $muster ? [ 'untis_faecher' => 'Zum Zusammenfassen mindestens zwei Untis-Fächer angeben.' ] : [] ),
			Fach_Regel::ART_AUFTEILEN      => $schild < 2 ? [ 'schild_faecher' => 'Zum Aufteilen mindestens zwei Schild-Fächer angeben.' ] : [],
			default                        => [],
		};
	}

	private function handle_regel_aktion(): void {
		$typ  = 'faecher' === ( $_POST['typ'] ?? '' ) ? 'faecher' : 'klassen';
		$repo = 'faecher' === $typ ? $this->fach_regeln : $this->klassen_regeln;
		$id   = absint( $_POST['id'] ?? 0 );
		$was  = sanitize_key( wp_unslash( $_POST['was'] ?? '' ) );

		$meldung = match ( $was ) {
			'loeschen'    => 'Regel gelöscht.',
			'aktivieren'  => 'Regel aktiviert.',
			'deaktivieren' => 'Regel deaktiviert – sie bleibt gespeichert, wirkt aber nicht.',
			'oben', 'unten', 'vor' => 'Reihenfolge geändert.',
			default       => null,
		};
		if ( null === $meldung || null === $repo->get( $id ) ) {
			$this->zurueck( $typ, 'fehler', 'Unbekannte Aktion.' );
		}
		match ( $was ) {
			'loeschen'     => $repo->delete( $id ),
			'aktivieren'   => $repo->set_aktiv( $id, true, get_current_user_id() ),
			'deaktivieren' => $repo->set_aktiv( $id, false, get_current_user_id() ),
			'oben'         => $repo->verschieben( $id, true ),
			'unten'        => $repo->verschieben( $id, false ),
			'vor'          => $repo->verschieben_vor( $id, absint( $_POST['vor_id'] ?? 0 ) ),
		};
		$this->converter->geaendert();
		$this->zurueck( $typ, 'ok', $meldung );
	}

	/**
	 * Fasst mehrere Regeln zu einer zusammen – aber nur, wenn das Ergebnis mit dem aktuellen
	 * Import gleich bleibt. Die zusammengefasste Regel übernimmt Platz und id der obersten.
	 */
	private function handle_regeln_zusammenfassen(): void {
		$typ    = 'faecher' === ( $_POST['typ'] ?? '' ) ? 'faecher' : 'klassen';
		$ids    = array_values( array_unique( array_map( 'absint', (array) ( $_POST['ids'] ?? [] ) ) ) );
		$repo   = 'faecher' === $typ ? $this->fach_regeln : $this->klassen_regeln;
		$alle   = $repo->all();
		$gruppe = array_values( array_filter( $alle, static fn( $r ) => in_array( $r->id, $ids, true ) && $r->aktiv ) );
		if ( count( $gruppe ) < 2 || count( $gruppe ) !== count( $ids ) ) {
			$this->zurueck( $typ, 'fehler', 'Die Regeln haben sich inzwischen geändert – bitte die Seite neu laden.' );
		}

		// Nur Regeln mit gleicher Wirkung: gleiche Schild-Fächer bzw. lauter Lösch-Regeln.
		$wirkungen = array_unique( array_map( static fn( $r ) => $r instanceof Fach_Regel
			? mb_strtolower( implode( '|', $r->schild_faecher ) )
			: ( $r->ist_loeschung() ? '' : '#' . $r->id ), $gruppe ) );
		$neu = 1 !== count( $wirkungen ) ? null
			: ( 'faecher' === $typ ? Regel_Pruefer::fach_zusammenfuehren( $gruppe ) : Regel_Pruefer::klassen_zusammenfuehren( $gruppe ) );
		if ( null === $neu ) {
			$this->zurueck( $typ, 'fehler', 'Diese Regeln lassen sich nicht zusammenfassen (zu lang oder unterschiedliche Wirkung).' );
		}

		$import = $this->imports->latest();
		if ( null !== $import ) {
			$rest    = array_values( array_filter( $alle, static fn( $r ) => ! in_array( $r->id, $ids, true ) ) );
			$rest[]  = $neu;
			$klassen = 'klassen' === $typ ? $rest : $this->klassen_regeln->all();
			$faecher = 'faecher' === $typ ? $rest : $this->fach_regeln->all();
			$vorher  = $this->converter->ergebnis_mit( $import, $this->klassen_regeln->all(), $this->fach_regeln->all() );
			$nachher = $this->converter->ergebnis_mit( $import, $klassen, $faecher );
			if ( ! Converter_Service::gleiches_ergebnis( $vorher, $nachher ) ) {
				$this->zurueck( $typ, 'fehler', 'Nicht zusammengefasst: Mit dem aktuellen Import würde sich das Ergebnis ändern, weil andere Regeln dazwischen liegen. '
					. 'Prüfe die Reihenfolge oder fasse von Hand zusammen.' );
			}
		}

		$repo->save( $neu, get_current_user_id() );
		foreach ( $ids as $id ) {
			if ( $id !== $neu->id ) {
				$repo->delete( $id );
			}
		}
		$this->converter->geaendert();
		$this->zurueck( $typ, 'ok', sprintf( '%d Regeln zu einer zusammengefasst%s.', count( $ids ), null === $import ? ' (ohne Import nicht nachgerechnet)' : ' – das Ergebnis bleibt gleich' ), [], [], $neu->id );
	}

	private function handle_regeln_einfuegen(): void {
		$typ      = 'faecher' === ( $_POST['typ'] ?? '' ) ? 'faecher' : 'klassen';
		$text     = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
		$ersetzen = 'ersetzen' === ( $_POST['modus'] ?? '' );
		$gelesen  = 'faecher' === $typ ? Regel_Text::faecher( $text ) : Regel_Text::klassen( $text );

		if ( [] === $gelesen['regeln'] ) {
			$this->zurueck( $typ, 'fehler', 'Der Textblock enthält keine Regeln.', [], $_POST );
		}
		if ( $gelesen['fehler'] ) {
			$this->zurueck( $typ, 'fehler', 'Es wurde nichts gespeichert – bitte die markierten Zeilen korrigieren.', $gelesen['fehler'], $_POST );
		}
		if ( $ersetzen && empty( $_POST['bestaetigt'] ) ) {
			$this->zurueck( $typ, 'fehler', 'Zum Ersetzen aller Regeln bitte das Häkchen zur Bestätigung setzen.', [], $_POST );
		}

		$repo = 'faecher' === $typ ? $this->fach_regeln : $this->klassen_regeln;
		if ( $ersetzen ) {
			$repo->delete_all();
		}
		foreach ( $gelesen['regeln'] as $r ) {
			$repo->save( $r, get_current_user_id() );
		}
		$this->converter->geaendert();
		$this->zurueck( $typ, 'ok', sprintf( '%d Regeln %s.', count( $gelesen['regeln'] ), $ersetzen ? 'gespeichert, alle bisherigen ersetzt' : 'hinzugefügt' ) );
	}

	// =========================================================================
	// Konflikte
	// =========================================================================

	/**
	 * Speichert nur Auswahlen, die von der automatischen Wahl abweichen oder einen offenen
	 * Konflikt lösen. Jede Auswahl wird gegen den aktuellen Konflikt geprüft.
	 */
	private function handle_konflikte(): void {
		$import = $this->imports->latest();
		if ( null === $import ) {
			$this->zurueck( 'konflikte', 'fehler', 'Es gibt keinen Import.' );
		}
		$aktuell = [];
		foreach ( $this->converter->ergebnis( $import )->konflikte as $k ) {
			$aktuell[ Pipeline::schluessel( $k->klasse, $k->fach ) ] = $k;
		}

		$gespeichert = 0;
		foreach ( (array) ( $_POST['wahl'] ?? [] ) as $key => $lehrer ) {
			$key    = (string) wp_unslash( $key );
			$lehrer = sanitize_text_field( wp_unslash( (string) $lehrer ) );
			$k      = $aktuell[ $key ] ?? null;
			if ( null === $k || '' === $lehrer || $lehrer === $k->lehrer || ! array_key_exists( $lehrer, $k->kandidaten ) ) {
				continue;
			}
			$this->entscheidungen->save( $k->klasse, $k->fach, $lehrer, get_current_user_id() );
			$gespeichert++;
		}
		$this->converter->geaendert();
		if ( $gespeichert > 0 ) {
			$this->protokoll->log( 'auswahl_gespeichert', sprintf( '%d Lehrkraft-Auswahlen', $gespeichert ) );
		}
		$this->zurueck( 'konflikte', 'ok', 0 === $gespeichert ? 'Keine Änderungen.' : sprintf( '%d Auswahl%s gespeichert.', $gespeichert, 1 === $gespeichert ? '' : 'en' ) );
	}

	private function handle_entscheidung_loeschen(): void {
		$this->entscheidungen->delete( absint( $_POST['id'] ?? 0 ) );
		$this->converter->geaendert();
		$this->protokoll->log( 'auswahl_entfernt', 'eine Lehrkraft-Auswahl' );
		$this->zurueck( 'konflikte', 'ok', 'Gespeicherte Auswahl entfernt – es gilt wieder die automatische Wahl.' );
	}

	// =========================================================================
	// Schild-Fächer und Export
	// =========================================================================

	private function handle_schildfaecher(): void {
		$datei  = $_FILES['xlsx'] ?? null;
		$fehler = $this->pruefe_upload( $datei, [ 'xlsx', 'xls' ] );
		if ( null !== $fehler ) {
			$this->zurueck( 'schildfaecher', 'fehler', $fehler );
		}
		try {
			$faecher = $this->excel->lies_schild_faecher( (string) $datei['tmp_name'] );
		} catch ( Import_Fehler $e ) {
			$this->zurueck( 'schildfaecher', 'fehler', $e->getMessage() );
		}
		$this->schild_faecher->replace_all( $faecher );
		update_option( self::OPTION_SCHILD_STAND, [
			'datum' => current_time( 'mysql' ),
			'von'   => get_current_user_id(),
			'datei' => sanitize_file_name( (string) $datei['name'] ),
		], false );
		$this->converter->geaendert();
		$this->protokoll->log( 'schildfaecher', sprintf( '%d Fächer', count( $faecher ) ) );
		$this->zurueck( 'schildfaecher', 'ok', sprintf( '%d Schild-Fächer eingelesen.', count( $faecher ) ) );
	}

	private function handle_export(): void {
		$import = $this->imports->latest();
		if ( null === $import ) {
			$this->zurueck( 'ergebnis', 'fehler', 'Es gibt keinen Import.' );
		}
		$ergebnis = $this->converter->ergebnis( $import );
		if ( $ergebnis->export_gesperrt() ) {
			$this->zurueck( 'konflikte', 'fehler', 'Export gesperrt: Bitte zuerst die offenen Konflikte entscheiden.' );
		}
		if ( ! Excel_Service::verfuegbar() ) {
			$this->zurueck( 'ergebnis', 'fehler', 'Die PHP-Erweiterung zip fehlt – es kann keine Excel-Datei erzeugt werden.' );
		}
		$this->protokoll->log( 'export', sprintf( 'Import #%d: %d Zeilen, %s HJ %d', (int) $import->id, count( $ergebnis->zeilen ),
			self::schuljahr_text( (int) $import->schuljahr ), (int) $import->halbjahr ) );
		$this->excel->sende_xlsx(
			sprintf( 'schildimport_%d_HJ%d.xlsx', (int) $import->schuljahr, (int) $import->halbjahr ),
			Schild_Export::KOPFZEILE,
			Schild_Export::zeilen( $ergebnis->zeilen, (int) $import->schuljahr, (int) $import->halbjahr )
		);
		exit;
	}

	// =========================================================================
	// Hilfen
	// =========================================================================

	public static function schuljahr_text( int $jahr ): string {
		return sprintf( '%d/%02d', $jahr, ( $jahr + 1 ) % 100 );
	}

	private static function text( string $feld ): string {
		return sanitize_text_field( wp_unslash( (string) ( $_POST[ $feld ] ?? '' ) ) );
	}

	/**
	 * @param string[] $endungen
	 */
	private function pruefe_upload( ?array $datei, array $endungen ): ?string {
		if ( null === $datei || UPLOAD_ERR_NO_FILE === ( $datei['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return 'Bitte eine Datei auswählen.';
		}
		if ( UPLOAD_ERR_OK !== $datei['error'] || ! is_uploaded_file( (string) $datei['tmp_name'] ) ) {
			return 'Die Datei konnte nicht hochgeladen werden.';
		}
		if ( (int) $datei['size'] > self::MAX_UPLOAD ) {
			return 'Die Datei ist zu groß (höchstens 5 MB).';
		}
		$endung = strtolower( pathinfo( (string) $datei['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $endung, $endungen, true ) ) {
			return sprintf( 'Bitte eine Datei vom Typ %s hochladen.', implode( ' oder ', array_map( static fn( $e ) => '.' . $e, $endungen ) ) );
		}
		return null;
	}

	/**
	 * Rückmeldung merken und zurück zur Seite mit dem Shortcode.
	 *
	 * @param string[]             $fehler
	 * @param array<string, mixed> $eingabe Formularwerte zum erneuten Befüllen nach einem Fehler.
	 */
	private function zurueck( string $tab, string $typ, string $text, array $fehler = [], array $eingabe = [], int $bearbeiten = 0 ): never {
		unset( $eingabe['_wpnonce'], $eingabe['_wp_http_referer'], $eingabe['action'], $eingabe['zurueck'] );
		set_transient( 'mh_usc_flash_' . get_current_user_id(), [
			'typ'     => $typ,
			'text'    => $text,
			'fehler'  => $fehler, // bei Einzelregeln Feld => Meldung, damit das Feld markiert werden kann
			'eingabe' => wp_unslash( $eingabe ),
		], 5 * MINUTE_IN_SECONDS );

		$url = wp_validate_redirect( esc_url_raw( wp_unslash( (string) ( $_POST['zurueck'] ?? '' ) ) ), home_url( '/' ) );
		$url = add_query_arg( array_filter( [ 'usc_tab' => $tab, 'usc_bearbeiten' => $bearbeiten ?: null ] ), $url );
		wp_safe_redirect( $url . '#usc' );
		exit;
	}

	/**
	 * @return array{typ:string, text:string, fehler:string[], eingabe:array}|null
	 */
	private function hole_flash(): ?array {
		$key   = 'mh_usc_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( false === $flash ) {
			return null;
		}
		delete_transient( $key );
		return is_array( $flash ) ? $flash : null;
	}
}
