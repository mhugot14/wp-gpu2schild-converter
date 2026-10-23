<?php
/**
 * View: Hinweis für nicht angemeldete oder nicht freigeschaltete Besucher.
 *
 * @var string $login_url
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<?php include __DIR__ . '/partial-style.php'; ?>
<div class="mh-usc">
	<div class="mh-usc-notice is-info">
		<?php if ( ! is_user_logged_in() ) : ?>
			Der Untis2Schild Converter steht nur angemeldeten und freigeschalteten Personen zur Verfügung.
			<a href="<?= esc_url( $login_url ) ?>">Jetzt anmelden</a>
		<?php else : ?>
			Für den Untis2Schild Converter bist du nicht freigeschaltet. Bitte wende dich an die Administration.
		<?php endif; ?>
	</div>
</div>
