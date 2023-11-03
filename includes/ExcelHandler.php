<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace untisSchildConverter;
require(__DIR__ . '/../vendor/autoload.php');


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
 use PhpOffice\PhpSpreadsheet\IOFactory;
/**
 * Description of ExcelHandler
 *
 * @author micha
 */
class ExcelHandler {

	//Testfunktion zur Überprüfung ob das Einbinden funktioniert
	public function spreadsheetCheck(){
		$spreadsheet = new Spreadsheet();
		$activeWorksheet = $spreadsheet->getActiveSheet();
		$activeWorksheet->setCellValue('A1', 'Hello World !');

		$writer = new Xlsx($spreadsheet);
		$writer->save('hello world.xlsx');
		
}
	public function spreadsheetReader($dateipfad,$anzahlSpalten){
		$reader = IOFactory::createReader('Xlsx');
		$spreadsheet = $reader->load($dateipfad);
    
		// Die Daten aus der Excel-Datei auslesen
		$sheet = $spreadsheet->getActiveSheet();
		$data = [];
     	 // Um die erste Zeile als Headerzeile zu identifizieren

		$first_row=true;
		foreach ($sheet->getRowIterator() as $row) {
			$rowData = [];
			
			$cellIterator = $row->getCellIterator();
			
			$colCount=0;
			foreach ($cellIterator as $cell) {
				$rowData[] = $cell->getValue();
				   $colCount++;

				if ($colCount >= $anzahlSpalten) {
					break; // Nur die ersten drei Spalten einlesen
				}
			}
			//Erste Zeile nicht importieren in das Array, da Überschrift
			if ($first_row==true){
				$first_row=false;
				continue;
				
			}
           $data[] = $rowData;
		}
		//debug - gibt das Array aus
		//echo $this->array_to_table($data);		
		return $data;		
	}
	
	 function array_to_table($data) {
		$html = '<table class="wp-list-table widefat fixed striped table-view-list pages">';
		// Headerzeile erstellen
		$html .= '<tr>';
		foreach (array_keys($data[0]) as $key) {
			$html .= '<th>' . htmlspecialchars($key) . '</th>';
		}
		$html .= '</tr>';

		// Datenzeilen erstellen
		foreach ($data as $row) {
			$html .= '<tr>';
			foreach ($row as $value) {
				$html .= '<td>' . htmlspecialchars($value) . '</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</table>';
		return $html;
	}


}
