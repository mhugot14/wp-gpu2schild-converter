<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Controller;

use Mh\UntisSchild\Repository\Import_Repository;
use Mh\UntisSchild\Repository\Protokoll_Repository;
use Mh\UntisSchild\Repository\Status_Repository;
use Mh\UntisSchild\Setup\Activator;
use Mh\UntisSchild\Service\Aufbewahrung;
use Mh\UntisSchild\Service\Zugriff;
use Mh\UntisSchild\Setup\Plugin_Bootstrap;

/**
 * Class Admin_Controller
 *
 * Startseite im Backend: Erklärung des Plugins, Einbindung des Shortcodes und
 * Konfigurationscheck. Die eigentliche Arbeit findet im Frontend statt.
 */
class Admin_Controller {

	/** Slug aus der Vorversion beibehalten, damit Lesezeichen auf die Übersicht weiter funktionieren. */
	public const MENU_SLUG  = 'mh_u2s_main-menu';
	public const CAPABILITY = 'manage_options';

	public const FREIGABEN_SLUG = 'mh_usc_freigaben';

	private const ACTION_CREATE_PAGE = 'mh_usc_create_page';
	private const ACTION_FREIGABEN   = 'mh_usc_freigaben_speichern';

	public function __construct(
		private Status_Repository $status_repo,
		private Protokoll_Repository $protokoll,
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'datenschutzerklaerung' ] );
		add_action( 'admin_post_' . self::ACTION_CREATE_PAGE, [ $this, 'handle_create_page' ] );
		add_action( 'admin_post_' . self::ACTION_FREIGABEN, [ $this, 'handle_freigaben' ] );
	}

	public function register_menu(): void {
		add_menu_page( 'Untis2Schild Converter', 'Untis2Schild', self::CAPABILITY, self::MENU_SLUG, [ $this, 'render_start' ], 'dashicons-migrate', 30 );
		add_submenu_page( self::MENU_SLUG, 'Untis2Schild – Übersicht', 'Übersicht', self::CAPABILITY, self::MENU_SLUG, [ $this, 'render_start' ] );
		add_submenu_page( self::MENU_SLUG, 'Untis2Schild – Freigaben & Datenschutz', 'Freigaben & Datenschutz', self::CAPABILITY, self::FREIGABEN_SLUG, [ $this, 'render_freigaben' ] );
	}

	/**
	 * Textvorschlag für die Datenschutzerklärung (Einstellungen → Datenschutz → Leitfaden).
	 */
	public function datenschutzerklaerung(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content( 'Untis2Schild Converter', wp_kses_post(
			'<p>Zur Übertragung der Unterrichtsverteilung aus Untis nach SchILD-NRW verarbeiten berechtigte Personen der Schulverwaltung '
			. 'die Unterrichtsdatei aus Untis (GPU002). Sie enthält zu jedem Unterricht Klasse, Fach, Wochenstunden und das Kürzel der '
			. 'Lehrkraft. Gespeichert werden nur diese Angaben; die hochgeladene Datei selbst wird nicht aufbewahrt. Zugriff haben '
			. 'ausschließlich Administratorinnen und Administratoren sowie ausdrücklich freigeschaltete Personen. Gespeichert werden höchstens '
			. 'die letzten ' . Import_Repository::BEHALTEN . ' Importe, jeder Import wird spätestens nach ' . Aufbewahrung::tage() . ' Tagen gelöscht. '
			. 'Ausgelöste Aktionen (Import, Export, Auswahl von Lehrkräften) werden ohne Inhalte protokolliert und nach '
			. Aufbewahrung::PROTOKOLL_TAGE . ' Tagen gelöscht.</p>'
		) );
	}

	/**
	 * Wer darf den Converter im Frontend benutzen? Wie lange werden Importe aufbewahrt?
	 */
	public function render_freigaben(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$benutzer      = get_users( [ 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name', 'user_email' ] ] );
		$freigegeben   = Zugriff::freigegeben();
		$gespeichert   = isset( $_GET['gespeichert'] );
		$aktion        = self::ACTION_FREIGABEN;
		$tage          = Aufbewahrung::tage();
		$protokoll     = $this->protokoll->neueste( 100 );
		$alt_sicherung = get_option( Activator::OPTION_ALT_SICHERUNG );
		include MH_USC_PLUGIN_DIR . 'templates/admin-freigaben.php';
	}

	public function handle_freigaben(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'Keine Berechtigung.', 403 );
		}
		check_admin_referer( self::ACTION_FREIGABEN );
		$ids = array_map( 'absint', (array) ( $_POST['user_ids'] ?? [] ) );
		Zugriff::speichern( $ids );
		Aufbewahrung::tage_speichern( absint( $_POST['aufbewahrung_tage'] ?? Aufbewahrung::STANDARD_TAGE ) );
		$this->protokoll->log( 'einstellungen', sprintf( '%d Personen freigegeben, Aufbewahrung %d Tage', count( array_filter( $ids ) ), Aufbewahrung::tage() ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::FREIGABEN_SLUG . '&gespeichert=1' ) );
		exit;
	}

	public function render_start(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$shortcode       = Plugin_Bootstrap::SHORTCODE;
		$frontend_aktiv  = shortcode_exists( $shortcode );
		$seiten          = $this->status_repo->pages_with_shortcode( $shortcode );
		$counts          = $this->status_repo->counts();
		$checks          = $this->checks( $counts, $frontend_aktiv, $seiten );
		$create_page_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_CREATE_PAGE ), self::ACTION_CREATE_PAGE );
		$fehler          = sanitize_key( wp_unslash( $_GET['usc_fehler'] ?? '' ) );

		include MH_USC_PLUGIN_DIR . 'templates/admin-start.php';
	}

	/**
	 * Legt einen Seitenentwurf mit dem Shortcode an und öffnet ihn im Editor.
	 */
	public function handle_create_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( 'Keine Berechtigung.', 403 );
		}
		check_admin_referer( self::ACTION_CREATE_PAGE );

		$id = wp_insert_post( [
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => 'Untis2Schild',
			'post_content' => "<!-- wp:shortcode -->\n[" . Plugin_Bootstrap::SHORTCODE . "]\n<!-- /wp:shortcode -->",
		], true );

		if ( is_wp_error( $id ) ) {
			wp_safe_redirect( add_query_arg( 'usc_fehler', 'seite', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
			exit;
		}
		wp_safe_redirect( (string) get_edit_post_link( $id, 'raw' ) );
		exit;
	}

	/**
	 * @param array<string, mixed> $counts
	 * @param array<int, object>   $seiten
	 * @return array<int, array{status:string, label:string, text:string, hint?:string}>
	 */
	private function checks( array $counts, bool $frontend_aktiv, array $seiten ): array {
		$checks = [];

		$checks[] = version_compare( PHP_VERSION, '8.1', '>=' )
			? [ 'status' => 'ok', 'label' => 'PHP', 'text' => 'Version ' . PHP_VERSION ]
			: [ 'status' => 'error', 'label' => 'PHP', 'text' => 'Version ' . PHP_VERSION . ' – benötigt wird 8.1 oder neuer.' ];

		$checks[] = class_exists( 'ZipArchive' )
			? [ 'status' => 'ok', 'label' => 'PHP-Erweiterung zip', 'text' => 'aktiv – Excel-Dateien können gelesen und geschrieben werden.' ]
			: [ 'status' => 'error', 'label' => 'PHP-Erweiterung zip', 'text' => 'fehlt – ohne sie lassen sich die Schild-Fächerliste nicht einlesen und keine Excel-Datei erzeugen.',
				'hint' => 'In der php.ini die Zeile extension=zip aktivieren und den Webserver neu starten.' ];

		$checks[] = get_option( Activator::OPTION_DB_VERSION ) === Activator::DB_VERSION
			? [ 'status' => 'ok', 'label' => 'Datenbank', 'text' => 'Tabellen auf Stand ' . Activator::DB_VERSION . '.' ]
			: [ 'status' => 'error', 'label' => 'Datenbank', 'text' => 'Tabellen nicht auf dem aktuellen Stand.', 'hint' => 'Plugin einmal deaktivieren und wieder aktivieren.' ];

		$migration = get_option( Activator::OPTION_MIGRATION );
		$checks[]  = [
			'status' => $counts['klassen_regeln'] + $counts['fach_regeln'] > 0 ? 'ok' : 'info',
			'label'  => 'Regeln',
			'text'   => sprintf( '%d Klassenregeln (%d aktiv), %d Fachregeln (%d aktiv).',
				$counts['klassen_regeln'], $counts['klassen_regeln_aktiv'], $counts['fach_regeln'], $counts['fach_regeln_aktiv'] ),
			'hint'   => is_array( $migration )
				? sprintf( 'Aus der Vorversion übernommen am %s: %d Klassen- und %d Fachregeln.',
					mysql2date( 'd.m.Y', (string) $migration['datum'] ), (int) $migration['klassen'], (int) $migration['faecher'] )
				: 'Regeln werden im Frontend gepflegt.',
		];

		$checks[] = $counts['schild_faecher'] > 0
			? [ 'status' => 'ok', 'label' => 'Schild-Fächerliste', 'text' => $counts['schild_faecher'] . ' Fächer importiert.',
				'hint' => 'Nach Änderungen an den Fächern in Schild die Liste neu importieren.' ]
			: [ 'status' => 'warn', 'label' => 'Schild-Fächerliste', 'text' => 'noch nicht importiert.',
				'hint' => 'Ohne Fächerliste kann nicht geprüft werden, ob Schild alle Fächer der Importdatei kennt.' ];

		if ( ! $frontend_aktiv ) {
			$checks[] = [ 'status' => 'error', 'label' => 'Frontend', 'text' => 'Der Shortcode ist nicht registriert.', 'hint' => 'Plugin deaktivieren und wieder aktivieren.' ];
		} elseif ( [] === $seiten ) {
			$checks[] = [ 'status' => 'warn', 'label' => 'Frontend', 'text' => 'Der Shortcode ist noch auf keiner Seite eingebunden.', 'hint' => 'Siehe Abschnitt „Einbinden“.' ];
		} else {
			$checks[] = [ 'status' => 'ok', 'label' => 'Frontend', 'text' => sprintf( 'Eingebunden auf %d %s.', count( $seiten ), 1 === count( $seiten ) ? 'Seite' : 'Seiten' ) ];
		}

		$checks[] = [
			'status' => 'info',
			'label'  => 'Importe',
			'text'   => $counts['importe'] > 0
				? sprintf( '%d GPU002-Importe, zuletzt am %s.', $counts['importe'], mysql2date( 'd.m.Y \u\m H:i', (string) $counts['letzter_import'] ) )
				: 'Noch keine GPU002 mit der neuen Version importiert.',
			'hint'   => sprintf( 'Es werden höchstens %d Importe aufbewahrt, jeder höchstens %d Tage.', Import_Repository::BEHALTEN, Aufbewahrung::tage() ),
		];

		array_push( $checks, ...$this->datenschutz_checks( $seiten ) );
		return $checks;
	}

	/**
	 * Datenschutz: Reste der Vorversion, Löschfristen, Freigaben, öffentlich erreichbare Seiten.
	 *
	 * @param array<int, object> $seiten
	 * @return array<int, array{status:string, label:string, text:string, hint?:string}>
	 */
	private function datenschutz_checks( array $seiten ): array {
		$checks = [];

		// Die Vorversion schrieb Exportdateien mit allen Lehrkraft-Kürzeln ungeschützt nach wp-admin/.
		$offen = array_values( array_filter( [ 'schildimport.csv', 'schildimport.xlsx' ], static fn( $d ) => is_file( ABSPATH . 'wp-admin/' . $d ) ) );
		$aufgeraeumt = get_option( Activator::OPTION_AUFGERAEUMT );
		if ( [] !== $offen ) {
			$checks[] = [ 'status' => 'error', 'label' => 'Altdateien', 'text' => 'Öffentlich abrufbar mit Lehrkraft-Kürzeln: wp-admin/' . implode( ', wp-admin/', $offen ),
				'hint' => 'Die Vorversion hat diese Dateien angelegt. Bitte sofort per FTP/Dateimanager löschen – das Plugin konnte es nicht (Schreibrechte).' ];
		} elseif ( is_array( $aufgeraeumt ) ) {
			$teile = [];
			if ( $aufgeraeumt['tabellen'] ) {
				$teile[] = count( $aufgeraeumt['tabellen'] ) . ' Alttabellen';
			}
			if ( $aufgeraeumt['dateien'] ) {
				$teile[] = 'öffentliche Exportdateien (' . implode( ', ', $aufgeraeumt['dateien'] ) . ')';
			}
			$checks[] = [ 'status' => 'ok', 'label' => 'Vorversion', 'text' => 'Reste der Vorversion entfernt am ' . mysql2date( 'd.m.Y', (string) $aufgeraeumt['datum'] ) . ': ' . implode( ', ', $teile ) . '.',
				'hint' => get_option( Activator::OPTION_ALT_SICHERUNG ) ? 'Die alten Regeln sind als Text gesichert (Freigaben & Datenschutz).' : '' ];
		}

		$checks[] = wp_next_scheduled( Aufbewahrung::CRON_HOOK )
			? [ 'status' => 'ok', 'label' => 'Löschfristen', 'text' => sprintf( 'Täglicher Lauf geplant: Importe nach %d Tagen, Protokoll nach %d Tagen.', Aufbewahrung::tage(), Aufbewahrung::PROTOKOLL_TAGE ) ]
			: [ 'status' => 'error', 'label' => 'Löschfristen', 'text' => 'Der tägliche Löschlauf ist nicht geplant.', 'hint' => 'Plugin deaktivieren und wieder aktivieren.' ];

		$frei     = count( Zugriff::freigegeben() );
		$checks[] = [ 'status' => 'info', 'label' => 'Zugriff', 'text' => sprintf( 'Administrator*innen und %d freigegebene %s.', $frei, 1 === $frei ? 'Person' : 'Personen' ),
			'hint' => 'Freigaben regelmäßig prüfen – z. B. wenn jemand die Verwaltung verlässt.' ];

		// Nicht auf „privat“ stellen: Private Seiten sehen nur Redakteure/Admins – Freigegebene mit
		// einfacher Rolle wären ausgesperrt. Der Schutz liegt im Shortcode selbst.
		if ( [] !== $seiten ) {
			$checks[] = [ 'status' => 'ok', 'label' => 'Seitenschutz', 'text' => 'Daten sieht nur, wer angemeldet und freigegeben ist. Die Seite wird nicht zwischengespeichert und ist für Suchmaschinen gesperrt.' ];
		}
		return $checks;
	}
}
