<?php
/**
 * Partial: Ergebnis des Regelprüfers über der Regelliste.
 *
 * @var string                                  $typ      klassen|faecher
 * @var Mh\UntisSchild\Model\Regel_Befund[]     $befunde
 * @var array<int, int>                         $nummern  Regel-ID => Nr. in der Liste
 * @var array<int, Mh\UntisSchild\Model\Klassen_Regel|Mh\UntisSchild\Model\Fach_Regel> $regeln
 * (+ $formular, $post_url aus layout.php)
 */

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Model\Regel_Befund as B;
use Mh\UntisSchild\Service\Regel_Text;

if ( ! defined( 'ABSPATH' ) ) exit;

$nach_id = [];
foreach ( $regeln as $r ) {
	$nach_id[ $r->id ] = $r;
}
$liste_id = 'faecher' === $typ ? '#usc-f-liste' : '#usc-k-liste';
$satz     = static fn( $r ): string => $r instanceof Fach_Regel ? Regel_Text::fach_satz( $r ) : Regel_Text::klassen_satz( $r );
$verweis  = static fn( int $id ): string => sprintf( '<a href="#usc-regel-%d">Regel %d</a>', $id, $nummern[ $id ] ?? 0 );
$knopf    = static function ( string $was, int $id, string $text, array $extra = [], string $frage = '' ) use ( $formular, $post_url, $typ ): void {
	?>
	<form method="post" action="<?= esc_url( $post_url ) ?>" <?= '' !== $frage ? 'data-confirm="' . esc_attr( $frage ) . '"' : '' ?>>
		<?php $formular( 'usc_regel_aktion', $typ ); ?>
		<input type="hidden" name="typ" value="<?= esc_attr( $typ ) ?>">
		<input type="hidden" name="id" value="<?= (int) $id ?>">
		<input type="hidden" name="was" value="<?= esc_attr( $was ) ?>">
		<?php foreach ( $extra as $k => $v ) : ?><input type="hidden" name="<?= esc_attr( $k ) ?>" value="<?= esc_attr( (string) $v ) ?>"><?php endforeach; ?>
		<button type="submit" class="mh-usc-btn mh-usc-btn-small <?= 'loeschen' === $was ? 'mh-usc-btn-danger' : '' ?>"><?= esc_html( $text ) ?></button>
	</form>
	<?php
};

$gruppen = [];
foreach ( $befunde as $b ) {
	$gruppen[ $b->typ ][] = $b;
}
$wichtig = array_intersect_key( $gruppen, array_flip( [ B::DOPPELT, B::WIRKUNGSLOS, B::ZUSAMMENFASSBAR, B::SCHILD_UNBEKANNT ] ) );
$zusammen_vorher  = array_sum( array_map( static fn( $b ) => count( $b->regel_ids ), $gruppen[ B::ZUSAMMENFASSBAR ] ?? [] ) );
$ohne_treffer_ids = $gruppen[ B::OHNE_TREFFER ][0]->regel_ids ?? [];
?>
<details class="mh-usc-card" id="usc-pruefung" <?= [] !== $wichtig ? 'open' : '' ?>>
	<summary class="mh-usc-card-head">
		<h3>Regelprüfung</h3>
		<?php if ( [] === $befunde ) : ?>
			<span class="mh-usc-badge is-green">keine Auffälligkeiten</span>
		<?php endif; ?>
		<?php if ( isset( $gruppen[ B::DOPPELT ] ) ) : ?><span class="mh-usc-badge is-red"><?= count( $gruppen[ B::DOPPELT ] ) ?> doppelt</span><?php endif; ?>
		<?php if ( isset( $gruppen[ B::WIRKUNGSLOS ] ) ) : ?><span class="mh-usc-badge is-red"><?= count( $gruppen[ B::WIRKUNGSLOS ] ) ?> wirkungslos</span><?php endif; ?>
		<?php if ( isset( $gruppen[ B::ZUSAMMENFASSBAR ] ) ) : ?><span class="mh-usc-badge is-blue"><?= (int) $zusammen_vorher ?> Regeln → <?= count( $gruppen[ B::ZUSAMMENFASSBAR ] ) ?> zusammenfassbar</span><?php endif; ?>
		<?php if ( isset( $gruppen[ B::SCHILD_UNBEKANNT ] ) ) : ?><span class="mh-usc-badge is-amber"><?= count( $gruppen[ B::SCHILD_UNBEKANNT ] ) ?> mit unbekanntem Schild-Fach</span><?php endif; ?>
		<?php if ( $ohne_treffer_ids ) : ?><span class="mh-usc-badge is-grey"><?= count( $ohne_treffer_ids ) ?> ohne Treffer</span><?php endif; ?>
	</summary>
	<div class="mh-usc-card-body">
		<?php if ( [] === $befunde ) : ?>
			<p class="mh-usc-muted">Keine doppelten, widersprüchlichen oder zusammenfassbaren Regeln gefunden.</p>
		<?php endif; ?>

		<?php foreach ( $gruppen[ B::DOPPELT ] ?? [] as $b ) : $id = $b->regel_ids[0]; ?>
			<div class="mh-usc-befund is-red">
				<p><b><?= $verweis( $id ) // phpcs:ignore ?> ist doppelt:</b> <?= $verweis( (int) $b->bezug_id ) // phpcs:ignore ?> weiter oben macht dasselbe, diese Regel kommt nie zum Zug.</p>
				<p class="mh-usc-small mh-usc-muted"><?= esc_html( $satz( $nach_id[ $id ] ) ) ?></p>
				<div class="mh-usc-actions" style="margin-top:6px"><?php $knopf( 'loeschen', $id, 'Regel ' . ( $nummern[ $id ] ?? '' ) . ' löschen', [], 'Die doppelte Regel löschen?' ); ?></div>
			</div>
		<?php endforeach; ?>

		<?php foreach ( $gruppen[ B::WIRKUNGSLOS ] ?? [] as $b ) : $id = $b->regel_ids[0]; $bezug = (int) $b->bezug_id; ?>
			<div class="mh-usc-befund is-red">
				<p><b><?= $verweis( $id ) // phpcs:ignore ?> wirkt nie<?= $b->zeilen ? ' (passt auf ' . (int) $b->zeilen . ' Zeilen)' : '' ?>:</b>
					<?= $verweis( $bezug ) // phpcs:ignore ?> steht weiter oben, erfasst dieselben Zeilen zuerst und macht etwas anderes.</p>
				<ul class="mh-usc-small mh-usc-muted" style="margin:0 0 0 18px">
					<li>oben: <?= esc_html( $satz( $nach_id[ $bezug ] ) ) ?></li>
					<li>wirkungslos: <?= esc_html( $satz( $nach_id[ $id ] ) ) ?></li>
				</ul>
				<p class="mh-usc-small" style="margin:6px 0 0">Ist die untere Regel eine Ausnahme, gehört sie über die allgemeine Regel.</p>
				<div class="mh-usc-actions" style="margin-top:6px">
					<?php $knopf( 'vor', $id, 'Regel ' . ( $nummern[ $id ] ?? '' ) . ' vor Regel ' . ( $nummern[ $bezug ] ?? '' ) . ' setzen', [ 'vor_id' => $bezug ] ); ?>
					<?php $knopf( 'loeschen', $id, 'Regel ' . ( $nummern[ $id ] ?? '' ) . ' löschen', [], 'Die wirkungslose Regel löschen?' ); ?>
				</div>
			</div>
		<?php endforeach; ?>

		<?php if ( isset( $gruppen[ B::ZUSAMMENFASSBAR ] ) ) : ?>
			<h4 class="mh-usc-befund-titel">Zusammenfassen</h4>
			<p class="mh-usc-small mh-usc-muted">Diese Regeln haben dieselbe Wirkung und lassen sich zu je einer Regel zusammenfassen – das macht die Liste kürzer.
				Vor dem Speichern wird mit dem aktuellen Import nachgerechnet; ändert sich das Ergebnis, wird nicht zusammengefasst.</p>
			<?php foreach ( $gruppen[ B::ZUSAMMENFASSBAR ] as $b ) : ?>
				<div class="mh-usc-befund">
					<p><b><?= count( $b->regel_ids ) ?> Regeln → 1:</b> <?= esc_html( $satz( $b->vorschlag ) ) ?></p>
					<details class="mh-usc-small">
						<summary style="cursor:pointer">betrifft <?= implode( ', ', array_map( $verweis, $b->regel_ids ) ) // phpcs:ignore ?></summary>
						<ul style="margin:4px 0 0 18px"><?php foreach ( $b->regel_ids as $rid ) : ?><li><?= esc_html( $satz( $nach_id[ $rid ] ) ) ?></li><?php endforeach; ?></ul>
					</details>
					<form method="post" action="<?= esc_url( $post_url ) ?>" class="mh-usc-actions" style="margin-top:6px">
						<?php $formular( 'usc_regeln_zusammenfassen', $typ ); ?>
						<input type="hidden" name="typ" value="<?= esc_attr( $typ ) ?>">
						<?php foreach ( $b->regel_ids as $rid ) : ?><input type="hidden" name="ids[]" value="<?= (int) $rid ?>"><?php endforeach; ?>
						<button type="submit" class="mh-usc-btn mh-usc-btn-small">Zusammenfassen</button>
					</form>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php foreach ( $gruppen[ B::SCHILD_UNBEKANNT ] ?? [] as $b ) : $id = $b->regel_ids[0]; ?>
			<div class="mh-usc-befund is-amber">
				<p><b><?= $verweis( $id ) // phpcs:ignore ?></b> erzeugt <?= esc_html( implode( ', ', $b->details ) ) ?> – <?= 1 === count( $b->details ) ? 'steht' : 'stehen' ?> nicht in der Schild-Fächerliste.
					Tippfehler, oder fehlt das Fach in Schild?</p>
				<div class="mh-usc-actions" style="margin-top:6px"><a class="mh-usc-btn mh-usc-btn-small" href="<?= esc_url( $tab_url( $typ, [ 'usc_bearbeiten' => $id ] ) ) ?>">Regel <?= (int) ( $nummern[ $id ] ?? 0 ) ?> bearbeiten</a></div>
			</div>
		<?php endforeach; ?>

		<?php if ( $ohne_treffer_ids ) : ?>
			<div class="mh-usc-befund is-grey">
				<p><b><?= count( $ohne_treffer_ids ) ?> Regeln treffen im aktuellen Import nichts.</b> Sie schaden nicht – vielleicht gelten sie für andere
					Bildungsgänge oder Jahre, oder sie sind veraltet.</p>
				<div class="mh-usc-actions" style="margin-top:6px">
					<button type="button" class="mh-usc-btn mh-usc-btn-small" data-nur-ids="<?= esc_attr( implode( ',', $ohne_treffer_ids ) ) ?>" data-liste="<?= esc_attr( $liste_id ) ?>">Nur diese in der Liste zeigen</button>
					<button type="button" class="mh-usc-btn mh-usc-btn-small" data-nur-ids="" data-liste="<?= esc_attr( $liste_id ) ?>">Alle zeigen</button>
				</div>
			</div>
		<?php endif; ?>
	</div>
</details>
<script>
document.querySelectorAll( '[data-nur-ids]' ).forEach( function ( btn ) {
	btn.addEventListener( 'click', function () {
		var ids = btn.dataset.nurIds ? btn.dataset.nurIds.split( ',' ) : null;
		document.querySelectorAll( btn.dataset.liste + ' tbody tr' ).forEach( function ( tr ) {
			tr.classList.toggle( 'mh-usc-hidden', ids !== null && ids.indexOf( tr.dataset.id ) === -1 );
		} );
		if ( ids ) { document.querySelector( btn.dataset.liste ).scrollIntoView( { behavior: 'smooth' } ); }
	} );
} );
</script>
