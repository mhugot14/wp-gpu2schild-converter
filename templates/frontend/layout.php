<?php
/**
 * View: Rahmen des Converters (Kopf, Reiter, Meldungen) – bindet den aktiven Reiter ein.
 *
 * Vom Converter_Controller::render() bereitgestellt (zusätzlich die Daten des Reiters):
 * @var string                 $tab
 * @var callable               $tab_url    fn( string $tab, array $args = [] ): string
 * @var ?object                $import     neuester Import oder null
 * @var ?Mh\UntisSchild\Model\Pipeline_Ergebnis $ergebnis
 * @var ?array                 $flash
 * @var callable               $formular   fn( string $aktion, string $ziel_tab ): void
 * @var callable               $benutzer   fn( int $id ): string
 * @var string                 $post_url
 */

use Mh\UntisSchild\Controller\Converter_Controller;

if ( ! defined( 'ABSPATH' ) ) exit;

$offen     = null !== $ergebnis ? count( $ergebnis->offene_konflikte() ) : 0;
$fehlend   = null !== $ergebnis ? count( $ergebnis->unbekannte_faecher ) : 0;
$eingabe   = $flash['eingabe'] ?? [];
// Formularwert nach einem Fehler wieder anzeigen.
$alt = static fn( string $feld, string $standard = '' ): string => isset( $eingabe[ $feld ] ) && is_string( $eingabe[ $feld ] ) ? $eingabe[ $feld ] : $standard;
?>
<?php include __DIR__ . '/partial-style.php'; ?>

<div class="mh-usc" id="usc">
	<header class="mh-usc-head">
		<span class="mh-usc-head-icon" aria-hidden="true">⇄</span>
		<div class="mh-usc-head-text">
			<h2 class="mh-usc-head-title">Untis2Schild</h2>
			<p class="mh-usc-head-sub">
				<?php if ( null !== $import ) : ?>
					GPU002 vom <?= esc_html( mysql2date( 'd.m.Y, H:i', $import->erstellt_am ) ) ?> Uhr ·
					Schuljahr <?= esc_html( Converter_Controller::schuljahr_text( (int) $import->schuljahr ) ) ?>, <?= (int) $import->halbjahr ?>. Halbjahr
				<?php else : ?>
					Noch keine GPU002 hochgeladen.
				<?php endif; ?>
			</p>
		</div>
		<?php if ( null !== $ergebnis ) : ?>
			<div class="mh-usc-chips">
				<a class="mh-usc-chip <?= $offen ? 'is-error' : 'is-ok' ?>" href="<?= esc_url( $tab_url( 'konflikte' ) ) ?>">
					<?= $offen ? esc_html( $offen . ' offene Konflikte' ) : '✓ Konflikte gelöst' ?>
				</a>
				<?php if ( $ergebnis->abgleich ) : ?>
					<a class="mh-usc-chip <?= $fehlend ? 'is-warn' : 'is-ok' ?>" href="<?= esc_url( $tab_url( 'schildfaecher' ) ) ?>">
						<?= $fehlend ? esc_html( $fehlend . ' Fächer fehlen in Schild' ) : '✓ Fächer in Schild' ?>
					</a>
				<?php else : ?>
					<a class="mh-usc-chip is-warn" href="<?= esc_url( $tab_url( 'schildfaecher' ) ) ?>">Schild-Fächerliste fehlt</a>
				<?php endif; ?>
				<a class="mh-usc-chip <?= $offen ? 'is-error' : 'is-ok' ?>" href="<?= esc_url( $tab_url( 'ergebnis' ) ) ?>">
					<?= $offen ? 'Export gesperrt' : esc_html( count( $ergebnis->zeilen ) . ' Zeilen exportbereit' ) ?>
				</a>
			</div>
		<?php endif; ?>
	</header>

	<nav class="mh-usc-tabs" aria-label="Bereiche">
		<?php foreach ( Converter_Controller::TABS as $key => $titel ) : ?>
			<a href="<?= esc_url( $tab_url( $key ) ) ?>" class="<?= $key === $tab ? 'is-active' : '' ?>" <?= $key === $tab ? 'aria-current="page"' : '' ?>>
				<?= esc_html( $titel ) ?>
				<?php if ( 'konflikte' === $key && $offen ) : ?><span class="mh-usc-count is-red"><?= (int) $offen ?></span><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( null !== $flash ) : ?>
		<div class="mh-usc-flash <?= 'fehler' === $flash['typ'] ? 'is-error' : '' ?>" role="<?= 'fehler' === $flash['typ'] ? 'alert' : 'status' ?>">
			<?= esc_html( $flash['text'] ) ?>
			<?php if ( ! empty( $flash['fehler'] ) ) : ?>
				<ul><?php foreach ( $flash['fehler'] as $f ) : ?><li><?= esc_html( (string) $f ) ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php include __DIR__ . '/partial-bausteine-js.php'; // vor dem Reiter, dessen Skripte es brauchen ?>
	<?php include __DIR__ . '/tab-' . $tab . '.php'; ?>
</div>

<script>
( function () {
	var root = document.getElementById( 'usc' );
	if ( ! root ) { return; }

	// Sicherheitsabfrage vor Löschen o. Ä.
	root.querySelectorAll( 'form[data-confirm]' ).forEach( function ( f ) {
		f.addEventListener( 'submit', function ( e ) {
			if ( ! window.confirm( f.dataset.confirm ) ) { e.preventDefault(); }
		} );
	} );

	// Tabellen durchsuchen.
	root.querySelectorAll( 'input[data-filter]' ).forEach( function ( input ) {
		var ziel = root.querySelector( input.dataset.filter );
		if ( ! ziel ) { return; }
		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase();
			ziel.querySelectorAll( '[data-suche]' ).forEach( function ( el ) {
				el.classList.toggle( 'mh-usc-hidden', q !== '' && el.dataset.suche.toLowerCase().indexOf( q ) === -1 );
			} );
		} );
	} );

	// Live-Vorschau eines Musters – dieselbe Logik wie Service\Muster.
	function regexe( muster ) {
		var teile = muster.split( ',' ).map( function ( t ) { return t.trim(); } ).filter( Boolean );
		if ( ! teile.length || teile.indexOf( '*' ) > -1 || teile.indexOf( '%' ) > -1 ) { return null; }
		return teile.map( function ( t ) {
			return new RegExp( '^' + t.replace( /[.+?^${}()|[\]\\]/g, '\\$&' ).replace( /[*%]/g, '.*' ) + '$', 'i' );
		} );
	}
	root.querySelectorAll( 'input[data-preview]' ).forEach( function ( input ) {
		var ausgabe = root.querySelector( input.dataset.preview );
		var werte   = JSON.parse( input.dataset.werte || '[]' );
		var trenner = input.dataset.trenner || ',';
		if ( ! ausgabe || ! werte.length ) { return; }
		function zeige() {
			var text = input.value;
			if ( trenner === '|' ) { text = text.split( '|' ).join( ',' ); }
			if ( text.trim() === '' ) { ausgabe.innerHTML = ''; return; }
			var re = regexe( text );
			var treffer = re === null ? werte : werte.filter( function ( w ) { return re.some( function ( r ) { return r.test( w ); } ); } );
			ausgabe.classList.toggle( 'is-none', treffer.length === 0 );
			var liste = treffer.slice( 0, 12 ).join( ', ' ) + ( treffer.length > 12 ? ' …' : '' );
			ausgabe.innerHTML = '';
			var b = document.createElement( 'b' );
			b.textContent = re === null ? 'alle ' + werte.length : treffer.length + ' von ' + werte.length;
			ausgabe.appendChild( document.createTextNode( 'Im aktuellen Import: ' ) );
			ausgabe.appendChild( b );
			ausgabe.appendChild( document.createTextNode( treffer.length && re !== null ? ' – ' + liste : ( treffer.length ? '' : ' – kein Treffer' ) ) );
		}
		input.addEventListener( 'input', zeige );
		zeige();
	} );

	// Text kopieren.
	root.querySelectorAll( '[data-copy]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var quelle = root.querySelector( btn.dataset.copy );
			navigator.clipboard.writeText( quelle.value !== undefined ? quelle.value : quelle.textContent ).then( function () {
				var alt = btn.textContent; btn.textContent = 'Kopiert ✓';
				setTimeout( function () { btn.textContent = alt; }, 1600 );
			} );
		} );
	} );
} )();
</script>
