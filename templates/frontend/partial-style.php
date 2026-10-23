<?php
/**
 * Gemeinsame Stile des Frontends. Aufbau und Farben wie die Ansichten von MH Form Workflows
 * (Nachschreiben, Mikrofortbildungen), damit sich alles wie aus einem Guss anfühlt.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<style>
	.mh-usc { --u-blue: #004f9f; --u-blue-dark: #003c7a; --u-blue-soft: #e8f0fa; --u-navy: #0f2e52;
		--u-red: #c62828; --u-red-soft: #fdecec; --u-green: #1e6b30; --u-green-soft: #e7f3ea; --u-amber: #8a5a00; --u-amber-soft: #fcf3d9;
		--u-border: #d5d9de; --u-muted: #5f6873; --u-bg: #f6f7f9;
		max-width: 1100px; margin: 0 auto; color: #1d2327; font-family: inherit; font-size: 15px; line-height: 1.5; }
	.mh-usc *, .mh-usc *::before, .mh-usc *::after { box-sizing: border-box; }
	.mh-usc input, .mh-usc select, .mh-usc textarea, .mh-usc button { font-family: inherit; text-transform: none; letter-spacing: normal; }
	.mh-usc h2, .mh-usc h3, .mh-usc h4 { font-family: inherit; letter-spacing: normal; text-transform: none; }

	/* Kopf */
	.mh-usc-head { display: flex; gap: 14px; align-items: center; margin: 0 0 14px; flex-wrap: wrap; }
	.mh-usc-head-icon { display: inline-flex; align-items: center; justify-content: center; width: 46px; height: 46px; border-radius: 10px;
		background: var(--u-blue); color: #fff; font-size: 22px; font-weight: 700; flex: 0 0 46px; }
	.mh-usc-head-text { flex: 1; min-width: 220px; }
	.mh-usc-head-title { margin: 0; font-size: 1.45em; color: var(--u-navy); line-height: 1.2; }
	.mh-usc-head-sub { margin: 2px 0 0; color: var(--u-muted); font-size: 0.9em; }
	.mh-usc-chips { display: flex; gap: 6px; flex-wrap: wrap; }
	.mh-usc-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 14px; font-size: 0.8em; font-weight: 600;
		background: #eef0f2; color: #3c434a; text-decoration: none !important; white-space: nowrap; }
	.mh-usc-chip.is-ok { background: var(--u-green-soft); color: var(--u-green) !important; }
	.mh-usc-chip.is-warn { background: var(--u-amber-soft); color: var(--u-amber) !important; }
	.mh-usc-chip.is-error { background: var(--u-red-soft); color: var(--u-red) !important; }

	/* Reiter */
	.mh-usc-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--u-border); margin: 0 0 22px; flex-wrap: wrap; }
	.mh-usc-tabs a { display: inline-flex; align-items: center; gap: 7px; padding: 10px 16px; text-decoration: none !important; color: var(--u-muted) !important;
		font-weight: 600; border: 2px solid transparent; border-bottom: none; border-radius: 6px 6px 0 0; margin-bottom: -2px; }
	.mh-usc-tabs a.is-active { color: var(--u-blue) !important; background: #fff; border-color: var(--u-border); border-bottom: 2px solid #fff; }
	.mh-usc-tabs a:hover { color: var(--u-blue) !important; }
	.mh-usc-tabs .mh-usc-count { font-size: 0.75em; padding: 1px 7px; border-radius: 9px; background: #e3e7ec; color: #3c434a; }
	.mh-usc-tabs .mh-usc-count.is-red { background: var(--u-red); color: #fff; }

	/* Meldungen */
	.mh-usc-flash { background: #f0f9f2; border-left: 4px solid var(--u-green); padding: 10px 14px; margin: 0 0 18px; border-radius: 0 6px 6px 0; }
	.mh-usc-flash.is-error { background: var(--u-red-soft); border-left-color: var(--u-red); }
	.mh-usc-flash ul { margin: 6px 0 0 18px; padding: 0; }
	.mh-usc-notice { border-left: 4px solid #dba617; background: #fcf7e6; padding: 10px 14px; border-radius: 0 6px 6px 0; margin: 0 0 16px; font-size: 0.93em; }
	.mh-usc-notice.is-info { border-left-color: var(--u-blue); background: var(--u-blue-soft); }
	.mh-usc-notice.is-error { border-left-color: var(--u-red); background: var(--u-red-soft); }
	.mh-usc-intro { color: var(--u-muted); margin: 0 0 18px; }
	.mh-usc-muted { color: var(--u-muted); }
	.mh-usc-small { font-size: 0.85em; }

	/* Karten */
	.mh-usc-card { background: #fff; border: 1px solid var(--u-border); border-radius: 10px; margin: 0 0 18px; }
	.mh-usc-card-head { margin: 0; padding: 13px 20px; border-bottom: 1px solid var(--u-border); background: var(--u-bg); border-radius: 10px 10px 0 0;
		display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
	.mh-usc-card-head h3 { margin: 0; font-size: 1.05em; flex: 1; color: var(--u-navy); }
	.mh-usc-card-body { padding: 16px 20px; }
	.mh-usc-card-body > :last-child { margin-bottom: 0; }
	details.mh-usc-card > summary { list-style: none; cursor: pointer; }
	details.mh-usc-card > summary::-webkit-details-marker { display: none; }
	details.mh-usc-card > summary h3::after { content: " ▸"; color: var(--u-muted); }
	details.mh-usc-card[open] > summary h3::after { content: " ▾"; }
	details.mh-usc-card:not([open]) > .mh-usc-card-head { border-bottom: 0; border-radius: 10px; }

	/* Kennzahlen */
	.mh-usc-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin: 0 0 18px; }
	.mh-usc-stat { background: #fff; border: 1px solid var(--u-border); border-radius: 8px; padding: 10px 14px; }
	.mh-usc-stat b { display: block; font-size: 1.45em; color: var(--u-navy); line-height: 1.2; }
	.mh-usc-stat span { font-size: 0.82em; color: var(--u-muted); }

	/* Badges */
	.mh-usc-badge { display: inline-block; font-size: 0.75em; font-weight: 700; padding: 2px 9px; border-radius: 10px; background: #e3e7ec; color: #333; white-space: nowrap; }
	.mh-usc-badge.is-blue { background: var(--u-blue-soft); color: var(--u-blue); }
	.mh-usc-badge.is-green { background: var(--u-green-soft); color: var(--u-green); }
	.mh-usc-badge.is-amber { background: var(--u-amber-soft); color: var(--u-amber); }
	.mh-usc-badge.is-red { background: var(--u-red-soft); color: var(--u-red); }
	.mh-usc-badge.is-grey { background: #eef0f2; color: #8a929b; }

	/* Tabellen */
	.mh-usc-scroll { overflow-x: auto; }
	.mh-usc-table { width: 100%; border-collapse: collapse; font-size: 0.92em; margin: 0; }
	.mh-usc-table th, .mh-usc-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eceef1; vertical-align: top; }
	.mh-usc-table th { font-size: 0.76em; color: var(--u-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; }
	.mh-usc-table tr.is-off td { color: #9aa1a8; background: #fafbfc; }
	.mh-usc-table tr.is-off code { opacity: .6; }
	.mh-usc-table tr.is-highlight td { background: #fffbea; }
	.mh-usc-table td.is-actions { text-align: right; white-space: nowrap; }
	.mh-usc-table td.is-num, .mh-usc-table th.is-num { text-align: right; white-space: nowrap; }
	.mh-usc-table code, .mh-usc code { background: #f0f1f3; padding: 1px 6px; border-radius: 4px; font-size: 0.95em; color: #1d2327; }
	.mh-usc-table form { display: inline; margin: 0; }
	.mh-usc-arrow { color: var(--u-muted); margin: 0 4px; }
	.mh-usc-klassen { font-size: 0.82em; color: var(--u-muted); max-width: 320px; }
	@media (max-width: 760px) {
		.mh-usc-table.is-stack thead { display: none; }
		.mh-usc-table.is-stack tr { display: block; border-bottom: 1px solid #eceef1; padding: 6px 0; }
		.mh-usc-table.is-stack td { display: block; border: 0; padding: 3px 6px; }
		.mh-usc-table.is-stack td.is-actions, .mh-usc-table.is-stack td.is-num { text-align: left; }
		.mh-usc-table.is-stack td[data-l]::before { content: attr(data-l) ": "; font-size: 0.8em; color: var(--u-muted); }
	}

	/* Formulare */
	.mh-usc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px 16px; align-items: start; }
	.mh-usc-grid .is-wide { grid-column: span 2; }
	.mh-usc-grid .is-full { grid-column: 1 / -1; }
	@media (max-width: 760px) { .mh-usc-grid { grid-template-columns: 1fr; } .mh-usc-grid .is-wide { grid-column: auto; } }
	.mh-usc-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
	.mh-usc-field > label, .mh-usc-field > .mh-usc-label { font-weight: 600; font-size: 0.85em; color: #3c434a; margin: 0; }
	.mh-usc-field small { color: var(--u-muted); font-size: 0.8em; line-height: 1.4; }
	.mh-usc-field input[type=text], .mh-usc-field input[type=search], .mh-usc-field input[type=file], .mh-usc-field select, .mh-usc-field textarea, .mh-usc-search {
		width: 100%; min-height: 40px; padding: 7px 10px; border: 1px solid #9aa3ad; border-radius: 6px; background: #fff; font-size: 15px; margin: 0; color: inherit; }
	.mh-usc-field textarea { min-height: 160px; font-family: Consolas, Menlo, monospace; font-size: 13.5px; resize: vertical; }
	.mh-usc-field input:focus, .mh-usc-field select:focus, .mh-usc-field textarea:focus, .mh-usc-search:focus {
		outline: none; border-color: var(--u-blue); box-shadow: 0 0 0 2px var(--u-blue-soft); }
	.mh-usc-field.is-invalid input, .mh-usc-field.is-invalid textarea { border-color: var(--u-red); background: #fff6f6; }
	.mh-usc-field.is-invalid > label { color: var(--u-red); }
	.mh-usc-feld-fehler { color: var(--u-red); font-size: 0.82em; font-weight: 600; }
	.mh-usc-preview { font-size: 0.8em; color: var(--u-muted); min-height: 1.2em; }
	.mh-usc-preview b { color: var(--u-blue); }
	.mh-usc-preview.is-none b { color: var(--u-red); }
	.mh-usc-check { display: inline-flex; gap: 8px; align-items: center; font-size: 0.92em; cursor: pointer; margin: 0; }
	.mh-usc-check input { width: 17px; height: 17px; margin: 0; }
	.mh-usc-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 14px; }
	.mh-usc-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 0 0 12px; }
	.mh-usc-toolbar .mh-usc-search { max-width: 280px; min-height: 36px; padding: 5px 10px; font-size: 14px; }

	/* Knöpfe */
	.mh-usc-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--u-border); background: #fff; color: #1d2327 !important;
		text-decoration: none !important; border-radius: 6px; padding: 8px 15px; font-size: 0.93em; cursor: pointer; line-height: 1.3; margin: 0; }
	.mh-usc-btn:hover { background: #f2f4f6; }
	.mh-usc-btn-primary { background: var(--u-blue); border-color: var(--u-blue); color: #fff !important; font-weight: 600; }
	.mh-usc-btn-primary:hover { background: var(--u-blue-dark); }
	.mh-usc-btn-big { padding: 12px 22px; font-size: 1.02em; }
	.mh-usc-btn-small { padding: 3px 9px; font-size: 0.8em; }
	.mh-usc-btn-icon { padding: 3px 8px; font-size: 0.85em; min-width: 30px; justify-content: center; }
	.mh-usc-btn-danger { color: var(--u-red) !important; }
	.mh-usc-btn:disabled, .mh-usc-btn[aria-disabled=true] { opacity: .5; cursor: not-allowed; }

	/* Schalter */
	.mh-usc-switch { position: relative; display: inline-block; width: 38px; height: 22px; border: 0; padding: 0; background: #c3c8ce; border-radius: 11px;
		cursor: pointer; vertical-align: middle; transition: background .15s; }
	.mh-usc-switch::before { content: ""; position: absolute; width: 16px; height: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%;
		transition: transform .15s; box-shadow: 0 1px 2px rgba(0,0,0,.25); }
	.mh-usc-switch.is-on { background: var(--u-green); }
	.mh-usc-switch.is-on::before { transform: translateX(16px); }
	.mh-usc-switch:focus-visible { outline: 2px solid var(--u-blue); outline-offset: 2px; }

	/* Pipeline-Schritte */
	.mh-usc-steps { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin: 0 0 18px; }
	@media (max-width: 900px) { .mh-usc-steps { grid-template-columns: repeat(3, 1fr); } }
	@media (max-width: 520px) { .mh-usc-steps { grid-template-columns: repeat(2, 1fr); } }
	.mh-usc-step { display: block; background: #fff; border: 1px solid var(--u-border); border-radius: 8px; padding: 10px 12px; text-decoration: none !important;
		color: inherit !important; position: relative; }
	.mh-usc-step:hover, .mh-usc-step.is-active { border-color: var(--u-blue); box-shadow: 0 0 0 2px var(--u-blue-soft); }
	.mh-usc-step-nr { display: inline-flex; width: 22px; height: 22px; border-radius: 50%; background: var(--u-blue); color: #fff; font-size: 0.75em;
		font-weight: 700; align-items: center; justify-content: center; margin-right: 6px; }
	.mh-usc-step-name { font-weight: 700; font-size: 0.9em; }
	.mh-usc-step-zahl { display: block; margin-top: 6px; font-size: 0.85em; color: var(--u-muted); }
	.mh-usc-step-zahl b { color: #1d2327; }
	.mh-usc-step-diff { display: block; font-size: 0.78em; font-weight: 600; }
	.mh-usc-step-diff.is-minus { color: var(--u-red); }
	.mh-usc-step-diff.is-null { color: #9aa1a8; font-weight: 400; }

	/* Protokoll */
	.mh-usc-log { list-style: none; margin: 0; padding: 0; }
	.mh-usc-log > li { border: 1px solid var(--u-border); border-radius: 8px; margin: 0 0 8px; background: #fff; }
	.mh-usc-log details > summary { display: flex; gap: 10px; align-items: baseline; padding: 9px 12px; cursor: pointer; list-style: none; }
	.mh-usc-log details > summary::-webkit-details-marker { display: none; }
	.mh-usc-log details > summary::before { content: "▸"; color: var(--u-muted); font-size: 0.85em; }
	.mh-usc-log details[open] > summary::before { content: "▾"; }
	.mh-usc-log-text { flex: 1; }
	.mh-usc-log-body { padding: 0 12px 12px 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
	@media (max-width: 760px) { .mh-usc-log-body { grid-template-columns: 1fr; padding-left: 12px; } }
	.mh-usc-log-body h4 { margin: 4px 0 4px; font-size: 0.78em; text-transform: uppercase; letter-spacing: .3px; color: var(--u-muted); }
	.mh-usc-log-body .mh-usc-table { font-size: 0.85em; }
	.mh-usc-filter { display: flex; gap: 6px; flex-wrap: wrap; margin: 0 0 12px; }
	.mh-usc-filter button { border: 1px solid var(--u-border); background: #fff; color: #3c434a; border-radius: 16px; padding: 5px 12px; font-size: 0.85em; cursor: pointer; margin: 0; }
	.mh-usc-filter button.is-active { background: var(--u-blue); border-color: var(--u-blue); color: #fff; }

	/* Konflikte */
	.mh-usc-wahl { display: flex; gap: 8px; flex-wrap: wrap; }
	.mh-usc-wahl label { display: inline-flex; gap: 6px; align-items: center; border: 1px solid var(--u-border); border-radius: 6px; padding: 5px 10px;
		cursor: pointer; font-size: 0.92em; margin: 0; background: #fff; }
	.mh-usc-wahl label:hover { border-color: #9fb6d3; }
	.mh-usc-wahl input { margin: 0; }
	.mh-usc-wahl label.is-checked { border-color: var(--u-blue); background: var(--u-blue-soft); font-weight: 600; }
	.mh-usc-wahl small { color: var(--u-muted); font-weight: 400; }
	tr.is-offen td:first-child { box-shadow: inset 4px 0 0 var(--u-red); }

	/* Fachregel-Assistent: Art wählen */
	.mh-usc-arten { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 0 0 16px; }
	@media (max-width: 860px) { .mh-usc-arten { grid-template-columns: 1fr 1fr; } }
	@media (max-width: 480px) { .mh-usc-arten { grid-template-columns: 1fr; } }
	.mh-usc-art { position: relative; display: block; border: 2px solid var(--u-border); border-radius: 8px; padding: 11px 12px; cursor: pointer;
		background: #fff; margin: 0; transition: border-color .15s, box-shadow .15s; }
	.mh-usc-art:hover { border-color: #9fb6d3; }
	.mh-usc-art input { position: absolute; opacity: 0; pointer-events: none; }
	.mh-usc-art:has(input:checked) { border-color: var(--u-blue); box-shadow: 0 0 0 3px var(--u-blue-soft); }
	.mh-usc-art:has(input:checked)::after { content: "✓"; position: absolute; top: 8px; right: 10px; color: var(--u-blue); font-weight: 700; }
	.mh-usc-art:focus-within { outline: 2px solid var(--u-blue); outline-offset: 2px; }
	.mh-usc-art-titel { display: block; font-weight: 700; margin-bottom: 2px; }
	.mh-usc-art-text { display: block; font-size: 0.82em; color: var(--u-muted); line-height: 1.35; }
	.mh-usc-art-bsp { display: block; margin-top: 6px; font-size: 0.8em; }
	.mh-usc-schritt-titel { font-weight: 700; font-size: 0.92em; color: var(--u-navy); margin: 0 0 8px; }
	.mh-usc-fr.is-js:not([data-art]) .mh-usc-fr-felder { display: none; }
	.mh-usc-fr[data-art="loeschen"] .mh-usc-nur-schild { display: none; }
	.mh-usc-fr.is-js:not([data-art="umbenennen"]) .mh-usc-nur-ziel { display: none; }
	.mh-usc-fr[data-art="umbenennen"] .mh-usc-vorschlaege { display: none; } /* Umbenennen nur für eine einzelne Klasse bzw. ein Fach */

	/* Bausteine (Chips) */
	.mh-usc-chipfeld { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; min-height: 42px; padding: 5px 8px; border: 1px solid #9aa3ad;
		border-radius: 6px; background: #fff; cursor: text; }
	.mh-usc-chipfeld:focus-within { border-color: var(--u-blue); box-shadow: 0 0 0 2px var(--u-blue-soft); }
	.mh-usc-field.is-invalid .mh-usc-chipfeld { border-color: var(--u-red); background: #fff6f6; }
	.mh-usc-chipfeld input[type=text] { flex: 1; min-width: 140px; border: 0 !important; outline: none; box-shadow: none !important; padding: 4px 2px !important;
		min-height: 0 !important; height: auto; font-size: 15px; background: transparent; margin: 0; }
	.mh-usc-chipfeld.is-voll input[type=text] { display: none; }
	.mh-usc-baustein { display: inline-flex; align-items: center; gap: 5px; background: var(--u-blue-soft); color: var(--u-blue-dark); border: 1px solid #c5d7ee;
		border-radius: 14px; padding: 2px 4px 2px 10px; font-size: 0.88em; font-weight: 600; }
	.mh-usc-baustein small { font-weight: 400; color: var(--u-muted); }
	.mh-usc-baustein.is-fremd { background: var(--u-amber-soft); border-color: #ecd59a; color: var(--u-amber); }
	.mh-usc-baustein button { border: 0; background: none; color: inherit; cursor: pointer; font-size: 1.1em; line-height: 1; padding: 0 4px; margin: 0; }
	.mh-usc-baustein button:hover { color: var(--u-red); background: none; }
	/* Eigene Auswahlliste der Bausteinfelder (sucht in Kurzform und Bezeichnung) */
	.mh-usc-chipfeld { position: relative; }
	.mh-usc-auswahl { display: none; position: absolute; left: -1px; right: -1px; top: calc(100% + 4px); z-index: 20; margin: 0; padding: 4px 0;
		list-style: none; background: #fff; border: 1px solid #9aa3ad; border-radius: 6px; box-shadow: 0 6px 18px rgba(0,0,0,.12);
		max-height: 320px; overflow-y: auto; }
	.mh-usc-auswahl.is-offen { display: block; }
	.mh-usc-auswahl li { display: flex; gap: 10px; align-items: baseline; padding: 7px 12px; margin: 0; cursor: pointer; font-size: 0.93em; }
	.mh-usc-auswahl li b { min-width: 64px; color: var(--u-navy); }
	.mh-usc-auswahl li span { color: var(--u-muted); }
	.mh-usc-auswahl li.is-aktiv, .mh-usc-auswahl li:hover { background: var(--u-blue-soft); }
	.mh-usc-auswahl mark { background: #fde68a; color: inherit; padding: 0; border-radius: 2px; }
	.mh-usc-vorschlaege { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
	.mh-usc-vorschlaege button { border: 1px dashed #7d8b9a; background: #fff; color: var(--u-blue); border-radius: 14px; padding: 3px 10px; font-size: 0.8em;
		cursor: pointer; margin: 0; }
	.mh-usc-vorschlaege button:hover { background: var(--u-blue-soft); }
	.mh-usc-radios { display: flex; gap: 14px; flex-wrap: wrap; margin: 0 0 6px; }

	/* Klartext der Regel */
	.mh-usc-satz { margin: 16px 0 0; border-left: 4px solid var(--u-blue); background: var(--u-blue-soft); padding: 10px 14px; border-radius: 0 6px 6px 0; }
	.mh-usc-satz strong { display: block; margin-bottom: 2px; }
	.mh-usc-satz.is-warn { border-left-color: #dba617; background: #fcf7e6; }
	.mh-usc-satz:empty { display: none; }

	/* Regelprüfung */
	.mh-usc-befund { border-left: 4px solid var(--u-blue); background: #fafbfc; border-radius: 0 6px 6px 0; padding: 10px 14px; margin: 0 0 10px; }
	.mh-usc-befund p { margin: 0; }
	.mh-usc-befund.is-red { border-left-color: var(--u-red); background: #fff8f8; }
	.mh-usc-befund.is-amber { border-left-color: #dba617; background: #fffbf0; }
	.mh-usc-befund.is-grey { border-left-color: #c3c8ce; }
	.mh-usc-befund .mh-usc-actions form { display: inline; margin: 0; }
	.mh-usc-befund-titel { margin: 16px 0 4px; font-size: 0.95em; color: var(--u-navy); }
	.mh-usc-table tr:target td { background: #fff3c4; transition: background .3s; }
	.mh-usc-table td.is-nr { color: var(--u-muted); font-size: 0.85em; white-space: nowrap; }

	.mh-usc-save-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid var(--u-border); padding: 12px 0; margin-top: 12px;
		display: flex; gap: 12px; align-items: center; flex-wrap: wrap; z-index: 5; }
	.mh-usc-dirty { color: var(--u-amber); font-size: 0.88em; font-weight: 600; display: none; }
	.mh-usc-dirty.is-on { display: inline; }
	.mh-usc-hidden { display: none !important; }
</style>
