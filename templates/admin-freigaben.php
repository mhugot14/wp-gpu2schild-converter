<?php
/**
 * View: Freigaben & Datenschutz im Backend.
 *
 * @var object[]    $benutzer      (ID, display_name, user_email)
 * @var int[]       $freigegeben
 * @var bool        $gespeichert
 * @var string      $aktion
 * @var int         $tage          Aufbewahrungsfrist für Importe
 * @var object[]    $protokoll     neueste Einträge des Zugriffsprotokolls
 * @var array|false $alt_sicherung Regeln der Vorversion als Text
 */

use Mh\UntisSchild\Repository\Import_Repository;
use Mh\UntisSchild\Service\Aufbewahrung;

if ( ! defined( 'ABSPATH' ) ) exit;

$aktionen = [
	'import'              => 'GPU002 importiert',
	'import_geaendert'    => 'Import: Schuljahr/Halbjahr geändert',
	'import_geloescht'    => 'Import gelöscht',
	'export'              => 'Schild-Datei heruntergeladen',
	'auswahl_gespeichert' => 'Lehrkraft-Auswahl gespeichert',
	'auswahl_entfernt'    => 'Lehrkraft-Auswahl entfernt',
	'schildfaecher'       => 'Schild-Fächerliste eingelesen',
	'einstellungen'       => 'Freigaben/Einstellungen geändert',
	'aufbewahrung'        => 'Automatische Löschung',
];
$name = static function ( int $id ): string {
	if ( 0 === $id ) {
		return 'System';
	}
	$u = get_userdata( $id );
	return $u ? (string) $u->display_name : 'gelöschte Person #' . $id;
};
?>
<style>
	.usc-frei { max-width: 860px; }
	.usc-frei h2 { margin-top: 28px; }
	.usc-frei-liste { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; max-height: 420px; overflow-y: auto; margin: 10px 0 16px; }
	.usc-frei-liste label { display: flex; gap: 10px; align-items: center; padding: 8px 14px; border-bottom: 1px solid #f0f0f1; margin: 0; }
	.usc-frei-liste label:last-child { border-bottom: 0; }
	.usc-frei-liste label.is-admin { color: #646970; }
	.usc-frei-liste small { color: #646970; margin-left: auto; }
	.usc-frei-liste .is-hidden { display: none; }
	.usc-frei-box { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 14px 18px; }
	.usc-frei-box p:first-child { margin-top: 0; }
	.usc-frei table.widefat td, .usc-frei table.widefat th { padding: 7px 10px; }
</style>
<div class="wrap usc-frei">
	<h1>Untis2Schild – Freigaben &amp; Datenschutz</h1>
	<?php if ( $gespeichert ) : ?>
		<div class="notice notice-success is-dismissible"><p>Gespeichert.</p></div>
	<?php endif; ?>

	<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
		<input type="hidden" name="action" value="<?= esc_attr( $aktion ) ?>">
		<?php wp_nonce_field( $aktion ); ?>

		<h2>Wer darf den Converter benutzen?</h2>
		<p>Die Unterrichtsverteilung enthält Lehrkraft-Kürzel – personenbezogene Daten der Beschäftigten. Freigeben nur, wer sie für die
			Übertragung nach Schild braucht (Grundsatz der Erforderlichkeit). Administratorinnen und Administratoren haben immer Zugriff.</p>
		<input type="search" id="usc-frei-suche" class="regular-text" placeholder="Name oder E-Mail suchen …" aria-label="Benutzer suchen">
		<div class="usc-frei-liste" id="usc-frei-liste">
			<?php foreach ( $benutzer as $u ) : $admin = user_can( (int) $u->ID, 'manage_options' ); ?>
				<label class="<?= $admin ? 'is-admin' : '' ?>" data-suche="<?= esc_attr( mb_strtolower( $u->display_name . ' ' . $u->user_email ) ) ?>">
					<?php if ( $admin ) : ?>
						<input type="checkbox" checked disabled>
					<?php else : ?>
						<input type="checkbox" name="user_ids[]" value="<?= (int) $u->ID ?>" <?php checked( in_array( (int) $u->ID, $freigegeben, true ) ); ?>>
					<?php endif; ?>
					<?= esc_html( $u->display_name ) ?>
					<small><?= $admin ? 'Administrator*in – immer freigegeben' : esc_html( $u->user_email ) ?></small>
				</label>
			<?php endforeach; ?>
		</div>

		<h2>Aufbewahrung</h2>
		<div class="usc-frei-box">
			<p>
				<label for="usc-tage"><strong>Importe löschen nach</strong></label>
				<input type="number" id="usc-tage" name="aufbewahrung_tage" value="<?= (int) $tage ?>" min="<?= (int) Aufbewahrung::MIN_TAGE ?>" max="<?= (int) Aufbewahrung::MAX_TAGE ?>" style="width:90px"> Tagen
			</p>
			<p class="description">
				Zusätzlich werden höchstens die letzten <?= (int) Import_Repository::BEHALTEN ?> Importe aufbewahrt. Ein Import wird für ein Halbjahr gebraucht;
				365 Tage decken ein Schuljahr ab. Das Zugriffsprotokoll wird nach <?= (int) Aufbewahrung::PROTOKOLL_TAGE ?> Tagen gelöscht.
				Hochgeladene Dateien werden nie aufbewahrt, die Schild-Datei wird beim Herunterladen erzeugt und nicht gespeichert.
			</p>
		</div>

		<?php submit_button( 'Speichern' ); ?>
	</form>

	<h2>Zugriffsprotokoll</h2>
	<p class="description">Wer hat wann importiert, exportiert oder Lehrkräfte ausgewählt – ohne Inhalte. Die letzten 100 Einträge.</p>
	<?php if ( [] === $protokoll ) : ?>
		<p>Noch keine Einträge.</p>
	<?php else : ?>
		<table class="widefat striped">
			<thead><tr><th style="width:140px">Zeit</th><th style="width:180px">Person</th><th>Aktion</th><th>Details</th></tr></thead>
			<tbody>
				<?php foreach ( $protokoll as $e ) : ?>
					<tr>
						<td><?= esc_html( mysql2date( 'd.m.Y H:i', $e->zeit ) ) ?></td>
						<td><?= esc_html( $name( (int) $e->user_id ) ) ?></td>
						<td><?= esc_html( $aktionen[ $e->aktion ] ?? $e->aktion ) ?></td>
						<td><?= esc_html( $e->detail ) ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( is_array( $alt_sicherung ) ) : ?>
		<h2>Regeln der Vorversion (Sicherung)</h2>
		<p class="description">Vor dem Entfernen der Alttabellen am <?= esc_html( mysql2date( 'd.m.Y', (string) $alt_sicherung['datum'] ) ) ?> gesichert.
			Die Regeln wurden übernommen; diese Sicherung braucht es nur, falls etwas fehlt. Im Format für „Mehrere Regeln als Text einfügen“.</p>
		<?php foreach ( [ 'klassen' => 'Klassenregeln', 'faecher' => 'Fachregeln' ] as $art => $titel ) : if ( empty( $alt_sicherung[ $art ] ) ) continue; ?>
			<h3><?= esc_html( $titel ) ?></h3>
			<textarea readonly rows="6" class="large-text code"><?= esc_textarea( (string) $alt_sicherung[ $art ] ) ?></textarea>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<script>
	document.getElementById( 'usc-frei-suche' ).addEventListener( 'input', function () {
		var q = this.value.trim().toLowerCase();
		document.querySelectorAll( '#usc-frei-liste label' ).forEach( function ( l ) {
			l.classList.toggle( 'is-hidden', q !== '' && l.dataset.suche.indexOf( q ) === -1 );
		} );
	} );
</script>
