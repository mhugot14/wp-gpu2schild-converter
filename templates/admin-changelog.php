<?php
/**
 * View: Changelog im Backend.
 *
 * Dieselbe Datei liegt in mh_form_workflows, untisSchildConverter und webuntisAnalyser –
 * Änderungen am Aussehen bitte in allen drei Plugins nachziehen.
 *
 * Erwartet:
 * @var string  $cl_titel       Name des Plugins
 * @var string  $cl_installiert Installierte Version (aus dem Plugin-Header)
 * @var array[] $cl_releases    Neueste zuerst. Je Eintrag:
 *                              version (string|null, null = in Arbeit), datum (Y-m-d oder Freitext),
 *                              zusammenfassung?, neu?, verbessert?, behoben?, sicherheit?, technik?, hinweis?
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$cl_abschnitte = [
	'neu'        => [ 'Neu', '#1b7f3a', '#e7f5e9' ],
	'verbessert' => [ 'Verbessert', '#004f9f', '#e5f0fa' ],
	'behoben'    => [ 'Behoben', '#8a5a00', '#fcf3d9' ],
	'sicherheit' => [ 'Sicherheit & Datenschutz', '#b32d2e', '#fbe9e7' ],
	'technik'    => [ 'Technik', '#50575e', '#f0f0f1' ],
];
$cl_erlaubt = [ 'code' => [], 'strong' => [], 'em' => [] ];

$cl_datum = static function ( string $datum ): string {
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ) {
		$ts = strtotime( $datum . ' 12:00:00' );
		return false === $ts ? $datum : date_i18n( 'j. F Y', $ts );
	}
	return $datum;
};
$cl_offen = 3; // So viele Versionen sind aufgeklappt, der Rest ist eingeklappt.
?>
<style>
	.mh-cl { max-width: 980px; margin: 20px 20px 40px 0; }
	.mh-cl *, .mh-cl *::before { box-sizing: border-box; }
	.mh-cl-head { background: #004f9f; color: #fff; padding: 28px 32px; border-radius: 8px; margin-bottom: 18px; }
	.mh-cl-head h1 { color: #fff !important; margin: 0 0 6px; font-size: 26px; font-weight: 700; padding: 0; }
	.mh-cl-head p { margin: 0; font-size: 14px; opacity: .92; line-height: 1.5; }
	.mh-cl-rel { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
	.mh-cl-rel > summary { list-style: none; cursor: pointer; padding: 14px 20px; display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 14px; }
	.mh-cl-rel > summary::-webkit-details-marker { display: none; }
	.mh-cl-rel > summary::before { content: '▸'; color: #8c8f94; width: 10px; }
	.mh-cl-rel[open] > summary::before { content: '▾'; }
	.mh-cl-rel[open] > summary { border-bottom: 1px solid #f0f0f1; }
	.mh-cl-ver { font-size: 18px; font-weight: 700; color: #1d2327; }
	.mh-cl-date { color: #646970; font-size: 13px; }
	.mh-cl-sum { flex-basis: 100%; padding-left: 24px; color: #3c434a; font-size: 13px; }
	.mh-cl-badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; background: #e7f5e9; color: #1b7f3a; }
	.mh-cl-badge.is-wip { background: #fcf3d9; color: #8a5a00; }
	.mh-cl-body { padding: 6px 20px 16px 44px; }
	.mh-cl-sec h3 { display: inline-block; margin: 14px 0 6px; padding: 2px 10px; border-radius: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }
	.mh-cl-sec ul { margin: 0 0 4px 18px; list-style: disc; }
	.mh-cl-sec li { margin: 0 0 5px; line-height: 1.5; font-size: 13.5px; }
	.mh-cl-note { margin: 12px 0 0; padding: 10px 14px; border-left: 4px solid #004f9f; background: #f6f7f7; font-size: 13px; }
	@media (max-width: 600px) { .mh-cl-head { padding: 20px; } .mh-cl-body { padding-left: 20px; } }
</style>
<div class="wrap mh-cl">
	<div class="mh-cl-head">
		<h1>Changelog – <?php echo esc_html( $cl_titel ); ?></h1>
		<p>Installiert ist Version <strong><?php echo esc_html( $cl_installiert ); ?></strong>. Hier steht je Release, was neu hinzugekommen ist, was verbessert und was behoben wurde – die neueste Version zuerst.</p>
	</div>

	<?php foreach ( $cl_releases as $cl_i => $cl_rel ) :
		$cl_version = $cl_rel['version'] ?? null;
		$cl_wip     = null === $cl_version;
		?>
		<details class="mh-cl-rel"<?php echo $cl_i < $cl_offen ? ' open' : ''; ?>>
			<summary>
				<span class="mh-cl-ver"><?php echo esc_html( $cl_wip ? 'Nächste Version' : 'Version ' . $cl_version ); ?></span>
				<?php if ( ! empty( $cl_rel['datum'] ) ) : ?>
					<span class="mh-cl-date"><?php echo esc_html( $cl_datum( (string) $cl_rel['datum'] ) ); ?></span>
				<?php endif; ?>
				<?php if ( $cl_wip ) : ?>
					<span class="mh-cl-badge is-wip">in Arbeit, noch nicht veröffentlicht</span>
				<?php elseif ( $cl_version === $cl_installiert ) : ?>
					<span class="mh-cl-badge">installiert</span>
				<?php endif; ?>
				<?php if ( ! empty( $cl_rel['zusammenfassung'] ) ) : ?>
					<span class="mh-cl-sum"><?php echo wp_kses( (string) $cl_rel['zusammenfassung'], $cl_erlaubt ); ?></span>
				<?php endif; ?>
			</summary>
			<div class="mh-cl-body">
				<?php foreach ( $cl_abschnitte as $cl_key => [ $cl_label, $cl_farbe, $cl_grund ] ) :
					if ( empty( $cl_rel[ $cl_key ] ) ) {
						continue;
					}
					?>
					<div class="mh-cl-sec">
						<h3 style="color: <?php echo esc_attr( $cl_farbe ); ?>; background: <?php echo esc_attr( $cl_grund ); ?>;"><?php echo esc_html( $cl_label ); ?></h3>
						<ul>
							<?php foreach ( (array) $cl_rel[ $cl_key ] as $cl_punkt ) : ?>
								<li><?php echo wp_kses( (string) $cl_punkt, $cl_erlaubt ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
				<?php if ( ! empty( $cl_rel['hinweis'] ) ) : ?>
					<p class="mh-cl-note"><?php echo wp_kses( (string) $cl_rel['hinweis'], $cl_erlaubt ); ?></p>
				<?php endif; ?>
			</div>
		</details>
	<?php endforeach; ?>
</div>
