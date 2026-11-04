<?php
/**
 * View: Reiter „WebUntis-Abgleich“ – Ergebnis des Converters gegen den tatsächlichen
 * Unterricht laut WebUntis-Stundenplan (gespeicherter, datierter Datenstand).
 *
 * @var bool       $wu_verfuegbar webuntisAnalyser aktiv?
 * @var array      $wu_stand      [ von, bis, anzahl ] des gespeicherten Datenstands
 * @var array      $wu_fehler     Untis-Klasse => Fehlertext des letzten Abrufs
 * @var ?array     $wu_abgleich   Webuntis_Abgleich::vergleiche() oder null
 * @var int        $wu_halbjahr   aktuelles Halbjahr laut Kalender
 * @var string     $wu_nonce
 * (+ Variablen aus layout.php)
 */

use Mh\UntisSchild\Service\Pipeline;
use Mh\UntisSchild\Service\Webuntis_Abgleich as A;

if ( ! defined( 'ABSPATH' ) ) exit;

$befunde = [
	A::LEHRKRAFT => [ 'Lehrkraft weicht ab', 'is-red' ],
	A::FEHLT_GPU => [ 'fehlt in der GPU002', 'is-amber' ],
	A::NUR_GPU   => [ 'nur in der GPU002', 'is-grey' ],
	A::KONFLIKT  => [ 'Konflikt offen', 'is-red' ],
	A::GLEICH    => [ 'stimmt überein', 'is-green' ],
];
$klassen_status = [
	A::KLASSE_FEHLT_GPU         => [ 'Klasse fehlt in der GPU002', 'is-amber', 'Laut WebUntis wird die Klasse unterrichtet, in der GPU002 steht sie nicht.' ],
	A::KLASSE_NICHT_IN_WEBUNTIS => [ 'nicht in WebUntis', 'is-grey', 'Keine aktive WebUntis-Klasse führt zu dieser Klasse – aufgelöst oder anders benannt?' ],
	A::KLASSE_NICHT_GELADEN     => [ 'noch nicht geladen', 'is-blue', 'Die Klasse gibt es in WebUntis, sie ist aber nicht im Datenstand. Aktualisieren lädt sie.' ],
];
$std = static fn( float $w ): string => Pipeline::zahl( $w );

// Untis-Klassen mit Abruffehler, auf die Schild-Klasse umgelegt.
$fehler_je_klasse = [];
foreach ( $wu_abgleich['klassen'] ?? [] as $k => $kl ) {
	foreach ( $kl['untis'] as $u ) {
		if ( isset( $wu_fehler[ $u ] ) ) {
			$fehler_je_klasse[ $k ][] = $u . ': ' . $wu_fehler[ $u ];
		}
	}
}
$export_url = static fn( string $klasse ): string => $tab_url( 'ergebnis', [ 'usc_klassen' => [ $klasse ] ] );
?>
<p class="mh-usc-intro">
	Vergleicht das Ergebnis aus der GPU002 mit dem tatsächlichen Unterricht laut WebUntis: Je Klasse und Fach gilt die Lehrkraft mit den
	meisten <b>gehaltenen</b> Stunden der letzten vier Wochen (plus geplante der nächsten zwei). Beide Seiten laufen durch dieselben
	Klassen- und Fachregeln – verglichen wird also genau, was in den Schild-Export geht. Gespeicherte Konflikt-Auswahlen gelten nur für die GPU002.
</p>

<?php if ( ! $wu_verfuegbar ) : ?>
	<div class="mh-usc-notice is-info">Für den Abgleich muss das Plugin <b>WebUntis Analyser</b> (ab Version 0.7.0) aktiv und mit WebUntis verbunden sein.</div>
	<?php return;
endif; ?>

<section class="mh-usc-card" id="usc-wu-stand">
	<div class="mh-usc-card-head">
		<h3>WebUntis-Datenstand</h3>
		<span class="mh-usc-small mh-usc-muted">
			<?php if ( $wu_stand['anzahl'] > 0 ) : ?>
				<?= (int) $wu_stand['anzahl'] ?> Klassen,
				<?php if ( mysql2date( 'Y-m-d', (string) $wu_stand['von'] ) === mysql2date( 'Y-m-d', (string) $wu_stand['bis'] ) ) : ?>
					abgerufen am <b><?= esc_html( mysql2date( 'd.m.Y', (string) $wu_stand['bis'] ) ) ?></b>
					(<?= esc_html( mysql2date( 'H:i', (string) $wu_stand['von'] ) ) ?>–<?= esc_html( mysql2date( 'H:i', (string) $wu_stand['bis'] ) ) ?> Uhr)
				<?php else : ?>
					abgerufen <b><?= esc_html( mysql2date( 'd.m.Y', (string) $wu_stand['von'] ) ) ?></b> bis <b><?= esc_html( mysql2date( 'd.m.Y', (string) $wu_stand['bis'] ) ) ?></b>
				<?php endif; ?>
			<?php else : ?>
				noch kein Datenstand
			<?php endif; ?>
		</span>
	</div>
	<div class="mh-usc-card-body">
		<?php if ( null !== $import && $wu_stand['anzahl'] > 0 && strtotime( (string) $wu_stand['von'] ) < strtotime( (string) $import->erstellt_am ) ) : ?>
			<div class="mh-usc-notice">Der WebUntis-Datenstand ist älter als die GPU002 (eingelesen am <?= esc_html( mysql2date( 'd.m.Y', $import->erstellt_am ) ) ?>). Für einen aussagekräftigen Abgleich aktualisieren.</div>
		<?php endif; ?>
		<?php if ( null !== $import && (int) $import->halbjahr !== $wu_halbjahr ) : ?>
			<div class="mh-usc-notice">Die GPU002 ist für das <b><?= (int) $import->halbjahr ?>. Halbjahr</b> eingelesen, WebUntis zeigt den Unterricht von heute
				(<?= (int) $wu_halbjahr ?>. Halbjahr). Fächer, die nur in einem Halbjahr laufen, erscheinen dann als Abweichung.</div>
		<?php endif; ?>
		<div class="mh-usc-actions" style="margin-top:0">
			<button type="button" class="mh-usc-btn mh-usc-btn-primary" id="usc-wu-aktualisieren">
				<?= $wu_stand['anzahl'] > 0 ? 'Aktualisieren' : 'Datenstand laden' ?>
			</button>
			<span class="mh-usc-small mh-usc-muted">Holt alle Klassen frisch aus WebUntis (etwa eine Sekunde je Klasse). Klassen, die eine Klassenregel
				aus dem Export nimmt, werden übersprungen. Der Stand bleibt gespeichert, bis du erneut aktualisierst.</span>
		</div>
		<div id="usc-wu-fortschritt" class="mh-usc-hidden" style="margin-top:12px" aria-live="polite">
			<div style="height:10px;background:var(--u-bg);border:1px solid var(--u-border);border-radius:5px;overflow:hidden">
				<div id="usc-wu-balken" style="height:100%;width:0;background:var(--u-blue);transition:width .2s"></div>
			</div>
			<p class="mh-usc-small" id="usc-wu-text" style="margin:6px 0 0"></p>
		</div>
	</div>
</section>

<?php if ( null === $ergebnis ) : ?>
	<div class="mh-usc-notice is-info">Zuerst eine GPU002 hochladen.</div>
<?php elseif ( null === $wu_abgleich ) : ?>
	<div class="mh-usc-notice is-info">Noch kein WebUntis-Datenstand – oben „Datenstand laden“ wählen.</div>
<?php else :
	$z      = $wu_abgleich['zaehler'];
	$zeilen = [];
	$sonder = [];
	foreach ( $wu_abgleich['klassen'] as $k => $kl ) {
		if ( isset( $klassen_status[ $kl['status'] ] ) ) {
			$sonder[ $k ] = $kl;
		}
		foreach ( $kl['faecher'] as $f ) {
			$zeilen[] = [ 'kl' => $kl, 'k' => $k ] + $f;
		}
	}
	?>
	<div class="mh-usc-stats">
		<div class="mh-usc-stat"><b style="color:<?= $z[ A::LEHRKRAFT ] ? 'var(--u-red)' : 'var(--u-green)' ?>"><?= (int) $z[ A::LEHRKRAFT ] ?></b><span>Lehrkraft weicht ab</span></div>
		<div class="mh-usc-stat"><b style="color:<?= $z[ A::FEHLT_GPU ] ? 'var(--u-amber)' : 'var(--u-green)' ?>"><?= (int) $z[ A::FEHLT_GPU ] ?></b><span>Fach fehlt in der GPU002</span></div>
		<div class="mh-usc-stat"><b><?= (int) $z[ A::NUR_GPU ] ?></b><span>nur in der GPU002</span></div>
		<div class="mh-usc-stat"><b><?= (int) $z[ A::KONFLIKT ] ?></b><span>Konflikt offen</span></div>
		<div class="mh-usc-stat"><b style="color:var(--u-green)"><?= (int) $z[ A::GLEICH ] ?></b><span>stimmen überein</span></div>
	</div>

	<?php if ( [] !== $sonder ) : ?>
		<details class="mh-usc-card" <?= count( $sonder ) <= 8 ? 'open' : '' ?>>
			<summary class="mh-usc-card-head"><h3>Klassen mit Auffälligkeiten (<?= count( $sonder ) ?>)</h3></summary>
			<div class="mh-usc-card-body">
				<table class="mh-usc-table is-stack">
					<thead><tr><th>Klasse</th><th>Befund</th><th>Bedeutung</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $sonder as $k => $kl ) : $st = $klassen_status[ $kl['status'] ]; ?>
							<tr>
								<td data-l="Klasse"><b><?= esc_html( $kl['klasse'] ) ?></b>
									<?php if ( [] !== $kl['untis'] && [ $kl['klasse'] ] !== $kl['untis'] ) : ?><br><span class="mh-usc-small mh-usc-muted">Untis: <?= esc_html( implode( ', ', $kl['untis'] ) ) ?></span><?php endif; ?>
								</td>
								<td data-l="Befund"><span class="mh-usc-badge <?= esc_attr( $st[1] ) ?>"><?= esc_html( $st[0] ) ?></span></td>
								<td data-l="Bedeutung" class="mh-usc-small"><?= esc_html( $st[2] ) ?></td>
								<td class="is-actions">
									<?php if ( A::KLASSE_NICHT_GELADEN === $kl['status'] && [] !== $kl['untis'] ) : ?>
										<button type="button" class="mh-usc-btn mh-usc-btn-small" data-wu-klasse="<?= esc_attr( $kl['untis'][0] ) ?>">laden</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</details>
	<?php endif; ?>

	<section class="mh-usc-card">
		<div class="mh-usc-card-head">
			<h3>Fächer je Klasse</h3>
			<input type="search" class="mh-usc-search" style="max-width:240px;min-height:34px;padding:4px 10px;font-size:14px"
				placeholder="Klasse, Fach, Lehrkraft …" id="usc-wu-suche" aria-label="Abgleich durchsuchen">
		</div>
		<div class="mh-usc-card-body">
			<div class="mh-usc-filter" role="group" aria-label="Befund filtern" id="usc-wu-filter">
				<button type="button" data-befund="auffaellig" class="is-active">Auffällige (<?= count( $zeilen ) - (int) $z[ A::GLEICH ] ?>)</button>
				<?php foreach ( $befunde as $b => [ $text ] ) : if ( 0 === (int) $z[ $b ] ) continue; ?>
					<button type="button" data-befund="<?= esc_attr( $b ) ?>"><?= esc_html( $text ) ?> (<?= (int) $z[ $b ] ?>)</button>
				<?php endforeach; ?>
				<button type="button" data-befund="">Alle (<?= count( $zeilen ) ?>)</button>
			</div>
			<div class="mh-usc-scroll">
				<table class="mh-usc-table is-stack" id="usc-wu-tabelle">
					<thead><tr><th>Klasse</th><th>Fach</th><th>GPU002</th><th>WebUntis (aktuell)</th><th>Befund</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $zeilen as $r ) :
							$b     = $befunde[ $r['befund'] ];
							$summe = array_sum( $r['wu_kandidaten'] );
							$suche = $r['kl']['klasse'] . ' ' . $r['fach'] . ' ' . implode( ' ', $r['gpu'] ) . ' ' . implode( ' ', array_keys( $r['wu_kandidaten'] ) ) . ' ' . $b[0];
							?>
							<tr data-befund="<?= esc_attr( $r['befund'] ) ?>" data-suche="<?= esc_attr( $suche ) ?>"
								class="<?= A::GLEICH === $r['befund'] ? 'mh-usc-hidden' : '' ?>">
								<td data-l="Klasse"><b><?= esc_html( $r['kl']['klasse'] ) ?></b>
									<?php if ( isset( $fehler_je_klasse[ $r['k'] ] ) ) : ?><br><span class="mh-usc-small" style="color:var(--u-red)" title="<?= esc_attr( implode( '; ', $fehler_je_klasse[ $r['k'] ] ) ) ?>">Abruffehler</span><?php endif; ?>
								</td>
								<td data-l="Fach"><code><?= esc_html( $r['fach'] ) ?></code></td>
								<td data-l="GPU002">
									<?php if ( null !== $r['gpu_offen'] ) : ?>
										<span class="mh-usc-small">offen: <?= esc_html( implode( ', ', $r['gpu_offen'] ) ) ?></span>
									<?php elseif ( [] !== $r['gpu'] ) : ?>
										<b><?= esc_html( implode( ', ', $r['gpu'] ) ) ?></b>
									<?php elseif ( A::FEHLT_GPU !== $r['befund'] ) : ?>
										<span class="mh-usc-muted">ohne Lehrkraft</span>
									<?php else : ?>
										<span class="mh-usc-muted">–</span>
									<?php endif; ?>
								</td>
								<td data-l="WebUntis">
									<?php if ( [] === $r['wu_kandidaten'] ) : ?>
										<span class="mh-usc-muted">kein Unterricht im Zeitraum</span>
									<?php else : ?>
										<?php if ( null !== $r['wu'] ) : ?>
											<b><?= esc_html( $r['wu'] ) ?></b> <span class="mh-usc-small mh-usc-muted"><?= esc_html( $std( (float) $r['wu_kandidaten'][ $r['wu'] ] ) . ' von ' . $std( $summe ) ) ?> Std.</span>
										<?php endif; ?>
										<?php $andere = array_diff_key( $r['wu_kandidaten'], null !== $r['wu'] ? [ $r['wu'] => 0 ] : [] ); if ( [] !== $andere ) : ?>
											<br><span class="mh-usc-small mh-usc-muted"><?= null === $r['wu'] ? 'Gleichstand: ' : 'außerdem: ' ?><?= esc_html( implode( ', ', array_map(
												static fn( $l, $s ) => $l . ' (' . $std( (float) $s ) . ')', array_keys( $andere ), $andere ) ) ) ?></span>
										<?php endif; ?>
										<?php if ( null !== $r['zuletzt'] && null !== $r['wu'] && $r['zuletzt'] !== $r['wu'] ) : ?>
											<br><span class="mh-usc-small" style="color:var(--u-amber)">⚠ zuletzt <?= esc_html( $r['zuletzt'] ) ?> – Vertretung oder Wechsel?</span>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<td data-l="Befund"><span class="mh-usc-badge <?= esc_attr( $b[1] ) ?>"><?= esc_html( $b[0] ) ?></span>
									<?php if ( A::KONFLIKT === $r['befund'] ) : ?><br><a class="mh-usc-small" href="<?= esc_url( $tab_url( 'konflikte' ) ) ?>">zu den Konflikten</a><?php endif; ?>
								</td>
								<td class="is-actions">
									<a class="mh-usc-btn mh-usc-btn-small" href="<?= esc_url( $export_url( $r['kl']['klasse'] ) ) ?>" title="Nur diese Klasse exportieren">Export</a>
									<?php if ( [] !== $r['kl']['untis'] ) : ?>
										<button type="button" class="mh-usc-btn mh-usc-btn-small" data-wu-klasse="<?= esc_attr( implode( ',', $r['kl']['untis'] ) ) ?>" title="Diese Klasse frisch aus WebUntis laden">↻</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="mh-usc-small mh-usc-muted" id="usc-wu-leer" style="display:none">Keine Einträge für diesen Filter.</p>
			<p class="mh-usc-small mh-usc-muted" style="margin-top:12px">
				„Fehlt in der GPU002“: Laut WebUntis wird das Fach (nach den Regeln) unterrichtet, im Export fehlt es – neue Kopplung, Untis-Fach ohne Regel
				oder veraltete GPU002. „Nur in der GPU002“: im Zeitraum kein Unterricht, etwa Blockunterricht, anderes Halbjahr oder ein Eintrag, der nicht mehr gilt.
				„Zuletzt“: In den letzten 14 Tagen hatte eine andere Lehrkraft die meisten Stunden.
			</p>
		</div>
	</section>
<?php endif; ?>

<script>
( function () {
	var root = document.getElementById( 'usc' );
	if ( ! root ) { return; }
	var ajaxUrl = <?= wp_json_encode( admin_url( 'admin-ajax.php' ) ) ?>;
	var nonce   = <?= wp_json_encode( $wu_nonce ) ?>;

	// --- Filter und Suche ------------------------------------------------------
	var tabelle = root.querySelector( '#usc-wu-tabelle' );
	var suche   = root.querySelector( '#usc-wu-suche' );
	var filter  = root.querySelector( '#usc-wu-filter' );
	var leer    = root.querySelector( '#usc-wu-leer' );
	var befund  = 'auffaellig';
	function anwenden() {
		if ( ! tabelle ) { return; }
		var q = suche ? suche.value.trim().toLowerCase() : '';
		var sichtbar = 0;
		tabelle.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
			var b  = tr.dataset.befund;
			var ok = befund === '' || ( befund === 'auffaellig' ? b !== 'gleich' : b === befund );
			if ( ok && q !== '' ) { ok = tr.dataset.suche.toLowerCase().indexOf( q ) > -1; }
			tr.classList.toggle( 'mh-usc-hidden', ! ok );
			if ( ok ) { sichtbar++; }
		} );
		if ( leer ) { leer.style.display = sichtbar ? 'none' : ''; }
	}
	if ( filter ) {
		filter.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button[data-befund]' );
			if ( ! btn ) { return; }
			befund = btn.dataset.befund;
			filter.querySelectorAll( 'button' ).forEach( function ( b ) { b.classList.toggle( 'is-active', b === btn ); } );
			anwenden();
		} );
	}
	if ( suche ) { suche.addEventListener( 'input', anwenden ); }
	anwenden();

	// --- Aktualisieren ---------------------------------------------------------
	var box    = root.querySelector( '#usc-wu-fortschritt' );
	var balken = root.querySelector( '#usc-wu-balken' );
	var text   = root.querySelector( '#usc-wu-text' );
	var laeuft = false;

	function post( aktion, daten ) {
		var fd = new FormData();
		fd.append( 'action', aktion );
		fd.append( 'nonce', nonce );
		Object.keys( daten || {} ).forEach( function ( k ) { fd.append( k, daten[ k ] ); } );
		return fetch( ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				if ( ! j || ! j.success ) { throw new Error( ( j && j.data && j.data.text ) || 'Unbekannter Fehler' ); }
				return j.data || {};
			} );
	}

	function setze( anteil, meldung ) {
		box.classList.remove( 'mh-usc-hidden' );
		balken.style.width = Math.round( anteil * 100 ) + '%';
		text.textContent = meldung;
	}

	function knoepfe( aus ) {
		root.querySelectorAll( '#usc-wu-aktualisieren, [data-wu-klasse]' ).forEach( function ( b ) { b.disabled = aus; } );
	}

	// klassen: null = alle, sonst Liste von Untis-Klassen.
	function laden( klassen ) {
		if ( laeuft ) { return; }
		laeuft = true;
		knoepfe( true );
		setze( 0, 'Klassenliste wird geholt …' );
		var start = klassen === null ? post( 'usc_wu_start', {} ) : Promise.all( klassen.map( function ( k ) {
			return post( 'usc_wu_start', { klasse: k } );
		} ) ).then( function ( teile ) {
			return { klassen: teile.reduce( function ( a, t ) { return a.concat( t.klassen || [] ); }, [] ) };
		} );

		start.then( function ( d ) {
			var liste = d.klassen || [];
			if ( ! liste.length ) { throw new Error( 'Keine passende WebUntis-Klasse gefunden.' ); }
			var i = 0, fehler = 0;
			function naechste() {
				if ( i >= liste.length ) {
					return post( 'usc_wu_ende', { voll: klassen === null ? '1' : '0', geladen: liste.length - fehler, fehler: fehler } );
				}
				var k = liste[ i ];
				setze( i / liste.length, 'Lade ' + k.name + ' (' + ( i + 1 ) + ' von ' + liste.length + ') …' );
				return post( 'usc_wu_klasse', { wu_id: k.wu_id, name: k.name } ).then( function ( r ) {
					if ( r.fehler ) { fehler++; }
				}, function () { fehler++; } ).then( function () { i++; return naechste(); } );
			}
			return naechste().then( function () {
				setze( 1, liste.length + ' Klassen geladen' + ( fehler ? ', ' + fehler + ' mit Fehler' : '' ) + ' – Seite wird neu geladen …' );
				window.location.reload();
			} );
		} ).catch( function ( err ) {
			setze( 0, 'Abgebrochen: ' + err.message );
			laeuft = false;
			knoepfe( false );
		} );
	}

	var alleBtn = root.querySelector( '#usc-wu-aktualisieren' );
	if ( alleBtn ) { alleBtn.addEventListener( 'click', function () { laden( null ); } ); }
	root.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-wu-klasse]' );
		if ( btn ) { laden( btn.dataset.wuKlasse.split( ',' ) ); }
	} );
} )();
</script>
