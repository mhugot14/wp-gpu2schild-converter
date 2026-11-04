<?php
/**
 * View: Reiter „Ergebnis“ – Schritte, Protokoll, Ergebniszeilen und Export.
 *
 * (Variablen aus layout.php: $ergebnis, $import, $formular, $post_url, $tab_url …)
 */

use Mh\UntisSchild\Model\Protokoll_Eintrag as P;
use Mh\UntisSchild\Model\Unterricht_Zeile;
use Mh\UntisSchild\Service\Pipeline;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( null === $ergebnis ) : ?>
	<div class="mh-usc-notice is-info">Zuerst eine GPU002 hochladen.</div>
	<?php return;
endif;

$start_schritt = sanitize_key( wp_unslash( $_GET['usc_schritt'] ?? '' ) );
$start_schritt = isset( Pipeline::SCHRITTE[ $start_schritt ] ) ? $start_schritt : '';
$aktionen      = [
	P::AKTION_ENTFERNT        => [ 'entfernt', 'is-red' ],
	P::AKTION_GEAENDERT       => [ 'geändert', 'is-blue' ],
	P::AKTION_ZUSAMMENGEFASST => [ 'zusammengefasst', 'is-blue' ],
	P::AKTION_ENTSCHIEDEN     => [ 'entschieden', 'is-green' ],
	P::AKTION_OFFEN           => [ 'offen', 'is-red' ],
	P::AKTION_HINWEIS         => [ 'Hinweis', 'is-amber' ],
];
$std = static fn( ?float $w ): string => null === $w ? '–' : Pipeline::zahl( $w );

/** @param Unterricht_Zeile[] $zeilen */
$zeilen_tabelle = static function ( array $zeilen ) use ( $std ): void {
	?>
	<table class="mh-usc-table">
		<thead><tr><th>Klasse</th><th>Fach</th><th>Lehrkraft</th><th class="is-num">Std. Kl.</th><th class="is-num">Std. LK</th><th>Gruppe</th><th>U-Nr</th><th>GPU-Zeile</th></tr></thead>
		<tbody>
			<?php foreach ( array_slice( $zeilen, 0, 60 ) as $z ) : ?>
				<tr>
					<td><?= esc_html( $z->klasse ) ?></td>
					<td><code><?= esc_html( $z->fach ) ?></code></td>
					<td><?= esc_html( '' === $z->lehrer ? '–' : $z->lehrer ) ?></td>
					<td class="is-num"><?= esc_html( $std( $z->wstd_klasse ) ) ?></td>
					<td class="is-num"><?= esc_html( $std( $z->wstd_lehrer ) ) ?></td>
					<td><?= esc_html( $z->gruppe ) ?></td>
					<td><?= esc_html( $z->unr ) ?></td>
					<td><?= esc_html( implode( ', ', $z->quelle ) ) ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( count( $zeilen ) > 60 ) : ?>
				<tr><td colspan="8" class="mh-usc-muted">… und <?= count( $zeilen ) - 60 ?> weitere Zeilen</td></tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
};

$gesperrt = $ergebnis->export_gesperrt();
$anzahl   = array_count_values( array_map( static fn( P $e ) => $e->schritt, $ergebnis->protokoll ) );
?>

<section class="mh-usc-card">
	<div class="mh-usc-card-head"><h3>Schild-Importdatei</h3></div>
	<div class="mh-usc-card-body">
		<?php if ( $gesperrt ) : ?>
			<div class="mh-usc-notice is-error">
				<b>Export gesperrt:</b> <?= count( $ergebnis->offene_konflikte() ) ?> Fächer haben mehrere Lehrkräfte mit gleich vielen Stunden.
				<a href="<?= esc_url( $tab_url( 'konflikte' ) ) ?>">Jetzt auswählen</a>
				– oder unten nur Klassen ohne offene Konflikte exportieren.
			</div>
		<?php endif; ?>
		<?php if ( ! $ergebnis->abgleich ) : ?>
			<div class="mh-usc-notice">Ohne Schild-Fächerliste kann nicht geprüft werden, ob Schild alle Fächer kennt.
				<a href="<?= esc_url( $tab_url( 'schildfaecher' ) ) ?>">Fächerliste hochladen</a></div>
		<?php elseif ( [] !== $ergebnis->unbekannte_faecher ) : ?>
			<div class="mh-usc-notice"><?= count( $ergebnis->unbekannte_faecher ) ?> Fächer fehlen in der Schild-Fächerliste
				(<?= esc_html( implode( ', ', array_slice( array_map( 'strval', array_keys( $ergebnis->unbekannte_faecher ) ), 0, 12 ) ) ) ?><?= count( $ergebnis->unbekannte_faecher ) > 12 ? ' …' : '' ?>).
				Diese Zeilen würden beim Import in Schild scheitern. <a href="<?= esc_url( $tab_url( 'schildfaecher' ) ) ?>">Details</a></div>
		<?php endif; ?>

		<?php
		$teil      = [] !== $export_vorauswahl;
		$vorauswahl = array_flip( array_map( 'mb_strtolower', $export_vorauswahl ) );
		?>
		<form method="post" action="<?= esc_url( $post_url ) ?>" id="usc-export" style="margin-top:0">
			<?php $formular( 'usc_export', 'ergebnis' ); ?>
			<div class="mh-usc-radios" style="margin-bottom:10px">
				<label><input type="radio" name="umfang" value="alle" <?php checked( ! $teil ); ?>> Alle Klassen (<?= count( $export_klassen ) ?>)</label>
				<label><input type="radio" name="umfang" value="auswahl" <?php checked( $teil ); ?>> Nur ausgewählte Klassen – um sie in Schild gezielt neu einzulesen</label>
			</div>
			<div id="usc-export-klassen" class="<?= $teil ? '' : 'mh-usc-hidden' ?>" style="margin-bottom:12px">
				<div class="mh-usc-toolbar">
					<input type="search" class="mh-usc-search" style="max-width:240px;min-height:34px;padding:4px 10px;font-size:14px"
						placeholder="Klasse suchen …" data-filter="#usc-export-liste" aria-label="Klassen durchsuchen">
					<span class="mh-usc-small mh-usc-muted"><span id="usc-export-zahl">0</span> ausgewählt ·
						<a href="#" id="usc-export-keine">keine</a></span>
				</div>
				<div id="usc-export-liste" style="display:flex;flex-wrap:wrap;gap:4px 14px;max-height:260px;overflow:auto;padding:8px;border:1px solid var(--u-border);border-radius:6px">
					<?php foreach ( $export_klassen as $kl => $info ) : $kl = (string) $kl; ?>
						<label data-suche="<?= esc_attr( $kl ) ?>" style="white-space:nowrap" title="<?= esc_attr( $info['zeilen'] . ' Zeilen' . ( $info['offen'] ? ', ' . $info['offen'] . ' Konflikte offen' : '' ) ) ?>">
							<input type="checkbox" name="klassen[]" value="<?= esc_attr( $kl ) ?>" <?php checked( isset( $vorauswahl[ mb_strtolower( $kl ) ] ) ); ?>>
							<?= esc_html( $kl ) ?><?php if ( $info['offen'] ) : ?> <span class="mh-usc-badge is-red" title="offene Konflikte">!</span><?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="mh-usc-actions" style="margin-top:0">
				<button type="submit" class="mh-usc-btn mh-usc-btn-primary mh-usc-btn-big" id="usc-export-knopf" <?= $gesperrt && ! $teil ? 'disabled' : '' ?>>
					Excel-Datei herunterladen (<span id="usc-export-zeilen"><?= count( $ergebnis->zeilen ) ?></span> Zeilen)
				</button>
				<span class="mh-usc-small mh-usc-muted">
					Format wie bisher: Kursbezeichnung, klasse, Jahr, Halbjahr, Jahrgang, Fach, Kursart, Wochenstunden, Wochenstunden Lehrer, lehrer.
					Die Datei wird beim Herunterladen erzeugt und nicht auf dem Server gespeichert. Bei einer Auswahl sperren nur offene Konflikte
					in den gewählten Klassen den Export.
				</span>
			</div>
		</form>
		<script>
		( function () {
			var form   = document.getElementById( 'usc-export' );
			if ( ! form ) { return; }
			var info   = <?= wp_json_encode( $export_klassen ) ?>;
			var gesamt = <?= (int) count( $ergebnis->zeilen ) ?>;
			var alleGesperrt = <?= $gesperrt ? 'true' : 'false' ?>;
			var liste  = document.getElementById( 'usc-export-klassen' );
			var knopf  = document.getElementById( 'usc-export-knopf' );
			function aktualisieren() {
				var teil  = form.querySelector( 'input[name="umfang"]:checked' ).value === 'auswahl';
				var boxen = form.querySelectorAll( 'input[name="klassen[]"]:checked' );
				var zeilen = 0, offen = 0;
				boxen.forEach( function ( b ) { var i = info[ b.value ] || {}; zeilen += i.zeilen || 0; offen += i.offen || 0; } );
				liste.classList.toggle( 'mh-usc-hidden', ! teil );
				document.getElementById( 'usc-export-zahl' ).textContent = boxen.length;
				document.getElementById( 'usc-export-zeilen' ).textContent = teil ? zeilen : gesamt;
				knopf.disabled = teil ? ( boxen.length === 0 || offen > 0 ) : alleGesperrt;
				knopf.title = teil && offen > 0 ? 'In den gewählten Klassen sind Konflikte offen.' : '';
			}
			form.addEventListener( 'change', aktualisieren );
			document.getElementById( 'usc-export-keine' ).addEventListener( 'click', function ( e ) {
				e.preventDefault();
				form.querySelectorAll( 'input[name="klassen[]"]' ).forEach( function ( b ) { b.checked = false; } );
				aktualisieren();
			} );
			aktualisieren();
		} )();
		</script>
	</div>
</section>

<h3 style="margin:24px 0 10px">So ist das Ergebnis entstanden</h3>
<div class="mh-usc-steps">
	<?php $nr = 0; foreach ( Pipeline::SCHRITTE as $key => $name ) : $nr++; $s = $ergebnis->statistik[ $key ]; $diff = $s['nachher'] - $s['vorher']; ?>
		<a class="mh-usc-step <?= $key === $start_schritt ? 'is-active' : '' ?>" href="#usc-protokoll" data-schritt-link="<?= esc_attr( $key ) ?>">
			<span class="mh-usc-step-nr"><?= (int) $nr ?></span><span class="mh-usc-step-name"><?= esc_html( $name ) ?></span>
			<span class="mh-usc-step-zahl"><b><?= (int) $s['nachher'] ?></b> Zeilen</span>
			<span class="mh-usc-step-diff <?= $diff < 0 ? 'is-minus' : ( 0 === $diff ? 'is-null' : '' ) ?>">
				<?= 0 === $diff ? '± 0' : esc_html( ( $diff > 0 ? '+' : '−' ) . abs( $diff ) ) ?> · <?= (int) ( $anzahl[ $key ] ?? 0 ) ?> Einträge
			</span>
		</a>
	<?php endforeach; ?>
</div>

<section class="mh-usc-card" id="usc-protokoll">
	<div class="mh-usc-card-head"><h3>Protokoll</h3><span class="mh-usc-small mh-usc-muted">Zum Aufklappen auf einen Eintrag klicken – zeigt die Zeilen vorher und nachher.</span></div>
	<div class="mh-usc-card-body">
		<div class="mh-usc-filter" role="group" aria-label="Schritt filtern">
			<button type="button" data-schritt="" class="<?= '' === $start_schritt ? 'is-active' : '' ?>">Alle (<?= count( $ergebnis->protokoll ) ?>)</button>
			<?php foreach ( Pipeline::SCHRITTE as $key => $name ) : if ( empty( $anzahl[ $key ] ) ) continue; ?>
				<button type="button" data-schritt="<?= esc_attr( $key ) ?>" class="<?= $key === $start_schritt ? 'is-active' : '' ?>"><?= esc_html( $name ) ?> (<?= (int) $anzahl[ $key ] ?>)</button>
			<?php endforeach; ?>
		</div>
		<div class="mh-usc-toolbar">
			<input type="search" class="mh-usc-search" id="usc-log-suche" placeholder="Klasse, Fach, Lehrkraft, Regel …" aria-label="Protokoll durchsuchen">
		</div>
		<?php if ( [] === $ergebnis->protokoll ) : ?>
			<p class="mh-usc-muted">Keine Änderungen – die GPU002 wurde unverändert übernommen.</p>
		<?php endif; ?>
		<ul class="mh-usc-log" id="usc-log">
			<?php foreach ( $ergebnis->protokoll as $e ) :
				$a     = $aktionen[ $e->aktion ] ?? [ $e->aktion, '' ];
				$suche = $e->text . ' ' . ( $e->regel ?? '' ) . ' ' . implode( ' ', array_map( static fn( $z ) => $z->lehrer, array_merge( $e->vorher, $e->nachher ) ) );
				?>
				<li data-schritt="<?= esc_attr( $e->schritt ) ?>" data-suche="<?= esc_attr( $suche ) ?>"
					class="<?= '' !== $start_schritt && $e->schritt !== $start_schritt ? 'mh-usc-hidden' : '' ?>">
					<details>
						<summary>
							<span class="mh-usc-badge <?= esc_attr( $a[1] ) ?>"><?= esc_html( $a[0] ) ?></span>
							<span class="mh-usc-log-text"><?= esc_html( $e->text ) ?></span>
							<?php if ( null !== $e->regel ) : ?>
								<span class="mh-usc-badge is-grey" title="auslösende Regel"><?= esc_html( str_starts_with( $e->regel, 'K' ) ? 'Klassenregel' : 'Fachregel' ) ?></span>
							<?php endif; ?>
							<span class="mh-usc-small mh-usc-muted"><?= esc_html( Pipeline::SCHRITTE[ $e->schritt ] ?? '' ) ?></span>
						</summary>
						<div class="mh-usc-log-body">
							<div>
								<h4>vorher (<?= count( $e->vorher ) ?>)</h4>
								<?php if ( $e->vorher ) { $zeilen_tabelle( $e->vorher ); } else { echo '<span class="mh-usc-muted mh-usc-small">–</span>'; } ?>
							</div>
							<div>
								<h4>nachher (<?= count( $e->nachher ) ?>)</h4>
								<?php if ( $e->nachher ) { $zeilen_tabelle( $e->nachher ); } else { echo '<span class="mh-usc-muted mh-usc-small">' . ( P::AKTION_HINWEIS === $e->aktion || P::AKTION_OFFEN === $e->aktion ? 'unverändert' : 'entfällt' ) . '</span>'; } ?>
							</div>
						</div>
					</details>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<details class="mh-usc-card">
	<summary class="mh-usc-card-head"><h3>Ergebniszeilen (<?= count( $ergebnis->zeilen ) ?>)</h3></summary>
	<div class="mh-usc-card-body">
		<div class="mh-usc-toolbar"><input type="search" class="mh-usc-search" placeholder="Klasse, Fach, Lehrkraft …" data-filter="#usc-erg" aria-label="Ergebnis durchsuchen"></div>
		<div class="mh-usc-scroll" style="max-height:560px;overflow-y:auto">
			<table class="mh-usc-table" id="usc-erg">
				<thead><tr><th>Klasse</th><th>Fach</th><th>Lehrkraft</th><th class="is-num">Std. Klasse</th><th class="is-num">Std. Lehrkraft</th><th>gilt</th><th>GPU-Zeilen</th></tr></thead>
				<tbody>
					<?php foreach ( $ergebnis->zeilen as $z ) : ?>
						<tr data-suche="<?= esc_attr( $z->klasse . ' ' . $z->fach . ' ' . $z->lehrer ) ?>">
							<td><?= esc_html( $z->klasse ) ?></td>
							<td><code><?= esc_html( $z->fach ) ?></code></td>
							<td><?= esc_html( $z->lehrer ) ?></td>
							<td class="is-num"><?= esc_html( $std( $z->wstd_klasse ) ) ?></td>
							<td class="is-num"><?= esc_html( $std( $z->wstd_lehrer ) ) ?></td>
							<td><?= esc_html( Unterricht_Zeile::GILT_GANZJAEHRIG === $z->gilt ? 'ganzjährig' : $z->gilt . '. HJ' ) ?></td>
							<td class="mh-usc-small mh-usc-muted"><?= esc_html( implode( ', ', $z->quelle ) ) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</details>

<script>
( function () {
	var liste  = document.getElementById( 'usc-log' );
	var suche  = document.getElementById( 'usc-log-suche' );
	var aktiv  = <?= wp_json_encode( $start_schritt ) ?>;
	function filtern() {
		var q = suche.value.trim().toLowerCase();
		liste.querySelectorAll( 'li' ).forEach( function ( li ) {
			var passt = ( aktiv === '' || li.dataset.schritt === aktiv ) && ( q === '' || li.dataset.suche.toLowerCase().indexOf( q ) > -1 );
			li.classList.toggle( 'mh-usc-hidden', ! passt );
		} );
		document.querySelectorAll( '.mh-usc-filter button' ).forEach( function ( b ) { b.classList.toggle( 'is-active', b.dataset.schritt === aktiv ); } );
		document.querySelectorAll( '[data-schritt-link]' ).forEach( function ( a ) { a.classList.toggle( 'is-active', a.dataset.schrittLink === aktiv ); } );
	}
	document.querySelectorAll( '.mh-usc-filter button' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () { aktiv = b.dataset.schritt; filtern(); } );
	} );
	document.querySelectorAll( '[data-schritt-link]' ).forEach( function ( a ) {
		a.addEventListener( 'click', function () { aktiv = a.dataset.schrittLink; filtern(); } );
	} );
	suche.addEventListener( 'input', filtern );
} )();
</script>
