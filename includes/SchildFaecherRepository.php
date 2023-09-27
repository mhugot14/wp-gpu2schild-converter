<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace untisSchildConverter;

/**
 * Description of SchildFaecherRepository
 * Fächer, die in Schild vorhanden sind. Durch einen Excel-Export kann man 
 * sich diese Fächer aus Schild herausholen und hierhin importieren. Ziel ist 
 * es, diese mit dem UNTIS-Datenstamm abzugleichen, um zu schauen, ob Fächer 
 * aus Untis in Schild importiert werden sollen, die in Schild gar nicht vorhanden
 * sind und somit einen Import-Fehler auslösen.
 * @author micha
 */
class SchildFaecherRepository {
	
	private $wpdb;
	private $tabSchildFaecher; //Tabellenname mit den Schildimporten

	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabSchildFaecher = $this->wpdb->prefix.'usc_schildfaecher';
	 }
	 
	 function tabelleAusgeben(){
		 
	 }
	 
	 function tabelleEinlesen(){
		 
		 
	 }
	 }
