<?php
/**
 * View: Reiter „Konflikte“ – Fächer mit mehreren Lehrkräften in einer Klasse.
 *
 * @var Mh\UntisSchild\Model\Konflikt[] $konflikte   offene zuerst
 * @var object[]                         $gespeichert gemerkte Auswahlen (id, klasse, fach, lehrer, entschieden_von, entschieden_am)
 * (+ Variablen aus layout.php)
 */

use Mh\UntisSchild\Model\Konflikt;
use Mh\UntisSchild\Service\Pipeline;

if ( ! defined( 'ABSPATH' ) ) exit;

$offen     = array_filter( $konflikte, static fn( Konflikt $k ) => $k->ist_offen() );
$genutzt   = [];
foreach ( $konflikte as $k ) {
	if ( Konflikt::GRUND_ENTSCHEIDUNG === $k->grund ) {
		$genutzt[ Pipeline::schluessel( $k->klasse, $k->fach ) ] = true;
	}
}
$status = [
	Konflikt::GRUND_OFFEN         => [ 'offen – bitte auswählen', 'is-red' ],
	Konflikt::GRUND_WOCHENSTUNDEN => [ 'automatisch: meiste Stunden', 'is-blue' ],
	Konflikt::GRUND_ENTSCHEIDUNG  => [ 'gespeicherte Auswahl', 'is-green' ],
];
?>
<p class="mh-usc-intro">
	Schild nimmt je Klasse und Fach nur eine Lehrkraft auf. Unterrichten mehrere, wählt das Plugin die Lehrkraft mit den meisten
	Wochenstunden. Bei Gleichstand musst du auswählen – solange ein Konflikt offen ist, ist der Export gesperrt. Deine Auswahl wird
	gespeichert und auch bei künftigen Importen verwendet.
</p>

<?php if ( null === $ergebnis ) : ?>
	<div class="mh-usc-notice is-info">Zuerst eine GPU002 hochladen.</div>
<?php elseif ( [] === $konflikte ) : ?>
	<div class="mh-usc-notice is-info">Im aktuellen Import wird jedes Fach in jeder Klasse von genau einer Lehrkraft unterrichtet.</div>
<?php else : ?>
	<div class="mh-usc-stats">
		<div class="mh-usc-stat"><b style="color:<?= $offen ? 'var(--u-red)' : 'var(--u-green)' ?>"><?= count( $offen ) ?></b><span>offen</span></div>
		<div class="mh-usc-stat"><b><?= count( array_filter( $konflikte, static fn( $k ) => Konflikt::GRUND_WOCHENSTUNDEN === $k->grund ) ) ?></b><span>automatisch nach Stunden</span></div>
		<div class="mh-usc-stat"><b><?= count( array_filter( $konflikte, static fn( $k ) => Konflikt::GRUND_ENTSCHEIDUNG === $k->grund ) ) ?></b><span>gespeicherte Auswahl</span></div>
	</div>

	<form method="post" action="<?= esc_url( $post_url ) ?>" id="usc-konflikte">
		<?php $formular( 'usc_konflikte', 'konflikte' ); ?>
		<section class="mh-usc-card">
			<div class="mh-usc-card-head">
				<h3>Fächer mit mehreren Lehrkräften (<?= count( $konflikte ) ?>)</h3>
				<input type="search" class="mh-usc-search" style="max-width:240px;min-height:34px;padding:4px 10px;font-size:14px"
					placeholder="Klasse, Fach, Lehrkraft, „offen“ …" data-filter="#usc-k-tabelle" aria-label="Konflikte durchsuchen">
			</div>
			<div class="mh-usc-card-body">
				<div class="mh-usc-scroll">
					<table class="mh-usc-table is-stack" id="usc-k-tabelle">
						<thead><tr><th>Klasse</th><th>Fach</th><th>Lehrkraft (Wochenstunden)</th><th>Status</th></tr></thead>
						<tbody>
							<?php foreach ( $konflikte as $k ) :
								$key = Pipeline::schluessel( $k->klasse, $k->fach );
								$st  = $status[ $k->grund ];
								?>
								<tr class="<?= $k->ist_offen() ? 'is-offen' : '' ?>"
									data-suche="<?= esc_attr( $k->klasse . ' ' . $k->fach . ' ' . implode( ' ', array_keys( $k->kandidaten ) ) . ' ' . $st[0] ) ?>">
									<td data-l="Klasse"><b><?= esc_html( $k->klasse ) ?></b></td>
									<td data-l="Fach"><code><?= esc_html( $k->fach ) ?></code></td>
									<td>
										<div class="mh-usc-wahl">
											<?php foreach ( $k->kandidaten as $lehrer => $std ) : $lehrer = (string) $lehrer; ?>
												<label class="<?= $lehrer === $k->lehrer ? 'is-checked' : '' ?>">
													<input type="radio" name="wahl[<?= esc_attr( $key ) ?>]" value="<?= esc_attr( $lehrer ) ?>" <?php checked( $lehrer, (string) $k->lehrer ); ?>>
													<?= esc_html( $lehrer ) ?> <small><?= esc_html( Pipeline::zahl( (float) $std ) ) ?> Std.</small>
												</label>
											<?php endforeach; ?>
										</div>
									</td>
									<td data-l="Status"><span class="mh-usc-badge <?= esc_attr( $st[1] ) ?>"><?= esc_html( $st[0] ) ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="mh-usc-save-bar">
					<button type="submit" class="mh-usc-btn mh-usc-btn-primary">Auswahl speichern</button>
					<span class="mh-usc-dirty" id="usc-dirty">Ungespeicherte Änderungen</span>
					<span class="mh-usc-small mh-usc-muted">Gespeichert werden nur Auswahlen, die von der automatischen Wahl abweichen.</span>
				</div>
			</div>
		</section>
	</form>
	<script>
	( function () {
		var form = document.getElementById( 'usc-konflikte' );
		form.addEventListener( 'change', function ( e ) {
			if ( e.target.type !== 'radio' ) { return; }
			e.target.closest( '.mh-usc-wahl' ).querySelectorAll( 'label' ).forEach( function ( l ) {
				l.classList.toggle( 'is-checked', l.contains( e.target ) );
			} );
			document.getElementById( 'usc-dirty' ).classList.add( 'is-on' );
		} );
	} )();
	</script>
<?php endif; ?>

<?php if ( [] !== $gespeichert ) : ?>
	<details class="mh-usc-card">
		<summary class="mh-usc-card-head"><h3>Gespeicherte Auswahlen (<?= count( $gespeichert ) ?>)</h3></summary>
		<div class="mh-usc-card-body">
			<p class="mh-usc-small mh-usc-muted">Eine Auswahl, die im aktuellen Import nicht vorkommt, bleibt für spätere Importe erhalten.
				Entfernen heißt: Es gilt wieder die automatische Wahl.</p>
			<table class="mh-usc-table is-stack">
				<thead><tr><th>Klasse</th><th>Fach</th><th>Lehrkraft</th><th>gespeichert</th><th>im Import</th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $gespeichert as $g ) : ?>
						<tr>
							<td data-l="Klasse"><?= esc_html( $g->klasse ) ?></td>
							<td data-l="Fach"><code><?= esc_html( $g->fach ) ?></code></td>
							<td data-l="Lehrkraft"><b><?= esc_html( $g->lehrer ) ?></b></td>
							<td data-l="gespeichert" class="mh-usc-small mh-usc-muted"><?= esc_html( $benutzer( (int) $g->entschieden_von ) ) ?>, <?= esc_html( mysql2date( 'd.m.Y', $g->entschieden_am ) ) ?></td>
							<td data-l="im Import">
								<?= isset( $genutzt[ Pipeline::schluessel( $g->klasse, $g->fach ) ] )
									? '<span class="mh-usc-badge is-green">verwendet</span>'
									: '<span class="mh-usc-badge is-grey">nicht verwendet</span>' ?>
							</td>
							<td class="is-actions">
								<form method="post" action="<?= esc_url( $post_url ) ?>">
									<?php $formular( 'usc_entscheidung_loeschen', 'konflikte' ); ?>
									<input type="hidden" name="id" value="<?= (int) $g->id ?>">
									<button type="submit" class="mh-usc-btn mh-usc-btn-small mh-usc-btn-danger">Entfernen</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</details>
<?php endif; ?>
