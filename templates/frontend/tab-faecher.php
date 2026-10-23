<?php
/**
 * View: Reiter „Fächer“ – Fachregeln verwalten.
 *
 * Das Formular arbeitet mit Bausteinen statt Syntax: Die Art wird über Karten gewählt,
 * Fächer und Klassen als Bausteine mit Vorschlägen aus dem Import. JavaScript schreibt
 * daraus die Textform (LF1|LF2, AK*) in die Felder der „Experten-Eingabe“, die auch
 * ohne JavaScript funktionieren.
 *
 * @var Mh\UntisSchild\Model\Fach_Regel[]     $regeln
 * @var array<int, array{von:int, am:string}> $aenderungen
 * @var array<string, string[]>               $regel_klassen Regelkennung => Klassen, auf die sie gewirkt hat
 * @var array<string, int>                    $ueberdeckt    Regelkennung => Fälle, in denen auch spätere Regeln gepasst hätten
 * @var string[]                              $werte         Fächer im aktuellen Import
 * @var string[]                              $klassen_werte Klassen im aktuellen Import
 * @var array<string, array<string, int>>     $fach_klassen  Fach => [ Klasse => Zeilen ] (Rohdaten)
 * @var array<string, string>                 $schild_liste  Schild-Kurzform => Bezeichnung
 * @var string                                $als_text
 * @var int                                   $bearbeiten
 * @var array<string, string>                 $vorlage       Vorbelegung über die URL
 * @var Mh\UntisSchild\Model\Regel_Befund[]   $befunde       Ergebnis des Regelprüfers
 * @var array<int, int>                       $nummern       Regel-ID => Nr.
 * (+ Variablen aus layout.php)
 */

use Mh\UntisSchild\Model\Fach_Regel;
use Mh\UntisSchild\Service\Muster;
use Mh\UntisSchild\Service\Regel_Text;

if ( ! defined( 'ABSPATH' ) ) exit;

$typ  = 'faecher';
$edit = null;
foreach ( $regeln as $r ) {
	if ( $r->id === $bearbeiten ) {
		$edit = $r;
	}
}
$aus_flash   = isset( $eingabe['untis_faecher'] );
$feld_fehler = $aus_flash ? ( $flash['fehler'] ?? [] ) : [];
$text_fehler = isset( $eingabe['text'] );
$aktive      = count( array_filter( $regeln, static fn( $r ) => $r->aktiv ) );

// Startwerte: Eingabe nach Fehler > bearbeitete Regel > Vorlage aus der URL > leer.
if ( $aus_flash ) {
	$start = [
		'art'       => $alt( 'art' ),
		'untis'     => $alt( 'untis_faecher' ),
		'schild'    => $alt( 'schild_faecher' ),
		'klassen'   => 'alle' === $alt( 'klassen_modus' ) ? '' : $alt( 'klassen_muster' ),
		'bemerkung' => $alt( 'bemerkung' ),
	];
} elseif ( null !== $edit ) {
	$start = [
		'art'       => $edit->art(),
		'untis'     => implode( '|', $edit->untis_faecher ),
		'schild'    => implode( '|', $edit->schild_faecher ),
		'klassen'   => Muster::ist_alle( $edit->klassen_muster ) ? '' : $edit->klassen_muster,
		'bemerkung' => $edit->bemerkung,
	];
} else {
	$start = array_merge( [ 'art' => '', 'untis' => '', 'schild' => '', 'klassen' => '', 'bemerkung' => '' ], $vorlage );
}
$arten = [
	Fach_Regel::ART_LOESCHEN       => [ 'Entfernen', 'Das Fach kommt nicht nach Schild.', 'z. B. Vertretung, Förderung' ],
	Fach_Regel::ART_UMBENENNEN     => [ 'Umbenennen', 'Das Fach heißt in Schild anders.', 'z. B. ER → Reli' ],
	Fach_Regel::ART_ZUSAMMENFASSEN => [ 'Zusammenfassen', 'Mehrere Untis-Fächer werden ein Schild-Fach.', 'z. B. LF1 + LF2 → LF' ],
	Fach_Regel::ART_AUFTEILEN      => [ 'Aufteilen', 'Ein Untis-Fach wird zu mehreren Schild-Fächern.', 'z. B. LFU1 → WSP, KUP, KPA' ],
];
$badge = [
	Fach_Regel::ART_LOESCHEN       => 'is-red',
	Fach_Regel::ART_UMBENENNEN     => 'is-blue',
	Fach_Regel::ART_ZUSAMMENFASSEN => 'is-blue',
	Fach_Regel::ART_AUFTEILEN      => 'is-amber',
];

// Muster lesbar: „AK*“ → „AK…“ mit Erklärung, sonst der Name.
$lesbar = static function ( string $muster, string $was ): string {
	if ( preg_match( '/^([^*%]+)[*%]$/u', $muster, $m ) ) {
		return '<code title="' . esc_attr( "alle $was, die mit „{$m[1]}“ beginnen" ) . '">' . esc_html( $m[1] ) . '…</code>';
	}
	return '<code>' . esc_html( $muster ) . '</code>';
};
$liste = static fn( array $muster, string $was ): string => implode( ' ', array_map( static fn( $m ) => $lesbar( $m, $was ), $muster ) );
?>
<p class="mh-usc-intro">
	Fachregeln legen fest, was mit einem Untis-Fach in Schild passiert. Sie wirken nach den Klassenregeln. Unterrichten nach dem Aufteilen
	oder Zusammenfassen mehrere Lehrkräfte dasselbe Schild-Fach, entscheidet sich im Reiter „Konflikte“, wer es bekommt.
	Passen mehrere Regeln, gilt die oberste.
</p>

<?php if ( [] !== $vorlage && null === $edit && ! $aus_flash ) : ?>
	<div class="mh-usc-notice is-info">Die Regel ist vorausgefüllt – bitte prüfen und mit „Regel anlegen“ speichern.</div>
<?php endif; ?>

<section class="mh-usc-card" id="usc-form">
	<div class="mh-usc-card-head">
		<h3><?= null !== $edit ? 'Fachregel bearbeiten' : 'Neue Fachregel' ?></h3>
		<?php if ( null !== $edit || [] !== $vorlage ) : ?><a class="mh-usc-btn mh-usc-btn-small" href="<?= esc_url( $tab_url( 'faecher' ) ) ?>">Abbrechen</a><?php endif; ?>
	</div>
	<div class="mh-usc-card-body">
		<form method="post" action="<?= esc_url( $post_url ) ?>" class="mh-usc-fr" id="usc-fr" <?= '' !== $start['art'] ? 'data-art="' . esc_attr( $start['art'] ) . '"' : '' ?>
			data-fach-klassen="<?= esc_attr( (string) wp_json_encode( (object) $fach_klassen ) ) ?>"
			data-schild="<?= esc_attr( (string) wp_json_encode( (object) $schild_liste ) ) ?>">
			<?php $formular( 'usc_fach_regel', 'faecher' ); ?>
			<input type="hidden" name="id" value="<?= (int) ( $edit?->id ?? 0 ) ?>">

			<p class="mh-usc-schritt-titel">1. Was soll mit dem Fach passieren?</p>
			<div class="mh-usc-arten" role="radiogroup" aria-label="Art der Regel">
				<?php foreach ( $arten as $key => [ $titel, $text, $bsp ] ) : ?>
					<label class="mh-usc-art">
						<input type="radio" name="art" value="<?= esc_attr( $key ) ?>" <?php checked( $key, $start['art'] ); ?>>
						<span class="mh-usc-art-titel"><?= esc_html( $titel ) ?></span>
						<span class="mh-usc-art-text"><?= esc_html( $text ) ?></span>
						<span class="mh-usc-art-bsp mh-usc-muted"><?= esc_html( $bsp ) ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="mh-usc-fr-felder">
				<div class="mh-usc-grid">
					<div class="mh-usc-field is-wide <?= isset( $feld_fehler['untis_faecher'] ) ? 'is-invalid' : '' ?>">
						<span class="mh-usc-label" id="usc-l-untis">2. Untis-Fach</span>
						<div class="mh-usc-chipfeld" data-ziel="#usc-f-untis" data-trenner="|" data-liste="#usc-dl-faecher" data-was="Fächer" aria-labelledby="usc-l-untis">
							<input type="text" list="usc-dl-faecher" placeholder="Fach eingeben oder auswählen …" autocomplete="off" aria-labelledby="usc-l-untis">
						</div>
						<div class="mh-usc-vorschlaege" data-fuer="#usc-f-untis"></div>
						<?php if ( isset( $feld_fehler['untis_faecher'] ) ) : ?><span class="mh-usc-feld-fehler"><?= esc_html( $feld_fehler['untis_faecher'] ) ?></span><?php endif; ?>
					</div>
					<div class="mh-usc-field is-wide mh-usc-nur-schild <?= isset( $feld_fehler['schild_faecher'] ) ? 'is-invalid' : '' ?>">
						<span class="mh-usc-label" id="usc-l-schild">3. Schild-Fach</span>
						<div class="mh-usc-chipfeld" data-ziel="#usc-f-schild" data-trenner="|" data-liste="#usc-dl-schild" data-schild="1" aria-labelledby="usc-l-schild">
							<input type="text" list="usc-dl-schild" placeholder="Schild-Fach eingeben oder auswählen …" autocomplete="off" aria-labelledby="usc-l-schild">
						</div>
						<?php if ( isset( $feld_fehler['schild_faecher'] ) ) : ?><span class="mh-usc-feld-fehler"><?= esc_html( $feld_fehler['schild_faecher'] ) ?></span><?php endif; ?>
						<?php if ( [] === $schild_liste ) : ?><small>Tipp: Mit hochgeladener Schild-Fächerliste werden die Schild-Fächer hier vorgeschlagen.</small><?php endif; ?>
					</div>
					<div class="mh-usc-field is-full <?= isset( $feld_fehler['klassen_muster'] ) ? 'is-invalid' : '' ?>">
						<span class="mh-usc-label"><span class="mh-usc-nr-klassen">4.</span> In welchen Klassen?</span>
						<div class="mh-usc-radios">
							<label class="mh-usc-check"><input type="radio" name="klassen_modus" value="alle" <?php checked( '' === $start['klassen'] ); ?>> in allen Klassen</label>
							<label class="mh-usc-check"><input type="radio" name="klassen_modus" value="auswahl" <?php checked( '' !== $start['klassen'] ); ?>> nur in bestimmten Klassen</label>
						</div>
						<div class="mh-usc-klassenwahl">
							<div class="mh-usc-chipfeld" data-ziel="#usc-f-klassen" data-trenner="," data-liste="#usc-dl-klassen" data-was="Klassen" aria-label="Klassen">
								<input type="text" list="usc-dl-klassen" placeholder="Klasse eingeben oder auswählen …" autocomplete="off" aria-label="Klasse hinzufügen">
							</div>
							<div class="mh-usc-vorschlaege" data-fuer="#usc-f-klassen"></div>
							<small>Gemeint sind die Klassennamen aus Untis, wie sie im Import stehen.</small>
						</div>
						<?php if ( isset( $feld_fehler['klassen_muster'] ) ) : ?><span class="mh-usc-feld-fehler"><?= esc_html( $feld_fehler['klassen_muster'] ) ?></span><?php endif; ?>
					</div>
					<div class="mh-usc-field is-full">
						<label for="usc-f-bem">Bemerkung <span class="mh-usc-muted" style="font-weight:400">(warum gibt es die Regel?)</span></label>
						<input type="text" id="usc-f-bem" name="bemerkung" value="<?= esc_attr( $start['bemerkung'] ) ?>">
					</div>
				</div>

				<div class="mh-usc-satz" id="usc-satz" aria-live="polite"></div>

				<details class="mh-usc-experte" style="margin-top:14px">
					<summary class="mh-usc-small" style="cursor:pointer">Experten-Eingabe (Textform der Regel)</summary>
					<div class="mh-usc-grid" style="margin-top:10px">
						<div class="mh-usc-field">
							<label for="usc-f-untis">Untis-Fächer</label>
							<input type="text" id="usc-f-untis" name="untis_faecher" value="<?= esc_attr( $start['untis'] ) ?>" placeholder="LFU1|LFU2">
						</div>
						<div class="mh-usc-field mh-usc-nur-schild">
							<label for="usc-f-schild">Schild-Fächer</label>
							<input type="text" id="usc-f-schild" name="schild_faecher" value="<?= esc_attr( $start['schild'] ) ?>" placeholder="WSP|KUP|KPA">
						</div>
						<div class="mh-usc-field is-wide">
							<label for="usc-f-klassen">Klassen</label>
							<input type="text" id="usc-f-klassen" name="klassen_muster" value="<?= esc_attr( $start['klassen'] ) ?>" placeholder="leer = alle, z. B. AK*, EC1">
						</div>
					</div>
					<p class="mh-usc-small mh-usc-muted">Mehrere Fächer mit <code>|</code>, mehrere Klassen mit Komma trennen. <code>*</code> steht für beliebige Zeichen.
						Die Bausteine oben und diese Felder sind verbunden.</p>
				</details>

				<div class="mh-usc-actions">
					<?php if ( null !== $edit ) : ?>
						<label class="mh-usc-check"><input type="checkbox" name="aktiv" value="1" <?php checked( $aus_flash ? ! empty( $eingabe['aktiv'] ) : $edit->aktiv ); ?>> aktiv</label>
					<?php endif; ?>
					<button type="submit" class="mh-usc-btn mh-usc-btn-primary"><?= null !== $edit ? 'Änderung speichern' : 'Regel anlegen' ?></button>
				</div>
			</div>
		</form>

		<datalist id="usc-dl-faecher"><?php foreach ( $werte as $w ) : ?><option value="<?= esc_attr( $w ) ?>"><?php endforeach; ?></datalist>
		<datalist id="usc-dl-klassen"><?php foreach ( $klassen_werte as $w ) : ?><option value="<?= esc_attr( $w ) ?>"><?php endforeach; ?></datalist>
		<datalist id="usc-dl-schild"><?php foreach ( $schild_liste as $kurz => $bez ) : ?><option value="<?= esc_attr( (string) $kurz ) ?>" label="<?= esc_attr( $bez ) ?>"><?php endforeach; ?></datalist>
	</div>
</section>

<?php include __DIR__ . '/partial-regelpruefung.php'; ?>

<section class="mh-usc-card">
	<div class="mh-usc-card-head">
		<h3>Fachregeln</h3>
		<span class="mh-usc-badge is-blue"><?= count( $regeln ) ?> Regeln, <?= (int) $aktive ?> aktiv</span>
		<?php if ( $ueberdeckt ) : ?><span class="mh-usc-badge is-amber"><?= count( $ueberdeckt ) ?> Regeln mit Überschneidungen</span><?php endif; ?>
	</div>
	<div class="mh-usc-card-body">
		<?php if ( [] === $regeln ) : ?>
			<p class="mh-usc-muted">Noch keine Fachregeln.</p>
		<?php else : ?>
			<div class="mh-usc-toolbar">
				<input type="search" class="mh-usc-search" placeholder="Regeln durchsuchen …" data-filter="#usc-f-liste" aria-label="Regeln durchsuchen">
			</div>
			<div class="mh-usc-scroll">
				<table class="mh-usc-table is-stack" id="usc-f-liste">
					<thead><tr><th>Nr.</th><th>aktiv</th><th>Untis-Fach</th><th>Wirkung</th><th>in Klassen</th><th>Bemerkung</th><th>wirkt auf</th><th>geändert</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $regeln as $i => $r ) :
							$klassen = $regel_klassen[ $r->kennung() ] ?? [];
							$zeilen  = null !== $ergebnis ? $ergebnis->treffer_fuer( $r->kennung() ) : null;
							$aend    = $aenderungen[ $r->id ] ?? null;
							?>
							<tr class="<?= $r->aktiv ? '' : 'is-off' ?> <?= $r->id === $bearbeiten ? 'is-highlight' : '' ?>" id="usc-regel-<?= (int) $r->id ?>" data-id="<?= (int) $r->id ?>"
								data-suche="<?= esc_attr( implode( ' ', array_merge( $r->untis_faecher, $r->schild_faecher, [ $r->klassen_muster, $r->bemerkung, Regel_Text::fach_satz( $r ) ] ) ) ) ?>">
								<td class="is-nr" data-l="Nr."><?= (int) ( $nummern[ $r->id ] ?? 0 ) ?></td>
								<td data-l="aktiv"><?php include __DIR__ . '/partial-regel-schalter.php'; ?></td>
								<td data-l="Untis-Fach"><?= $liste( $r->untis_faecher, 'Fächer' ) // phpcs:ignore -- in $lesbar escaped ?></td>
								<td data-l="Wirkung">
									<span class="mh-usc-badge <?= esc_attr( $badge[ $r->art() ] ) ?>"><?= esc_html( $arten[ $r->art() ][0] ) ?></span>
									<?php if ( [] !== $r->schild_faecher ) : ?>
										<br><span class="mh-usc-arrow">→</span><?= $liste( $r->schild_faecher, 'Fächer' ) // phpcs:ignore ?>
									<?php endif; ?>
								</td>
								<td data-l="in Klassen"><?= Muster::ist_alle( $r->klassen_muster ) ? '<span class="mh-usc-muted">alle</span>' : $liste( Muster::teile( $r->klassen_muster ), 'Klassen' ) // phpcs:ignore ?></td>
								<td data-l="Bemerkung"><?= esc_html( $r->bemerkung ) ?></td>
								<td data-l="wirkt auf">
									<?php if ( null === $zeilen ) : ?>
										<span class="mh-usc-muted">–</span>
									<?php elseif ( 0 === $zeilen ) : ?>
										<span class="mh-usc-badge is-grey"><?= $r->aktiv ? 'ohne Treffer' : 'inaktiv' ?></span>
									<?php else : ?>
										<b><?= (int) $zeilen ?></b> Zeilen in <?= count( $klassen ) ?> <?= 1 === count( $klassen ) ? 'Klasse' : 'Klassen' ?>
										<div class="mh-usc-klassen"><?= esc_html( implode( ', ', array_slice( $klassen, 0, 15 ) ) . ( count( $klassen ) > 15 ? ' …' : '' ) ) ?></div>
									<?php endif; ?>
									<?php if ( isset( $ueberdeckt[ $r->kennung() ] ) ) : ?>
										<div><a class="mh-usc-badge is-amber" href="<?= esc_url( $tab_url( 'ergebnis', [ 'usc_schritt' => 'faecher' ] ) ) ?>"
											title="In diesen Fällen hätten auch weiter unten stehende Regeln gepasst; angewendet wurde diese.">überdeckt andere in <?= (int) $ueberdeckt[ $r->kennung() ] ?> Fällen</a></div>
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

<?php $text_typ = 'faecher'; $text_format = 'Untis-Fächer ; Schild-Fächer ; Klassen ; Bemerkung'; $text_beispiel = "VERT ; ; ; Vertretung\nER ; Reli ; ; \nLFU1|LFU2 ; WSP|KUP|KPA ; AK* ; Lernfelder Automobil"; ?>
<?php include __DIR__ . '/partial-regel-text.php'; ?>

<script>
( function () {
	var form = document.getElementById( 'usc-fr' );
	if ( ! form || ! window.mhUsc ) { return; }
	var usc         = window.mhUsc;
	var fachKlassen = JSON.parse( form.dataset.fachKlassen || '{}' );
	var schild      = JSON.parse( form.dataset.schild || '{}' );
	var felder      = {};

	form.querySelectorAll( '.mh-usc-chipfeld' ).forEach( function ( el ) {
		felder[ el.dataset.ziel ] = usc.bausteinfeld( el, { form: form, schild: schild, beiAenderung: function () { aktualisieren(); } } );
	} );
	var untis   = felder['#usc-f-untis'];
	var schildF = felder['#usc-f-schild'];
	var klassen = felder['#usc-f-klassen'];

	var labelUntis  = document.getElementById( 'usc-l-untis' );
	var labelSchild = document.getElementById( 'usc-l-schild' );
	var nrKlassen   = form.querySelector( '.mh-usc-nr-klassen' );
	function art() { var r = form.querySelector( 'input[name=art]:checked' ); return r ? r.value : ''; }
	function modus() { var r = form.querySelector( 'input[name=klassen_modus]:checked' ); return r ? r.value : 'alle'; }

	function aktualisieren() {
		var a = art();
		if ( a ) { form.dataset.art = a; } else { delete form.dataset.art; } // ohne Art: Felder wieder verbergen
		var maxU = a === 'umbenennen' ? 1 : 0;
		var maxS = ( a === 'umbenennen' || a === 'zusammenfassen' ) ? 1 : 0;
		// begrenzen() kürzt überzählige Bausteine und ruft über beiAenderung erneut aktualisieren() auf.
		if ( untis.max !== maxU ) { untis.begrenzen( maxU ); return; }
		if ( schildF.max !== maxS ) { schildF.begrenzen( maxS ); return; }
		labelUntis.textContent  = '2. ' + ( a === 'umbenennen' ? 'Welches Untis-Fach?' : ( a === 'zusammenfassen' ? 'Welche Untis-Fächer? (mindestens zwei)' : 'Welche Untis-Fächer?' ) );
		labelSchild.textContent = '3. ' + ( a === 'aufteilen' ? 'In welche Schild-Fächer? (mindestens zwei)' : 'Wie heißt das Fach in Schild?' );
		nrKlassen.textContent   = a === 'loeschen' ? '3.' : '4.';
		form.querySelector( '.mh-usc-klassenwahl' ).style.display = modus() === 'alle' ? 'none' : '';
		satz();
	}

	// Klartext der Regel und Wirkung im aktuellen Import.
	function satz() {
		var box = document.getElementById( 'usc-satz' );
		var a = art(), u = untis.liste(), s = schildF.liste();
		var kMuster = modus() === 'alle' ? '' : klassen.ziel.value;
		box.className = 'mh-usc-satz';
		box.innerHTML = '';
		if ( ! a || ! u.length ) { return; }

		var wo  = kMuster ? 'in den Klassen ' + usc.aufzaehlung( klassen.liste(), 'Klassen' ) : 'in allen Klassen';
		var was = usc.aufzaehlung( u, 'Fächer' ), ziel = usc.aufzaehlung( s ), t;
		if ( a === 'loeschen' ) { t = was + ' ' + ( u.length > 1 ? 'werden' : 'wird' ) + ' ' + wo + ' entfernt.'; }
		else if ( ! s.length ) { t = 'Bitte noch ' + ( a === 'aufteilen' ? 'die Schild-Fächer' : 'das Schild-Fach' ) + ' angeben.'; }
		else if ( a === 'umbenennen' ) { t = was + ' heißt in Schild ' + ziel + ' – ' + wo + '.'; }
		else if ( a === 'zusammenfassen' ) { t = was + ' werden ' + wo + ' zu ' + ziel + ' zusammengefasst.'; }
		else { t = was + ' ' + ( u.length > 1 ? 'werden' : 'wird' ) + ' ' + wo + ' aufgeteilt in ' + ziel + '. Jede beteiligte Lehrkraft erhält alle diese Fächer; die Wochenstunden bleiben leer.'; }

		var zeilen = 0, kl = {};
		Object.keys( fachKlassen ).forEach( function ( f ) {
			if ( ! u.some( function ( m ) { return usc.passt( m, f ); } ) ) { return; }
			Object.keys( fachKlassen[ f ] ).forEach( function ( k ) {
				if ( kMuster && ! usc.passt( kMuster, k ) ) { return; }
				zeilen += fachKlassen[ f ][ k ]; kl[ k ] = true;
			} );
		} );
		var klListe = Object.keys( kl ).sort();
		var st = document.createElement( 'strong' ); st.textContent = t; box.appendChild( st );
		if ( Object.keys( fachKlassen ).length ) {
			if ( zeilen ) {
				box.appendChild( usc.text( 'Im aktuellen Import betrifft das ' + zeilen + ' Zeilen in ' + klListe.length + ( klListe.length === 1 ? ' Klasse' : ' Klassen' ) + ': '
					+ klListe.slice( 0, 15 ).join( ', ' ) + ( klListe.length > 15 ? ' …' : '' ) ) );
			} else {
				box.classList.add( 'is-warn' );
				box.appendChild( usc.text( 'Im aktuellen Import trifft die Regel keine Zeile – Schreibweise prüfen?' ) );
			}
		}
	}

	form.querySelectorAll( 'input[name=art], input[name=klassen_modus]' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () {
			aktualisieren();
			Object.keys( felder ).forEach( function ( k ) { felder[ k ].zeichne(); } );
			if ( r.name === 'art' ) { untis.eingabe.focus(); }
			if ( r.name === 'klassen_modus' && r.value === 'auswahl' ) { klassen.eingabe.focus(); }
		} );
	} );
	form.addEventListener( 'submit', function () {
		// Noch nicht bestätigte Eingaben übernehmen.
		Object.keys( felder ).forEach( function ( k ) { if ( felder[ k ].eingabe.value.trim() ) { felder[ k ].hinzu( felder[ k ].eingabe.value ); } } );
		if ( modus() === 'alle' ) { klassen.ziel.value = ''; }
	} );
	aktualisieren();
	Object.keys( felder ).forEach( function ( k ) { felder[ k ].zeichne(); } );
	// Erst jetzt ausblenden, was von der Art abhängt – schlägt oben etwas fehl, bleibt alles sichtbar.
	usc.abwaehlbar( form, 'art', aktualisieren );
	form.classList.add( 'is-js' );
} )();
</script>
