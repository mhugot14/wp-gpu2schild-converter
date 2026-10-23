<?php

declare(strict_types=1);

namespace Mh\UntisSchild\Setup;

use Mh\UntisSchild\Controller\Admin_Controller;
use Mh\UntisSchild\Controller\Converter_Controller;
use Mh\UntisSchild\Repository\Entscheidung_Repository;
use Mh\UntisSchild\Repository\Fach_Regel_Repository;
use Mh\UntisSchild\Repository\Import_Repository;
use Mh\UntisSchild\Repository\Klassen_Regel_Repository;
use Mh\UntisSchild\Repository\Protokoll_Repository;
use Mh\UntisSchild\Service\Aufbewahrung;
use Mh\UntisSchild\Repository\Schild_Fach_Repository;
use Mh\UntisSchild\Repository\Status_Repository;
use Mh\UntisSchild\Service\Converter_Service;
use Mh\UntisSchild\Service\Excel_Service;
use Mh\UntisSchild\Service\Gpu_Parser;
use Mh\UntisSchild\Service\Pipeline;
use Mh\UntisSchild\Service\Regel_Pruefer;

/**
 * Class Plugin_Bootstrap
 *
 * Verdrahtet Controller, Repositories und Services (manuell, ohne DI-Container) und
 * registriert die Hooks. Neue Abhängigkeiten hier instanziieren UND in der
 * Konstruktor-Signatur des Controllers ergänzen (Reihenfolge beachten).
 */
class Plugin_Bootstrap {

	/** Shortcode des Frontends – bei Änderungen templates/admin-start.php mitpflegen. */
	public const SHORTCODE = 'mh_untis_schild';

	public function init(): void {
		global $wpdb;

		$imports        = new Import_Repository( $wpdb );
		$klassen_regeln = new Klassen_Regel_Repository( $wpdb );
		$fach_regeln    = new Fach_Regel_Repository( $wpdb );
		$entscheidungen = new Entscheidung_Repository( $wpdb );
		$schild_faecher = new Schild_Fach_Repository( $wpdb );

		$converter = new Converter_Service( $imports, $klassen_regeln, $fach_regeln, $entscheidungen, $schild_faecher, new Pipeline() );

		$protokoll = new Protokoll_Repository( $wpdb );

		// Täglicher Löschlauf (Speicherbegrenzung).
		add_action( Aufbewahrung::CRON_HOOK, [ new Aufbewahrung( $imports, $protokoll ), 'ausfuehren' ] );

		( new Converter_Controller(
			$imports,
			$klassen_regeln,
			$fach_regeln,
			$entscheidungen,
			$schild_faecher,
			$converter,
			new Gpu_Parser(),
			new Excel_Service(),
			new Regel_Pruefer(),
			$protokoll,
		) )->register();

		if ( is_admin() ) {
			( new Admin_Controller( new Status_Repository( $wpdb ), $protokoll ) )->register();
		}
	}
}
