<?php
namespace untisSchildConverter;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
//In Anlehnung an https://www.a-coding-project.de/ratgeber/php/csv-import-in-php
require_once plugin_dir_path(__FILE__) . 'league-csv/autoload.php';
require_once WP_PLUGIN_DIR . '/untisSchildConverter/vendor/autoload.php';
//use League\Csv\Reader;
use League\Csv\Writer;
//use League\Csv\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SchildImportRepository{

	private $wpdb;
	private $tabSchildImport; //Tabellenname mit den Schildimporten

	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabSchildImport = $this->wpdb->prefix.'usc_schildimport';
	 }
	 
	 function tabelleAusgeben(){
		$resultSet = $this->wpdb->get_results('SELECT * FROM '.$this->tabSchildImport.';'); 
		 
		 echo '<p>Anzahl Datensätze in der Datenbanktabelle <b>'.count($resultSet).'</b>.</p>';
		if (count($resultSet)){
			$this->csv_erzeugen( $resultSet );
			$this->xls_erzeugen( $resultSet );
			?>
			<a href='schildimport.csv'>CSV-Datei herunterladen</a> oder 
			<a href='schildimport.xlsx'>XLSX-Datei herunterladen</a><br/>
		<h3>Alle Schild-Import-Daten auf einen Blick</h3>	
		<table class="wp-list-table sortable fixed striped table-view-list pages">
			<thead>
				<tr>
					<th>id</th><!-- comment -->
					<th>Schuljahr</th>
					<th>Import für Halbjahr</th>
					<th>Klasse</th>
					<th>Fach</th>
					<th>Lehrer</th>
					<th>gilt für Halbjahr</th>
				</tr>	
			</thead>
			<tbody>
			<?php
			
			foreach ( $resultSet as $row ) {
				echo "<tr>";
				echo "<td>" .$row->id  . "</td>";
				echo "<td>" .$row->schuljahr . "</td>";
				echo "<td>" .$row->halbjahr . "</td>";
				echo "<td>" .$row->klasse . "</td>";
				echo "<td>" .$row->fach . "</td>";
				echo "<td>" .$row->lehrer . "</td>";
				echo "<td>" .$row->giltfuerHalbjahr . "</td>";
				echo "</tr>";
				}
			echo "</tbody></table>";
		}
		else{
			echo "Es sind keine Datensätze vorhanden.";
		}
	 }
	 
	 public function xls_erzeugen($resultset){
		 $spreadsheet = new Spreadsheet();
		 $worksheet = $spreadsheet->getActiveSheet();
		 $row=1;
		 foreach ($resultset as $rowData) {
			$col = 1;
				foreach ($rowData as $cellData) {
					$worksheet->setCellValueByColumnAndRow($col, $row, $cellData);
					$col++;
				}	
			 $row++;
			}
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('schildimport.xlsx');

	 }
	 
	 public function csv_erzeugen($resultset){
		$columns=array('Kursbezeichnung','klasse','Jahr','Halbjahr','Jahrgang', 
			'Fach', 'Kursart', 'Wochenstunden', 'Wochenstunden Lehrer','lehrer');	
		$csv = Writer::createFromString('');
		//let's convert the incoming data from iso-88959-15 to utf-8
		$csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
		$filename="schildimport.csv";
		 $csv->insertOne($columns); // Spaltenüberschriften hinzufügen
		$i=0;
		foreach ($resultset as $row) {

				$rowArray=array('',$row->klasse, $row->schuljahr,$row->halbjahr,'',$row->fach,'','','',$row->lehrer);
			$csv->insertOne($rowArray); // Datenzeile hinzufügen
			$i++;
		}
		echo "Die nachstehende Datei hat ".$i." Datensätze:";

		// CSV in eine Datei schreiben
		$file = fopen($filename, 'w');
		fwrite($file, $csv->getContent());
		fclose($file);

	}
	//Die Funktion gibt eine Liste mit Fächern aus, die nicht zu den Schild-Fächern gehören.
	function schildnrwFachabgleich(){
		$tabschildfaecher=$this->wpdb->prefix.'usc_schildfaecher';
		$resultSet = $this->wpdb->get_results(
				'SELECT * FROM '.$this->tabSchildImport.' WHERE fach NOT IN '
				. '(SELECT interne_kurzform from '.$tabschildfaecher. ');' ); 
		return $resultSet;
		
		
	}

}


