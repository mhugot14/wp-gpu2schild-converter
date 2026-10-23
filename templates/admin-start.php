<?php
/**
 * View: Startseite im Backend (Menüpunkt „Untis2Schild“).
 *
 * Vom Admin_Controller::render_start() bereitgestellt:
 * @var string   $shortcode
 * @var bool     $frontend_aktiv
 * @var object[] $seiten          Seiten mit dem Shortcode (ID, post_title, post_status, post_type)
 * @var array    $counts
 * @var array    $checks          [ status, label, text, hint? ]
 * @var string   $create_page_url
 * @var string   $fehler
 * @var array    $legacy_links    Titel => URL der Seiten der Vorversion
 *
 * Bei neuen Shortcodes oder geänderten Schritten diese Seite mitpflegen.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$status_stil = [
	'ok'    => [ '✓', '#e7f5e9', '#1b7f3a', 'in Ordnung' ],
	'warn'  => [ '!', '#fcf3d9', '#8a5a00', 'Hinweis' ],
	'error' => [ '✕', '#fbe9e7', '#b32d2e', 'Fehler' ],
	'info'  => [ 'i', '#e5f0fa', '#0a4b78', 'Info' ],
];
$anzahl = array_count_values( array_column( $checks, 'status' ) );
$status_name = [ 'publish' => 'veröffentlicht', 'private' => 'privat', 'draft' => 'Entwurf', 'pending' => 'ausstehend', 'future' => 'geplant' ];
?>
<style>
	.usc-help { max-width: 1100px; margin: 20px 0; }
	.usc-help *, .usc-help *::before { box-sizing: border-box; }

	.usc-head { background: #004f9f; color: #fff; padding: 34px 40px; border-radius: 8px 8px 0 0; position: relative; }
	.usc-head h1 { color: #fff !important; margin: 0 0 8px; font-size: 28px; font-weight: 700; padding: 0; }
	.usc-head p { margin: 0; font-size: 15px; opacity: .92; max-width: 760px; line-height: 1.5; }
	.usc-version { position: absolute; top: 18px; right: 20px; background: rgba(255,255,255,.15); padding: 3px 10px; border-radius: 4px; font-size: 12px; }
	.usc-toc { background: #fff; border: 1px solid #dcdcde; border-top: 0; padding: 12px 40px; display: flex; flex-wrap: wrap; gap: 6px 22px; font-size: 13px; }
	.usc-toc a { text-decoration: none; }

	.usc-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1px; background: #dcdcde; border: 1px solid #dcdcde; border-top: 0; border-radius: 0 0 8px 8px; overflow: hidden; }
	.usc-step { background: #fff; padding: 24px 22px; }
	.usc-step-nr { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%; background: #004f9f; color: #fff; font-weight: 700; margin-bottom: 10px; }
	.usc-step h3 { margin: 0 0 8px; font-size: 15px; }
	.usc-step p { margin: 0; color: #50575e; line-height: 1.5; }
	.usc-step .usc-where { display: block; margin-top: 8px; font-size: 12px; color: #004f9f; font-weight: 600; }

	.usc-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 24px 28px; margin-top: 24px; scroll-margin-top: 50px; }
	.usc-card > h2 { margin: 0 0 6px; font-size: 19px; }
	.usc-lead { color: #50575e; margin: 0 0 18px; line-height: 1.6; }
	.usc-card h3 { margin: 22px 0 8px; font-size: 14px; }
	.usc-card p, .usc-card li { line-height: 1.6; }

	.usc-check-sum { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 12px; }
	.usc-check-sum span { padding: 3px 11px; border-radius: 12px; font-size: 12px; font-weight: 600; }
	.usc-checks { width: 100%; border-collapse: collapse; }
	.usc-checks td { padding: 9px 8px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
	.usc-checks tr:last-child td { border-bottom: 0; }
	.usc-checks .c-icon { width: 34px; }
	.usc-checks .c-icon span { display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 50%; font-weight: 700; font-size: 12px; }
	.usc-checks .c-label { width: 190px; font-weight: 600; }
	.usc-checks .c-hint { color: #6f6f6f; font-size: 12.5px; margin-top: 2px; }

	.usc-pipeline { list-style: none; margin: 0; padding: 0; counter-reset: usc; display: grid; gap: 10px; }
	.usc-pipeline li { position: relative; padding: 12px 14px 12px 54px; border: 1px solid #e3e5e8; border-left: 4px solid #004f9f; border-radius: 4px; background: #fafbfc; }
	.usc-pipeline li::before { counter-increment: usc; content: counter(usc); position: absolute; left: 14px; top: 12px; width: 26px; height: 26px; border-radius: 50%;
		background: #e8f0fa; color: #004f9f; font-weight: 700; text-align: center; line-height: 26px; font-size: 13px; }
	.usc-pipeline strong { display: block; margin-bottom: 2px; }
	.usc-pipeline span { color: #50575e; }
	.usc-new { display: inline-block; margin-left: 6px; font-size: 11px; font-weight: 700; color: #1b7f3a; background: #e7f5e9; padding: 1px 7px; border-radius: 9px; vertical-align: 1px; }

	.usc-table { width: 100%; border-collapse: collapse; margin: 6px 0 0; }
	.usc-table th { text-align: left; background: #f6f7f7; padding: 10px 12px; border-bottom: 2px solid #e3e5e8; font-size: 13px; }
	.usc-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
	.usc-table code, .usc-code { background: #f0f0f1; padding: 2px 7px; border-radius: 4px; color: #1d2327; white-space: nowrap; }

	.usc-shortcode { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 14px; margin: 4px 0 6px; }
	.usc-shortcode code { font-size: 16px; font-weight: 700; color: #b32d2e; background: #fff; padding: 6px 12px; border: 1px solid #dcdcde; }
	.usc-badge { display: inline-block; padding: 2px 9px; border-radius: 10px; font-size: 11px; font-weight: 600; white-space: nowrap; }
	.usc-badge.is-ok { background: #e7f5e9; color: #1b5e20; }
	.usc-badge.is-warn { background: #fcf3d9; color: #8a5a00; }
	.usc-badge.is-restricted { background: #fbe9e7; color: #8a1c0f; }
	.usc-howto { margin: 6px 0 0 18px; padding: 0; }
	.usc-howto li { margin-bottom: 6px; }

	.usc-note { background: #fff8e5; border-left: 4px solid #e5a912; padding: 10px 15px; margin: 14px 0 0; border-radius: 0 4px 4px 0; }
	.usc-note.is-info { background: #f0f6fc; border-left-color: #004f9f; }
	.usc-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
	@media (max-width: 900px) {
		.usc-cols { grid-template-columns: 1fr; }
		.usc-head, .usc-toc { padding-left: 20px; padding-right: 20px; }
		.usc-checks td { display: block; border: 0; padding: 2px 8px; }
		.usc-checks tr { display: block; border-bottom: 1px solid #f0f0f1; padding: 8px 0; }
		.usc-checks .c-label { width: auto; }
	}
</style>

<div class="wrap">
<div class="usc-help">

	<?php if ( 'seite' === $fehler ) : ?>
		<div class="notice notice-error"><p>Die Seite konnte nicht angelegt werden. Bitte manuell anlegen (siehe „Einbinden“).</p></div>
	<?php endif; ?>

	<div class="usc-head">
		<span class="usc-version">Version <?= esc_html( MH_USC_VERSION ) ?></span>
		<h1>Untis2Schild Converter</h1>
		<p>Überträgt die Unterrichtsverteilung aus Untis nach SchILD-NRW: Aus der GPU002 entsteht eine
			Importdatei, mit der alle Schülerinnen und Schüler einer Klasse ihre Fächer und Fachlehrkräfte
			erhalten. Kurse und Gruppen außerhalb des Klassenverbands werden weiterhin in Schild zugeordnet.</p>
	</div>
	<nav class="usc-toc" aria-label="Inhalt">
		<a href="#usc-status">Status</a>
		<a href="#usc-einbinden">Einbinden</a>
		<a href="#usc-schritte">Was das Plugin bereinigt</a>
		<a href="#usc-regeln">Regeln schreiben</a>
		<a href="#usc-export">Export und Schild-Import</a>
		<a href="#usc-datenschutz">Datenschutz</a>
	</nav>
	<div class="usc-steps">
		<div class="usc-step">
			<span class="usc-step-nr">1</span>
			<h3>Aus Untis exportieren</h3>
			<p>Die Unterrichtsdatei der aktuellen Periode exportieren (GPU002.TXT). Kodierung und Trennzeichen erkennt das Plugin selbst.</p>
			<span class="usc-where">Untis: Datei → Import/Export → Untis → Unterricht</span>
		</div>
		<div class="usc-step">
			<span class="usc-step-nr">2</span>
			<h3>Hochladen</h3>
			<p>Schuljahr und Halbjahr wählen und die Datei hochladen. Das Plugin zeigt sofort, was es gelesen hat.</p>
			<span class="usc-where">Frontend: Reiter „Import“</span>
		</div>
		<div class="usc-step">
			<span class="usc-step-nr">3</span>
			<h3>Regeln und Konflikte prüfen</h3>
			<p>Klassen und Fächer anpassen, bei Fächern mit mehreren Lehrkräften auswählen. Jede Änderung wirkt sofort – ohne neuen Upload.</p>
			<span class="usc-where">Frontend: Klassen · Fächer · Konflikte</span>
		</div>
		<div class="usc-step">
			<span class="usc-step-nr">4</span>
			<h3>In Schild importieren</h3>
			<p>Protokoll durchsehen, Excel-Datei herunterladen und mit der gewohnten Importvorlage in Schild einlesen.</p>
			<span class="usc-where">Frontend: Reiter „Ergebnis“</span>
		</div>
	</div>

	<!-- STATUS -->
	<section class="usc-card" id="usc-status">
		<h2>Status</h2>
		<p class="usc-lead">Was eingerichtet ist und was noch fehlt.</p>
		<div class="usc-check-sum">
			<?php foreach ( [ 'error', 'warn', 'ok' ] as $s ) : if ( empty( $anzahl[ $s ] ) ) continue; ?>
				<span style="background:<?= esc_attr( $status_stil[ $s ][1] ) ?>;color:<?= esc_attr( $status_stil[ $s ][2] ) ?>">
					<?= (int) $anzahl[ $s ] ?> × <?= esc_html( $status_stil[ $s ][3] ) ?>
				</span>
			<?php endforeach; ?>
		</div>
		<table class="usc-checks">
			<?php foreach ( $checks as $c ) : $st = $status_stil[ $c['status'] ]; ?>
				<tr>
					<td class="c-icon"><span style="background:<?= esc_attr( $st[1] ) ?>;color:<?= esc_attr( $st[2] ) ?>" aria-label="<?= esc_attr( $st[3] ) ?>"><?= esc_html( $st[0] ) ?></span></td>
					<td class="c-label"><?= esc_html( $c['label'] ) ?></td>
					<td>
						<?= esc_html( $c['text'] ) ?>
						<?php if ( ! empty( $c['hint'] ) ) : ?><div class="c-hint"><?= esc_html( $c['hint'] ) ?></div><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	</section>

	<!-- EINBINDEN -->
	<section class="usc-card" id="usc-einbinden">
		<h2>Einbinden</h2>
		<p class="usc-lead">Gearbeitet wird im Frontend, damit auch Kolleginnen und Kollegen aus der Verwaltung den Converter nutzen
			können, ohne Zugang zum WordPress-Backend zu brauchen.</p>

		<table class="usc-table">
			<thead><tr><th style="width:210px">Shortcode</th><th>Zeigt</th><th style="width:170px">Zugriff</th><th style="width:130px">Status</th></tr></thead>
			<tbody>
				<tr>
					<td><code>[<?= esc_html( $shortcode ) ?>]</code></td>
					<td>Den kompletten Converter mit den Reitern Import, Klassen, Fächer, Konflikte, Schild-Fächer und Ergebnis.</td>
					<td><span class="usc-badge is-restricted">Admins + Freigegebene</span></td>
					<td>
						<?php if ( $frontend_aktiv ) : ?>
							<span class="usc-badge is-ok">verfügbar</span>
						<?php else : ?>
							<span class="usc-badge is-warn">in Arbeit</span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="usc-cols" style="margin-top:18px">
			<div>
				<h3>So bindest du den Converter ein</h3>
				<ol class="usc-howto">
					<li>Eine neue Seite anlegen, z. B. „Untis2Schild“.</li>
					<li>Im Block-Editor den Block <strong>Shortcode</strong> einfügen.</li>
					<li>Den Shortcode hineinkopieren:
						<div class="usc-shortcode">
							<code id="usc-sc">[<?= esc_html( $shortcode ) ?>]</code>
							<button type="button" class="button" data-copy="#usc-sc">Kopieren</button>
						</div>
					</li>
					<li>Seite veröffentlichen. Wer keine Berechtigung hat, sieht dort nur einen Hinweis – Daten sind für niemanden sonst sichtbar.</li>
				</ol>
				<p>
					<a class="button button-primary" href="<?= esc_url( $create_page_url ) ?>">Seite mit Shortcode anlegen</a>
					<span style="color:#646970;font-size:12.5px;margin-left:6px">legt einen Entwurf an und öffnet ihn im Editor</span>
				</p>
			</div>
			<div>
				<h3>Eingebunden auf</h3>
				<?php if ( [] === $seiten ) : ?>
					<p style="color:#646970">Noch auf keiner Seite.</p>
				<?php else : ?>
					<ul style="margin:0">
						<?php foreach ( $seiten as $s ) : ?>
							<li>
								<a href="<?= esc_url( (string) get_edit_post_link( (int) $s->ID ) ) ?>"><?= esc_html( '' !== $s->post_title ? $s->post_title : '(ohne Titel)' ) ?></a>
								<span style="color:#646970">· <?= esc_html( $status_name[ $s->post_status ] ?? $s->post_status ) ?></span>
								<?php if ( 'publish' === $s->post_status || 'private' === $s->post_status ) : ?>
									· <a href="<?= esc_url( (string) get_permalink( (int) $s->ID ) ) ?>">ansehen</a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<h3>Wer darf den Converter benutzen?</h3>
				<p>Administratorinnen und Administratoren immer. Weitere Personen, z. B. aus der Verwaltung, schaltest du unter
					<a href="<?= esc_url( admin_url( 'admin.php?page=' . \Mh\UntisSchild\Controller\Admin_Controller::FREIGABEN_SLUG ) ) ?>">Untis2Schild → Freigaben</a> frei.
					Alle anderen sehen auf der Seite nur einen Hinweis.</p>
				<p class="usc-note is-info" style="margin-top:0">Geplant: eine Kachel im Dashboard <code class="usc-code">[mh_dashboard]</code> von
					<em>MH Form Workflows</em> für alle Freigegebenen.</p>
				<?php if ( ! $frontend_aktiv ) : ?>
					<p class="usc-note">Frontend, Freigabe und Dashboard-Kachel kommen mit den nächsten Ausbaustufen. Bis dahin
						funktionieren die bisherigen Seiten:
						<?php $links = []; foreach ( $legacy_links as $titel => $url ) { $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $titel ) . '</a>'; } echo implode( ' · ', $links ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- PIPELINE -->
	<section class="usc-card" id="usc-schritte">
		<h2>Was das Plugin bereinigt</h2>
		<p class="usc-lead">Die hochgeladene GPU002 bleibt unverändert gespeichert. Das Ergebnis wird bei jedem Aufruf aus der Datei
			und den aktuellen Regeln neu berechnet – immer in diesen sechs Schritten. Zu jedem Schritt gibt es ein Protokoll, das zeigt,
			welche Zeile durch welche Regel wie verändert wurde.</p>
		<ol class="usc-pipeline">
			<li><strong>Halbjahr</strong>
				<span>Im 1. Halbjahr fällt Unterricht weg, der in Untis nur für das 2. Halbjahr gilt (Gruppe „2. HJ“). Im 2. Halbjahr bleibt
					alles erhalten, auch Fächer, die im 1. Halbjahr endeten.</span></li>
			<li><strong>Klassenregeln</strong>
				<span>Klassen, die es in Schild nicht gibt (z. B. Vertretungsreserven), werden entfernt; abweichende Namen werden umbenannt.</span></li>
			<li><strong>Fachregeln</strong>
				<span>Untis-Fächer werden entfernt, umbenannt, zusammengefasst oder aufgeteilt (z. B. Lernfelder in Schild-Fächer). Beim
					Aufteilen behält jede beteiligte Lehrkraft ihre Zeile.</span></li>
			<li><strong>Lehrkräfte</strong> <span class="usc-new">neu</span>
				<span>Schild kennt je Klasse und Fach nur eine Lehrkraft. Unterrichten mehrere, wird eine gemerkte Auswahl verwendet, sonst die
					Lehrkraft mit den meisten Wochenstunden. Bei Gleichstand muss jemand auswählen – bis dahin ist der Export gesperrt.
					Im 2. Halbjahr zählt bei einem Lehrkraftwechsel die aktuelle Lehrkraft.</span></li>
			<li><strong>Dubletten</strong>
				<span>Gleiche Klasse, gleiches Fach, gleiche Lehrkraft wird zu einer Zeile. Das passiert bewusst erst jetzt, weil Umbenennungen neue
					Dubletten erzeugen können. Stunden desselben Unterrichts (gleiche Unterrichtsnummer) zählen nur einmal.</span></li>
			<li><strong>Prüfung</strong>
				<span>Fächer, die in der Schild-Fächerliste fehlen, und auffällige Lehrkraft-Kürzel werden gemeldet – sie würden sonst beim
					Import in Schild scheitern.</span></li>
		</ol>
	</section>

	<!-- REGELN -->
	<section class="usc-card" id="usc-regeln">
		<h2>Regeln schreiben</h2>
		<p class="usc-lead">Regeln bleiben über Importe und Schuljahre hinweg gespeichert und lassen sich einzeln aktivieren und deaktivieren.
			Passen mehrere Regeln auf dieselbe Zeile, gilt die oberste; das Protokoll weist darauf hin.</p>
		<div class="usc-cols">
			<div>
				<h3>Klassen und Muster</h3>
				<table class="usc-table">
					<tr><td><code>AK21A</code></td><td>genau diese Klasse</td></tr>
					<tr><td><code>AK*</code> oder <code>AK%</code></td><td>alle Klassen, die mit AK beginnen</td></tr>
					<tr><td><code>AK21A, EC*</code></td><td>mehrere Angaben mit Komma</td></tr>
					<tr><td>leer oder <code>*</code></td><td>alle Klassen (nur bei Fachregeln)</td></tr>
				</table>
				<p style="color:#646970;font-size:12.5px">Groß- und Kleinschreibung spielt keine Rolle.</p>
			</div>
			<div>
				<h3>Fachregeln</h3>
				<table class="usc-table">
					<thead><tr><th>Untis</th><th>Schild</th><th>Wirkung</th></tr></thead>
					<tr><td><code>VERT</code></td><td><em>leer</em></td><td>entfernen</td></tr>
					<tr><td><code>ER</code></td><td><code>Reli</code></td><td>umbenennen</td></tr>
					<tr><td><code>LF1|LF2</code></td><td><code>LF</code></td><td>zusammenfassen</td></tr>
					<tr><td><code>LFU1|LFU2</code></td><td><code>WSP|KUP|KPA</code></td><td>aufteilen</td></tr>
				</table>
				<p style="color:#646970;font-size:12.5px">Mehrere Fächer mit <code class="usc-code">|</code> trennen. Beim Aufteilen sind die
					Wochenstunden je Schild-Fach unbekannt und bleiben in der Importdatei leer.</p>
			</div>
		</div>
	</section>

	<!-- EXPORT -->
	<section class="usc-card" id="usc-export">
		<h2>Export und Schild-Import</h2>
		<p class="usc-lead">Die Excel-Datei hat dieselben zehn Spalten wie in der Vorversion, damit die gespeicherte Importvorlage in Schild
			weiter passt. Neu ist, dass die Wochenstunden aus Untis gefüllt werden.</p>
		<table class="usc-table">
			<thead><tr><th>Spalte</th><th>Inhalt</th></tr></thead>
			<tr><td>Kursbezeichnung, Jahrgang, Kursart</td><td>leer</td></tr>
			<tr><td>klasse · Fach · lehrer</td><td>aus der bereinigten GPU002</td></tr>
			<tr><td>Jahr · Halbjahr</td><td>beim Upload gewählt</td></tr>
			<tr><td>Wochenstunden · Wochenstunden Lehrer</td><td>aus Untis (Wochenstunden Klasse bzw. Lehrkraft); leer bei aufgeteilten Fächern</td></tr>
		</table>
		<p class="usc-note is-info">Die Schild-Fächerliste exportierst du in Schild unter <em>Unterrichtsfächer → Excel-Export</em> und lädst sie
			im Reiter „Schild-Fächer“ hoch. Nach Änderungen an den Fächern in Schild bitte neu importieren.</p>
	</section>

	<!-- DATENSCHUTZ -->
	<section class="usc-card" id="usc-datenschutz">
		<h2>Datenschutz</h2>
		<p class="usc-lead">Die GPU002 enthält Lehrkraft-Kürzel und damit personenbezogene Daten der Beschäftigten.</p>
		<ul style="margin:0 0 0 18px">
			<li>Nur Administratorinnen, Administratoren und ausdrücklich freigeschaltete Personen sehen die Daten.</li>
			<li>Die Importdatei wird beim Herunterladen erzeugt und nicht auf dem Server abgelegt.</li>
			<li>Hochgeladene Dateien werden nach dem Einlesen nicht aufbewahrt, gespeichert werden nur die benötigten Spalten.</li>
		</ul>
	</section>

</div>
</div>
<script>
	document.querySelectorAll( '.usc-help [data-copy]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var text = document.querySelector( btn.dataset.copy ).textContent;
			navigator.clipboard.writeText( text ).then( function () {
				btn.textContent = 'Kopiert ✓';
				setTimeout( function () { btn.textContent = 'Kopieren'; }, 1800 );
			} );
		} );
	} );
</script>
