<?php
/**
 * Partial: Regeln als Textblock einfügen bzw. kopieren.
 *
 * @var string $text_typ       klassen|faecher
 * @var string $text_format
 * @var string $text_beispiel
 * @var string $als_text       aktuelle Regeln als Text
 * @var bool   $text_fehler    Wurde der Textblock mit Fehlern abgeschickt?
 * (+ Variablen aus layout.php)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<details class="mh-usc-card" <?= $text_fehler ? 'open' : '' ?>>
	<summary class="mh-usc-card-head"><h3>Mehrere Regeln als Text einfügen oder kopieren</h3></summary>
	<div class="mh-usc-card-body">
		<form method="post" action="<?= esc_url( $post_url ) ?>">
			<?php $formular( 'usc_regeln_einfuegen', $text_typ ); ?>
			<input type="hidden" name="typ" value="<?= esc_attr( $text_typ ) ?>">
			<div class="mh-usc-grid">
				<div class="mh-usc-field is-full">
					<label for="usc-text-<?= esc_attr( $text_typ ) ?>">Eine Regel pro Zeile: <code><?= esc_html( $text_format ) ?></code></label>
					<textarea id="usc-text-<?= esc_attr( $text_typ ) ?>" name="text" placeholder="<?= esc_attr( $text_beispiel ) ?>"><?= esc_textarea( $text_fehler ? $alt( 'text' ) : '' ) ?></textarea>
					<small>Felder mit Semikolon trennen. Listen aus der Vorversion mit Komma werden ebenfalls gelesen. Zeilen mit # werden übersprungen.
						Enthält eine Zeile einen Fehler, wird nichts gespeichert.</small>
				</div>
			</div>
			<div class="mh-usc-actions">
				<label class="mh-usc-check"><input type="radio" name="modus" value="hinzufuegen" <?php checked( 'ersetzen' !== $alt( 'modus' ) ); ?>> zu den bestehenden Regeln hinzufügen</label>
				<label class="mh-usc-check"><input type="radio" name="modus" value="ersetzen" <?php checked( 'ersetzen' === $alt( 'modus' ) ); ?>> alle bestehenden Regeln ersetzen</label>
				<label class="mh-usc-check"><input type="checkbox" name="bestaetigt" value="1"> ja, beim Ersetzen alle bisherigen Regeln löschen</label>
				<button type="submit" class="mh-usc-btn mh-usc-btn-primary">Prüfen und speichern</button>
			</div>
		</form>

		<?php if ( '' !== $als_text ) : ?>
			<h4 style="margin:22px 0 6px">Aktuelle Regeln als Text</h4>
			<div class="mh-usc-field">
				<textarea id="usc-als-text-<?= esc_attr( $text_typ ) ?>" readonly rows="8"><?= esc_textarea( $als_text ) ?></textarea>
			</div>
			<div class="mh-usc-actions">
				<button type="button" class="mh-usc-btn mh-usc-btn-small" data-copy="#usc-als-text-<?= esc_attr( $text_typ ) ?>">Kopieren</button>
				<span class="mh-usc-small mh-usc-muted">z. B. als Sicherung oder zum Übertragen auf ein anderes System</span>
			</div>
		<?php endif; ?>
	</div>
</details>
