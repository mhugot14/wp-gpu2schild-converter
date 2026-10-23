<?php
/**
 * Partial: Aktiv-Schalter einer Regel (ein Klick = Formular absenden).
 *
 * @var string $typ
 * @var Mh\UntisSchild\Model\Klassen_Regel|Mh\UntisSchild\Model\Fach_Regel $r
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<form method="post" action="<?= esc_url( $post_url ) ?>">
	<?php $formular( 'usc_regel_aktion', $typ ); ?>
	<input type="hidden" name="typ" value="<?= esc_attr( $typ ) ?>">
	<input type="hidden" name="id" value="<?= (int) $r->id ?>">
	<input type="hidden" name="was" value="<?= $r->aktiv ? 'deaktivieren' : 'aktivieren' ?>">
	<button type="submit" class="mh-usc-switch <?= $r->aktiv ? 'is-on' : '' ?>" role="switch" aria-checked="<?= $r->aktiv ? 'true' : 'false' ?>"
		title="<?= $r->aktiv ? 'aktiv – klicken zum Deaktivieren' : 'inaktiv – klicken zum Aktivieren' ?>"></button>
</form>
