<?php
/**
 * View: Freigaben im Backend – wer den Converter im Frontend benutzen darf.
 *
 * @var object[] $benutzer    (ID, display_name, user_email)
 * @var int[]    $freigegeben
 * @var bool     $gespeichert
 * @var string   $aktion
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<style>
	.usc-frei { max-width: 760px; }
	.usc-frei-liste { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; max-height: 520px; overflow-y: auto; margin: 10px 0 16px; }
	.usc-frei-liste label { display: flex; gap: 10px; align-items: center; padding: 8px 14px; border-bottom: 1px solid #f0f0f1; margin: 0; }
	.usc-frei-liste label:last-child { border-bottom: 0; }
	.usc-frei-liste label.is-admin { color: #646970; }
	.usc-frei-liste small { color: #646970; margin-left: auto; }
	.usc-frei-liste .is-hidden { display: none; }
</style>
<div class="wrap usc-frei">
	<h1>Untis2Schild – Freigaben</h1>
	<?php if ( $gespeichert ) : ?>
		<div class="notice notice-success is-dismissible"><p>Freigaben gespeichert.</p></div>
	<?php endif; ?>
	<p>Administratorinnen und Administratoren dürfen den Converter immer benutzen. Hier schaltest du weitere Personen frei,
		z. B. aus der Verwaltung. Sie sehen den Converter auf der Seite mit dem Shortcode und brauchen keinen Zugang zum Backend.</p>

	<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
		<input type="hidden" name="action" value="<?= esc_attr( $aktion ) ?>">
		<?php wp_nonce_field( $aktion ); ?>
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
		<?php submit_button( 'Freigaben speichern' ); ?>
	</form>
</div>
<script>
	document.getElementById( 'usc-frei-suche' ).addEventListener( 'input', function () {
		var q = this.value.trim().toLowerCase();
		document.querySelectorAll( '#usc-frei-liste label' ).forEach( function ( l ) {
			l.classList.toggle( 'is-hidden', q !== '' && l.dataset.suche.indexOf( q ) === -1 );
		} );
	} );
</script>
