<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


/*
Plugin Name: Untis2Schild Converter
Plugin URI: www.lebk-muenster.de
Description: Das Plugin konvertiert die GPU002 aus UNTIS in eine importfähige Datei für Schild.
 * Dabei begleitet dich das Plugin, um die Datei entsprechend zu konfiguieren (Unterricht löschen, aufteilen, hinzufügen).
Version: v0.9.2 
Author: Michael Hugot
Author URI: Berufsschulwissen.de
License: GPLv2
*/

namespace untisSchildConverter;

// Sicherheit: Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit; 
}

// Composer-Autoload einmalig einbinden
$autoload = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    error_log('Untis2Schild Converter: vendor/autoload.php nicht gefunden – bitte "composer install" im Plugin-Ordner ausführen.');
}

//Plugin Aktivierung
define('MH_uSC_FILE',__FILE__);
//Includes
require_once __DIR__ . '/includes/Plugin_Helpers.php';
require_once __DIR__ . '/includes/Settings.php';

//Die Funktion wird aufgerufen bei der Aktivierung des Plugins im Backend
register_activation_hook(
		MH_uSC_FILE, 
		['untisSchildConverter\Plugin_Helpers' ,'activate']
		);


new Settings();

