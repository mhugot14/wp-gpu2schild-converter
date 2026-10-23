<?php
/**
 * View: Reiter „Klassen“ – Klassenregeln verwalten.
 *
 * Wie der Reiter „Fächer“: Art über Karten, Klassen als Bausteine mit Vorschlägen aus dem
 * Import, Klartext mit Wirkung, Experten-Eingabe als Textform.
 *
 * @var Mh\UntisSchild\Model\Klassen_Regel[]  $regeln
 * @var array<int, int>                       $nummern        Regel-ID => Nr.
 * @var Mh\UntisSchild\Model\Regel_Befund[]   $befunde
 * @var array<int, array{von:int, am:string}> $aenderungen
 * @var array<string, string[]>               $regel_klassen  Regelkennung => Klassen, auf die sie gewirkt hat
 * @var string[]                              $werte          Klassen im aktuellen Import
 * @var array<string, int>                    $klassen_zeilen Klasse => Zeilen
 * @var string                                $als_text
 * @var int                                   $bearbeiten
 * @var array<string, string>                 $vorlage        art, klassen, schild, bemerkung
 * (+ Variablen aus layout.php)
 */

use Mh\UntisSchild\Service\Muster;
use Mh\UntisSchild\Service\Regel_Text;

if ( ! defined( 'ABSPATH' ) ) exit;

$typ  = 'klassen';
$edit = null;
foreach ( $regeln as $r ) {
	if ( $r->id === $bearbeiten ) {
		$edit = $r;
	}
}
$aus_flash   = isset( $eingabe['muster'] );
$feld_fehler = $aus_flash ? ( $flash['fehler'] ?? [] ) : [];
$text_fehler = isset( $eingabe['text'] );
$aktive      = count( array_filter( $regeln, static fn( $r ) => $r->aktiv ) );

if ( $aus_flash ) {
	$start = [ 'art' => $alt( 'art' ), 'muster' => $alt( 'muster' ), 'ziel' => $alt( 'ziel' ), 'bemerkung' => $alt( 'bemerkung' ) ];
} elseif ( null !== $edit ) {
	$start = [ 'art' => $edit->ist_loeschung() ? 'loeschen' : 'umbenennen', 'muster' => $edit->muster, 'ziel' => $edit->ziel, 'bemerkung' => $edit->bemerkung ];
} else {
	$start = [
		'art'       => $vorlage['art'] ?? '',
		'muster'    => $vorlage['klassen'] ?? '',
		'ziel'      => $vorlage['schild'] ?? '',
		'bemerkung' => $vorlage['bemerkung'] ?? '',
	];
}
$lesbar = static function ( string $muster ): string {
	if ( preg_match( '/^([^*%]+)[*%]$/u', $muster, $m ) ) {
		return '<code title="' . esc_attr( "alle Klassen, die mit „{$m[1]}“ beginnen" ) . '">' . esc_html( $m[1] ) . '…</code>';
	}
	return '<code>' . esc_html( $muster ) . '</code>';
};
?>
<p class="mh-usc-intro">
	Klassenregeln wirken als Erstes. Klassen, die es in Schild nicht gibt (Vertretungsreserven, Beratung, Konferenzen …), werden entfernt;
	heißt eine Klasse in Schild anders, wird sie umbenannt. Passen mehrere Regeln auf eine Klasse, gilt die oberste.
</p>

<section class="mh-usc-card" id="usc-form">
	<div class="mh-usc-card-head">
		<h3><?= null !== $edit ? 'Klassenregel bearbeiten' : 'Neue Klassenregel' ?></h3>
		<?php if ( null !== $edit || [] !== $vorlage ) : ?><a class="mh-usc-btn mh-usc-btn-small" href="<?= esc_url( $tab_url( 'klassen' ) ) ?>">Abbrechen</a><?php endif; ?>
	</div>
	<div class="mh-usc-card-body">
		<form method="post" action="<?= esc_url( $post_url ) ?>" class="mh-usc-fr" id="usc-kr" <?= '' !== $start['art'] ? 'data-art="' . esc_attr( $start['art'] ) . '"' : '' ?>
			data-klassen-zeilen="<?= esc_attr( (string) wp_json_encode( (object) $klassen_zeilen ) ) ?>">
			<?php $formular( 'usc_klassen_regel', 'klassen' ); ?>
			<input type="hidden" name="id" value="<?= (int) ( $edit?->id ?? 0 ) ?>">

			<p class="mh-usc-schritt-titel">1. Was soll mit der Klasse passieren?</p>
			<div class="mh-usc-arten" style="grid-template-columns:1fr 1fr" role="radiogroup" aria-label="Art der Regel">
				<label class="mh-usc-art">
					<input type="radio" name="art" value="loeschen" <?php checked( 'loeschen', $start['art'] ); ?>>
					<span class="mh-usc-art-titel">Entfernen</span>
					<span class="mh-usc-art-text">Die Klasse gibt es in Schild nicht – ihr Unterricht wird nicht übertragen.</span>
					<span class="mh-usc-art-bsp mh-usc-muted">z. B. Vertretungsreserve, Beratung, Konferenz</span>
				</label>
				<label class="mh-usc-art">
					<input type="radio" name="art" value="umbenennen" <?php checked( 'umbenennen', $start['art'] ); ?>>
					<span class="mh-usc-art-titel">Umbenennen</span>
					<span class="mh-usc-art-text">Die Klasse heißt in Schild anders.</span>
					<span class="mh-usc-art-bsp mh-usc-muted">z. B. EC1 → ECKU1</span>
				</label>
			</div>

			<div class="mh-usc-fr-felder">
				<div class="mh-usc-grid">
					<div class="mh-usc-field is-wide <?= isset( $feld_fehler['muster'] ) ? 'is-invalid' : '' ?>">
						<span class="mh-usc-label" id="usc-l-kmuster">2. Welche Untis-Klassen?</span>
						<div class="mh-usc-chipfeld" data-ziel="#usc-k-muster" data-trenner="," data-liste="#usc-dl-kl" data-was="Klassen" aria-labelledby="usc-l-kmuster">
							<input type="text" list="usc-dl-kl" placeholder="Klasse eingeben oder auswählen …" autocomplete="off" aria-labelledby="usc-l-kmuster">
						</div>
						<div class="mh-usc-vorschlaege" data-fuer="#usc-k-muster"></div>
						<?php if ( isset( $feld_fehler['muster'] ) ) : ?><span class="mh-usc-feld-fehler"><?= esc_html( $feld_fehler['muster'] ) ?></span><?php endif; ?>
					</div>
					<div class="mh-usc-field is-wide mh-usc-nur-ziel <?= isset( $feld_fehler['ziel'] ) ? 'is-invalid' : '' ?>">
						<label for="usc-k-ziel">3. Wie heißt die Klasse in Schild?</label>
						<input type="text" id="usc-k-ziel" name="ziel" value="<?= esc_attr( $start['ziel'] ) ?>" placeholder="z. B. ECKU1">
						<?php if ( isset( $feld_fehler['ziel'] ) ) : ?><span class="mh-usc-feld-fehler"><?= esc_html( $feld_fehler['ziel'] ) ?></span><?php endif; ?>
					</div>
					<div class="mh-usc-field is-full">
						<label for="usc-k-bem">Bemerkung <span class="mh-usc-muted" style="font-weight:400">(warum gibt es die Regel?)</span></label>
						<input type="text" id="usc-k-bem" name="bemerkung" value="<?= esc_attr( $start['bemerkung'] ) ?>">
					</div>
				</div>

				<div class="mh-usc-satz" id="usc-k-satz" aria-live="polite"></div>

				<details style="margin-top:14px">
					<summary class="mh-usc-small" style="cursor:pointer">Experten-Eingabe (Textform der Regel)</summary>
					<div class="mh-usc-grid" style="margin-top:10px">
						<div class="mh-usc-field is-wide">
							<label for="usc-k-muster">Untis-Klassen</label>
							<input type="text" id="usc-k-muster" name="muster" value="<?= esc_attr( $start['muster'] ) ?>" placeholder="z. B. BERAT, VR*">
						</div>
					</div>
					<p class="mh-usc-small mh-usc-muted">Mehrere Klassen mit Komma trennen, <code>*</code> steht für beliebige Zeichen. Umbenennen geht nur für eine einzelne Klasse.</p>
				</details>

				<div class="mh-usc-actions">
					<?php if ( null !== $edit ) : ?>
						<label class="mh-usc-check"><input type="checkbox" name="aktiv" value="1" <?php checked( $aus_flash ? ! empty( $eingabe['aktiv'] ) : $edit->aktiv ); ?>> aktiv</label>
					<?php endif; ?>
					<button type="submit" class="mh-usc-btn mh-usc-btn-primary"><?= null !== $edit ? 'Änderung speichern' : 'Regel anlegen' ?></button>
				</div>
			</div>
		</form>
		<datalist id="usc-dl-kl"><?php foreach ( $werte as $w ) : ?><option value="<?= esc_attr( $w ) ?>"><?php endforeach; ?></datalist>
	</div>
</section>

<?php include __DIR__ . '/partial-regelpruefung.php'; ?>

<section class="mh-usc-card">
	<div class="mh-usc-card-head">
		<h3>Klassenregeln</h3>
		<span class="mh-usc-badge is-blue"><?= count( $regeln ) ?> Regeln, <?= (int) $aktive ?> aktiv</span>
	</div>
	<div class="mh-usc-card-body">
		<?php if ( [] === $regeln ) : ?>
			<p class="mh-usc-muted">Noch keine Klassenregeln.</p>
		<?php else : ?>
			<div class="mh-usc-toolbar">
				<input type="search" class="mh-usc-search" placeholder="Regeln durchsuchen …" data-filter="#usc-k-liste" aria-label="Regeln durchsuchen">
				<?php if ( null === $ergebnis ) : ?><span class="mh-usc-small mh-usc-muted">Ohne Import kann nicht angezeigt werden, worauf die Regeln wirken.</span><?php endif; ?>
			</div>
			<div class="mh-usc-scroll">
				<table class="mh-usc-table is-stack" id="usc-k-liste">
					<thead><tr><th>Nr.</th><th>aktiv</th><th>Untis-Klasse</th><th>Wirkung</th><th>Bemerkung</th><th>wirkt auf</th><th>geändert</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $regeln as $i => $r ) :
							$klassen = $regel_klassen[ $r->kennung() ] ?? [];
							$zeilen  = null !== $ergebnis ? $ergebnis->treffer_fuer( $r->kennung() ) : null;
							$aend    = $aenderungen[ $r->id ] ?? null;
							?>
							<tr class="<?= $r->aktiv ? '' : 'is-off' ?> <?= $r->id === $bearbeiten ? 'is-highlight' : '' ?>" id="usc-regel-<?= (int) $r->id ?>" data-id="<?= (int) $r->id ?>"
								data-suche="<?= esc_attr( $r->muster . ' ' . $r->ziel . ' ' . $r->bemerkung ) ?>">
								<td class="is-nr" data-l="Nr."><?= (int) ( $nummern[ $r->id ] ?? 0 ) ?></td>
								<td data-l="aktiv"><?php include __DIR__ . '/partial-regel-schalter.php'; ?></td>
								<td data-l="Untis-Klasse"><?= implode( ' ', array_map( $lesbar, Muster::teile( $r->muster ) ) ) // phpcs:ignore -- in $lesbar escaped ?></td>
								<td data-l="Wirkung">
									<?php if ( $r->ist_loeschung() ) : ?>
										<span class="mh-usc-badge is-red">entfernen</span>
									<?php else : ?>
										<span class="mh-usc-badge is-blue">umbenennen</span><br><span class="mh-usc-arrow">→</span><code><?= esc_html( $r->ziel ) ?></code>
									<?php endif; ?>
								</td>
								<td data-l="Bemerkung"><?= esc_html( $r->bemerkung ) ?></td>
								<td data-l="wirkt auf">
									<?php if ( null === $zeilen ) : ?>
										<span class="mh-usc-muted">–</span>
									<?php elseif ( 0 === $zeilen ) : ?>
										<span class="mh-usc-badge is-grey"><?= $r->aktiv ? 'ohne Treffer' : 'inaktiv' ?></span>
									<?php else : ?>
										<b><?= (int) $zeilen ?></b> Zeilen
										<div class="mh-usc-klassen"><?= esc_html( implode( ', ', $klassen ) ) ?></div>
									<?php endif; ?>
								</td>
								<td data-l="geändert" class="mh-usc-small mh-usc-muted">
									<?php if ( $aend && $aend['von'] > 0 ) : ?><?= esc_html( $benutzer( $aend['von'] ) ) ?><br><?php endif; ?>
									<?= $aend ? esc_html( mysql2date( 'd.m.Y', $aend['am'] ) ) : '' ?>
								</td>
								<td class="is-actions">
									<?php $erste = 0 === $i; $letzte = $i === count( $regeln ) - 1; include __DIR__ . '/partial-regel-aktionen.php'; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php $text_typ = 'klassen'; $text_format = 'Untis-Klasse ; Schild-Klasse ; Bemerkung'; $text_beispiel = "BERAT ; ; Beratung, gibt es in Schild nicht\nVR* ; ; Vertretungsreserven\nEC1 ; ECKU1 ; heißt in Schild anders"; ?>
<?php include __DIR__ . '/partial-regel-text.php'; ?>

<script>
( function () {
	var form = document.getElementById( 'usc-kr' );
	if ( ! form || ! window.mhUsc ) { return; }
	var usc     = window.mhUsc;
	var zeilen  = JSON.parse( form.dataset.klassenZeilen || '{}' );
	var ziel    = document.getElementById( 'usc-k-ziel' );
	var klassen = usc.bausteinfeld( form.querySelector( '.mh-usc-chipfeld' ), { form: form, beiAenderung: function () { aktualisieren(); } } );

	function art() { var r = form.querySelector( 'input[name=art]:checked' ); return r ? r.value : ''; }

	function aktualisieren() {
		var a = art();
		if ( a ) { form.dataset.art = a; } else { delete form.dataset.art; } // ohne Art: Felder wieder verbergen
		if ( klassen.max !== ( a === 'umbenennen' ? 1 : 0 ) ) {
			klassen.begrenzen( a === 'umbenennen' ? 1 : 0 ); // ruft über beiAenderung erneut aktualisieren() auf
			return;
		}
		satz();
	}

	function satz() {
		var box = document.getElementById( 'usc-k-satz' );
		var a = art(), k = klassen.liste();
		box.className = 'mh-usc-satz';
		box.innerHTML = '';
		if ( ! a || ! k.length ) { return; }
		var was = usc.aufzaehlung( k, 'Klassen' ), viele = k.length > 1 || /[*%]/.test( k.join( '' ) ), t;
		if ( a === 'loeschen' ) {
			t = ( viele ? 'Die Klassen ' : 'Die Klasse ' ) + was + ( viele ? ' werden' : ' wird' ) + ' mit ihrem Unterricht entfernt.';
		} else if ( ! ziel.value.trim() ) {
			t = 'Bitte noch angeben, wie die Klasse in Schild heißt.';
		} else {
			t = 'Die Klasse ' + was + ' heißt in Schild ' + ziel.value.trim() + '.';
		}
		var summe = 0, treffer = [];
		Object.keys( zeilen ).forEach( function ( kl ) {
			if ( k.some( function ( m ) { return usc.passt( m, kl ); } ) ) { summe += zeilen[ kl ]; treffer.push( kl ); }
		} );
		var st = document.createElement( 'strong' ); st.textContent = t; box.appendChild( st );
		if ( Object.keys( zeilen ).length ) {
			if ( summe ) {
				treffer.sort();
				box.appendChild( usc.text( 'Im aktuellen Import betrifft das ' + summe + ' Zeilen in ' + treffer.length + ( treffer.length === 1 ? ' Klasse' : ' Klassen' ) + ': '
					+ treffer.slice( 0, 15 ).join( ', ' ) + ( treffer.length > 15 ? ' …' : '' ) ) );
			} else {
				box.classList.add( 'is-warn' );
				box.appendChild( usc.text( 'Im aktuellen Import gibt es diese Klasse nicht – Schreibweise prüfen?' ) );
			}
		}
	}

	form.querySelectorAll( 'input[name=art]' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () { aktualisieren(); klassen.eingabe.focus(); } );
	} );
	ziel.addEventListener( 'input', satz );
	form.addEventListener( 'submit', function () {
		if ( klassen.eingabe.value.trim() ) { klassen.hinzu( klassen.eingabe.value ); }
		if ( art() === 'loeschen' ) { ziel.value = ''; }
	} );
	aktualisieren();
	usc.abwaehlbar( form, 'art', aktualisieren );
	form.classList.add( 'is-js' );
} )();
</script>
