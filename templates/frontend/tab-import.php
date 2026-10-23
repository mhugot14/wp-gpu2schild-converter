<?php
/**
 * View: Reiter „Import“.
 *
 * @var ?object  $import
 * @var object[] $importe
 * @var array    $gruppen        Gruppe => Anzahl
 * @var int      $anzahl_klassen
 * @var int      $anzahl_faecher
 * @var int      $vorschlag_jahr
 * @var int      $vorschlag_hj
 * (+ Variablen aus layout.php)
 */

use Mh\UntisSchild\Controller\Converter_Controller;
use Mh\UntisSchild\Model\Unterricht_Zeile;

if ( ! defined( 'ABSPATH' ) ) exit;

$jahr_optionen = static function ( int $gewaehlt ) use ( $vorschlag_jahr ): void {
	for ( $j = $vorschlag_jahr - 1; $j <= $vorschlag_jahr + 1; $j++ ) {
		printf( '<option value="%d"%s>%s</option>', $j, selected( $j, $gewaehlt, false ), esc_html( Converter_Controller::schuljahr_text( $j ) ) );
	}
};
?>
<p class="mh-usc-intro">
	Lade die Unterrichtsdatei aus Untis hoch. Sie wird nicht verändert: Alle Regeln und Entscheidungen wirken beim Anzeigen,
	deshalb muss nach einer Regeländerung nichts erneut hochgeladen werden. Die hochgeladene Datei selbst wird nicht aufbewahrt.
</p>

<section class="mh-usc-card">
	<div class="mh-usc-card-head"><h3><?= null === $import ? 'GPU002 hochladen' : 'Neue GPU002 hochladen' ?></h3></div>
	<div class="mh-usc-card-body">
		<form method="post" action="<?= esc_url( $post_url ) ?>" enctype="multipart/form-data">
			<?php $formular( 'usc_import', 'import' ); ?>
			<div class="mh-usc-grid">
				<div class="mh-usc-field is-wide">
					<label for="usc-gpu">Datei (GPU002.TXT)</label>
					<input type="file" id="usc-gpu" name="gpu" accept=".txt,.csv" required>
					<small>In Untis: Datei → Import/Export → Untis → Unterricht (aktuelle Periode). Kodierung und Trennzeichen werden erkannt.</small>
				</div>
				<div class="mh-usc-field">
					<label for="usc-sj">Schuljahr</label>
					<select id="usc-sj" name="schuljahr"><?php $jahr_optionen( (int) $alt( 'schuljahr', (string) $vorschlag_jahr ) ); ?></select>
				</div>
				<div class="mh-usc-field">
					<label for="usc-hj">Halbjahr</label>
					<select id="usc-hj" name="halbjahr">
						<option value="1" <?php selected( 1, (int) $alt( 'halbjahr', (string) $vorschlag_hj ) ); ?>>1. Halbjahr</option>
						<option value="2" <?php selected( 2, (int) $alt( 'halbjahr', (string) $vorschlag_hj ) ); ?>>2. Halbjahr</option>
					</select>
					<small>Im 1. Halbjahr entfällt Unterricht der Gruppe „2. HJ“.</small>
				</div>
			</div>
			<div class="mh-usc-actions">
				<button type="submit" class="mh-usc-btn mh-usc-btn-primary">Hochladen und einlesen</button>
				<?php if ( null !== $import ) : ?><span class="mh-usc-muted mh-usc-small">Ersetzt den aktuellen Import. Regeln und Entscheidungen bleiben erhalten.</span><?php endif; ?>
			</div>
		</form>
	</div>
</section>

<?php if ( null !== $import ) : ?>
	<section class="mh-usc-card">
		<div class="mh-usc-card-head">
			<h3>Aktueller Import</h3>
			<span class="mh-usc-badge is-blue"><?= esc_html( $import->dateiname ) ?></span>
		</div>
		<div class="mh-usc-card-body">
			<div class="mh-usc-stats">
				<div class="mh-usc-stat"><b><?= (int) $import->zeilen ?></b><span>Unterrichtszeilen</span></div>
				<div class="mh-usc-stat"><b><?= (int) $anzahl_klassen ?></b><span>Klassen</span></div>
				<div class="mh-usc-stat"><b><?= (int) $anzahl_faecher ?></b><span>Fächer</span></div>
				<div class="mh-usc-stat"><b><?= (int) $import->uebersprungen ?></b><span>übersprungen (ohne Klasse oder Fach)</span></div>
			</div>
			<p class="mh-usc-small mh-usc-muted">
				Hochgeladen am <?= esc_html( mysql2date( 'd.m.Y \u\m H:i', $import->erstellt_am ) ) ?> Uhr von <?= esc_html( $benutzer( (int) $import->erstellt_von ) ) ?>
				· Kodierung <?= esc_html( $import->kodierung ) ?> · Trennzeichen <?= esc_html( $import->trennzeichen ) ?>
			</p>

			<h4 style="margin:16px 0 6px">Spalte „Gruppe“ – so werden die Werte gelesen</h4>
			<table class="mh-usc-table" style="max-width:560px">
				<thead><tr><th>Gruppe</th><th class="is-num">Zeilen</th><th>gilt für</th></tr></thead>
				<tbody>
					<?php foreach ( $gruppen as $gruppe => $anzahl ) : $gilt = Unterricht_Zeile::gilt_aus_gruppe( (string) $gruppe ); ?>
						<tr>
							<td><?= '' === (string) $gruppe ? '<span class="mh-usc-muted">(leer)</span>' : '<code>' . esc_html( (string) $gruppe ) . '</code>' ?></td>
							<td class="is-num"><?= (int) $anzahl ?></td>
							<td>
								<?php if ( Unterricht_Zeile::GILT_GANZJAEHRIG === $gilt ) : ?>
									<span class="mh-usc-badge is-grey">ganzjährig</span>
								<?php else : ?>
									<span class="mh-usc-badge is-blue">nur <?= esc_html( $gilt ) ?>. Halbjahr</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<details style="margin-top:16px">
				<summary class="mh-usc-small" style="cursor:pointer">Falsches Schuljahr oder Halbjahr gewählt? Hier ändern, ohne neu hochzuladen.</summary>
				<form method="post" action="<?= esc_url( $post_url ) ?>" class="mh-usc-actions">
					<?php $formular( 'usc_import_meta', 'import' ); ?>
					<input type="hidden" name="id" value="<?= (int) $import->id ?>">
					<div class="mh-usc-field"><select name="schuljahr" aria-label="Schuljahr"><?php $jahr_optionen( (int) $import->schuljahr ); ?></select></div>
					<div class="mh-usc-field">
						<select name="halbjahr" aria-label="Halbjahr">
							<option value="1" <?php selected( 1, (int) $import->halbjahr ); ?>>1. Halbjahr</option>
							<option value="2" <?php selected( 2, (int) $import->halbjahr ); ?>>2. Halbjahr</option>
						</select>
					</div>
					<button type="submit" class="mh-usc-btn">Übernehmen</button>
				</form>
			</details>
		</div>
	</section>

	<details class="mh-usc-card">
		<summary class="mh-usc-card-head"><h3>Bisherige Importe (<?= count( $importe ) ?>)</h3></summary>
		<div class="mh-usc-card-body">
			<p class="mh-usc-small mh-usc-muted">Verwendet wird immer der neueste Import. Es werden die letzten <?= (int) \Mh\UntisSchild\Repository\Import_Repository::BEHALTEN ?> aufbewahrt.</p>
			<table class="mh-usc-table is-stack">
				<thead><tr><th>Hochgeladen</th><th>Datei</th><th>Schuljahr</th><th class="is-num">Zeilen</th><th>von</th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $importe as $i ) : ?>
						<tr class="<?= (int) $i->id === (int) $import->id ? 'is-highlight' : '' ?>">
							<td data-l="Hochgeladen"><?= esc_html( mysql2date( 'd.m.Y H:i', $i->erstellt_am ) ) ?></td>
							<td data-l="Datei"><?= esc_html( $i->dateiname ) ?> <?= (int) $i->id === (int) $import->id ? '<span class="mh-usc-badge is-green">aktuell</span>' : '' ?></td>
							<td data-l="Schuljahr"><?= esc_html( Converter_Controller::schuljahr_text( (int) $i->schuljahr ) ) ?>, <?= (int) $i->halbjahr ?>. HJ</td>
							<td class="is-num" data-l="Zeilen"><?= (int) $i->zeilen ?></td>
							<td data-l="von"><?= esc_html( $benutzer( (int) $i->erstellt_von ) ) ?></td>
							<td class="is-actions">
								<form method="post" action="<?= esc_url( $post_url ) ?>" data-confirm="Diesen Import löschen?">
									<?php $formular( 'usc_import_loeschen', 'import' ); ?>
									<input type="hidden" name="id" value="<?= (int) $i->id ?>">
									<button type="submit" class="mh-usc-btn mh-usc-btn-small mh-usc-btn-danger">Löschen</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</details>
<?php endif; ?>
