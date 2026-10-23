<?php
/**
 * Partial: Knöpfe einer Regelzeile (verschieben, aktiv, bearbeiten, löschen).
 *
 * @var string                                                      $typ    klassen|faecher
 * @var Mh\UntisSchild\Model\Klassen_Regel|Mh\UntisSchild\Model\Fach_Regel $r
 * @var bool                                                        $erste
 * @var bool                                                        $letzte
 * (+ $formular, $post_url, $tab_url aus layout.php)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$knopf = static function ( string $was, string $text, string $titel, string $klasse = '', bool $aus = false, string $frage = '' ) use ( $formular, $post_url, $typ, $r ): void {
	?>
	<form method="post" action="<?= esc_url( $post_url ) ?>" <?= '' !== $frage ? 'data-confirm="' . esc_attr( $frage ) . '"' : '' ?>>
		<?php $formular( 'usc_regel_aktion', $typ ); ?>
		<input type="hidden" name="typ" value="<?= esc_attr( $typ ) ?>">
		<input type="hidden" name="id" value="<?= (int) $r->id ?>">
		<input type="hidden" name="was" value="<?= esc_attr( $was ) ?>">
		<button type="submit" class="<?= esc_attr( $klasse ) ?>" title="<?= esc_attr( $titel ) ?>" aria-label="<?= esc_attr( $titel ) ?>" <?= $aus ? 'disabled' : '' ?>><?= esc_html( $text ) ?></button>
	</form>
	<?php
};
?>
<?php $knopf( 'oben', '↑', 'nach oben (wird früher angewendet)', 'mh-usc-btn mh-usc-btn-icon', $erste ); ?>
<?php $knopf( 'unten', '↓', 'nach unten', 'mh-usc-btn mh-usc-btn-icon', $letzte ); ?>
<a class="mh-usc-btn mh-usc-btn-small" href="<?= esc_url( $tab_url( $typ, [ 'usc_bearbeiten' => $r->id ] ) ) ?>">Bearbeiten</a>
<?php $knopf( 'loeschen', 'Löschen', 'Regel löschen', 'mh-usc-btn mh-usc-btn-small mh-usc-btn-danger', false, 'Diese Regel endgültig löschen? (Deaktivieren hebt sie auf.)' ); ?>
