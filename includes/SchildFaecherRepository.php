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
chdir(__DIR__);
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;


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
	 
	 function xlsxEinlesen(){
		  if(isset($_POST["submit"])) {
            $target_dir = plugin_dir_path(__FILE__); // Verzeichnis deines Plugins
            $target_file = $target_dir . basename($_FILES["xlsx_file"]["name"]);
            $uploadOk = 1;
            $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

            // Überprüfe, ob die hochgeladene Datei eine XLSX-Datei ist
            if($imageFileType != "xlsx") {
                echo "Nur XLSX-Dateien sind erlaubt.";
                $uploadOk = 0;
            }

            // Wenn alles in Ordnung ist, lade die Datei hoch und verarbeite sie mit PhpSpreadsheet
            if ($uploadOk == 1) {
                if (move_uploaded_file($_FILES["xlsx_file"]["tmp_name"], $target_file)) {
                    // Datei wurde erfolgreich hochgeladen, verarbeite sie mit PhpSpreadsheet
                    self::processXLSXFile($target_file);
                } else {
                    echo "Es gab ein Problem beim Hochladen der Datei.";
		 
		 
					}
			}
		  }
	 }
	 
	 function dateiuploadAnzeigen(){
		
		?>
		<p>Lade hier deine XLSX-Datei aus Schild-NRW mit allen Fächern hoch.</p>
						<form method="post" enctype="multipart/form-data">
							<input type="file" name="xlsx_file" id="xlsx_file" class="file"><br/><br/>
							<br/><br/>
							<input type="submit" name="submit" value="Datei hochladen" class="button button-primary">
							</form><br/>
					<?php
	}
	

	 
}
