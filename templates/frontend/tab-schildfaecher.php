<?php
/**
 * View: Reiter „Schild-Fächer“.
 *
 * @var object[]    $faecher      (interne_kurzform, asd_kuerzel, bezeichnung)
 * @var array|false $schild_stand [ datum, von, datei ]
 * @var array<string, array{untis:bool, regel_id:?int}> $herkunft fehlendes Fach => Herkunft
 * (+ Variablen aus layout.php)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$fehlend = null !== $ergebnis ? $ergebnis->unbekannte_faecher : [];
?>
<p class="mh-usc-intro">
	Mit der Fächerliste aus Schild prüft das Plugin, ob Schild jedes Fach der Importdatei kennt. Unbekannte Fächer würden beim Import in
	Schild scheitern – sie lassen sich meist mit einer Fachregel umbenennen oder müssen in Schild angelegt werden.
</p>

<?php if ( [] !== $fehlend ) : ?>
	<section class="mh-usc-card">
		<div class="mh-usc-card-head">
			<h3>Fächer, die in Schild fehlen</h3>
			<span class="mh-usc-badge is-amber"><?= count( $fehlend ) ?></span>
		</div>
		<div class="mh-usc-card-body">
			<p class="mh-usc-small mh-usc-muted">„Ignorieren“ legt eine vorausgefüllte Regel an, die das Fach in allen Klassen entfernt – du prüfst sie vor dem Speichern.
				Ist ein Fach erst durch eine eigene Regel entstanden, muss diese Regel korrigiert werden.</p>
			<table class="mh-usc-table is-stack">
				<thead><tr><th>Fach</th><th>Herkunft</th><th>Klassen</th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $fehlend as $fach => $klassen ) :
						$fach = (string) $fach;
						$h    = $herkunft[ $fach ] ?? [ 'untis' => true, 'regel_id' => null ];
						?>
						<tr>
							<td data-l="Fach"><code><?= esc_html( $fach ) ?></code></td>
							<td data-l="Herkunft">
								<?php if ( null !== $h['regel_id'] ) : ?>
									<span class="mh-usc-badge is-amber">aus einer Fachregel</span>
								<?php else : ?>
									<span class="mh-usc-badge is-grey">aus Untis</span>
								<?php endif; ?>
							</td>
							<td data-l="Klassen" class="mh-usc-klassen" style="max-width:none"><?= esc_html( implode( ', ', $klassen ) ) ?></td>
							<td class="is-actions">
								<?php if ( null !== $h['regel_id'] ) : ?>
									<a class="mh-usc-btn mh-usc-btn-small" href="<?= esc_url( $tab_url( 'faecher', [ 'usc_bearbeiten' => $h['regel_id'] ] ) ) ?>">Regel korrigieren</a>
								<?php endif; ?>
								<?php if ( $h['untis'] ) : ?>
									<a class="mh-usc-btn mh-usc-btn-small" href="<?= esc_url( $tab_url( 'faecher', [ 'usc_vorlage' => [
										'art' => 'loeschen', 'untis' => $fach, 'bemerkung' => 'Fehlt in Schild – wird nicht übertragen',
									] ] ) ) ?>">Ignorieren</a>
									<a class="mh-usc-btn mh-usc-btn-small" href="<?= esc_url( $tab_url( 'faecher', [ 'usc_vorlage' => [
										'art' => 'umbenennen', 'untis' => $fach,
									] ] ) ) ?>">Umbenennen …</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
<?php elseif ( null !== $ergebnis && $ergebnis->abgleich ) : ?>
	<div class="mh-usc-flash">Alle Fächer der Importdatei sind in der Schild-Fächerliste vorhanden.</div>
<?php endif; ?>

<section class="mh-usc-card">
	<div class="mh-usc-card-head"><h3>Fächerliste aus Schild hochladen</h3></div>
	<div class="mh-usc-card-body">
		<form method="post" action="<?= esc_url( $post_url ) ?>" enctype="multipart/form-data">
			<?php $formular( 'usc_schildfaecher', 'schildfaecher' ); ?>
			<div class="mh-usc-grid">
				<div class="mh-usc-field is-wide">
					<label for="usc-xlsx">Excel-Datei</label>
					<input type="file" id="usc-xlsx" name="xlsx" accept=".xlsx,.xls" required>
					<small>In Schild: Unterrichtsfächer → Excel-Export. Gelesen werden „interne Kurzform“, „ASD-Kürzel“ und „Bezeichnung“.
						Die bisherige Liste wird ersetzt.</small>
				</div>
			</div>
			<div class="mh-usc-actions"><button type="submit" class="mh-usc-btn mh-usc-btn-primary">Hochladen und ersetzen</button></div>
		</form>
	</div>
</section>

<section class="mh-usc-card">
	<div class="mh-usc-card-head">
		<h3>Aktuelle Schild-Fächer</h3>
		<span class="mh-usc-badge is-blue"><?= count( $faecher ) ?></span>
		<?php if ( is_array( $schild_stand ) ) : ?>
			<span class="mh-usc-small mh-usc-muted">eingelesen am <?= esc_html( mysql2date( 'd.m.Y', (string) $schild_stand['datum'] ) ) ?> von <?= esc_html( $benutzer( (int) $schild_stand['von'] ) ) ?></span>
		<?php endif; ?>
	</div>
	<div class="mh-usc-card-body">
		<?php if ( [] === $faecher ) : ?>
			<p class="mh-usc-muted">Noch keine Fächerliste vorhanden – der Abgleich ist ausgeschaltet.</p>
		<?php else : ?>
			<div class="mh-usc-toolbar"><input type="search" class="mh-usc-search" placeholder="Fach suchen …" data-filter="#usc-sf" aria-label="Fach suchen"></div>
			<div class="mh-usc-scroll" style="max-height:480px;overflow-y:auto">
				<table class="mh-usc-table" id="usc-sf">
					<thead><tr><th>Kurzform</th><th>ASD</th><th>Bezeichnung</th></tr></thead>
					<tbody>
						<?php foreach ( $faecher as $f ) : ?>
							<tr data-suche="<?= esc_attr( $f->interne_kurzform . ' ' . $f->asd_kuerzel . ' ' . $f->bezeichnung ) ?>">
								<td><code><?= esc_html( $f->interne_kurzform ) ?></code></td>
								<td><?= esc_html( $f->asd_kuerzel ) ?></td>
								<td><?= esc_html( $f->bezeichnung ) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</section>
