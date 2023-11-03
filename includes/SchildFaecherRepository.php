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
	private $tabellenname; //Tabellenname mit den Schildimporten

	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabellenname = $this->wpdb->prefix.'usc_schildfaecher';
	 }
	 
	 function tabelleAusgeben(){
		$resultSet = $this->wpdb->get_results('SELECT * FROM '.$this->tabellenname.';'); 
		 if (count($resultSet)){
			echo '<h3>Schild-Fächer</h3><p>Anzahl Datensätze in der Datenbanktabelle <b>'.count($resultSet).'</b>.</p>';
			?>	
		<p>Nachstehende Fächer sind in Schild angelegt.
		<table class="wp-list-table widefat">
			<thead>
				<tr>
					<th>id</th><!-- comment -->
					<th>Interne Kurzform</th>
					<th>ASD Kürzel</th>
					<th>Bezeichnung</th>
				</tr>	
			</thead>
			<tbody>
			<?php
			
			foreach ( $resultSet as $row ) {
				echo "<tr>";
				echo "<td>" .$row->id  . "</td>";
				echo "<td>" .$row->interne_kurzform . "</td>";
				echo "<td>" .$row->asd_kuerzel . "</td>";
				echo "<td>" .$row->bezeichnung . "</td>";
				echo "</tr>";
				}
			echo "</tbody></table>";
		 }
		else{
			echo "Es sind keine Datensätze vorhanden.";
		}

	 }
	 
	 function tabelleEinlesen($data){
		 
		 try{
					$this->wpdb->query("TRUNCATE TABLE ".$this->tabellenname.';');
				} catch (Exception $ex) {
					echo "Die Tabelle konnte nicht geleert werden: ".$ex;
				}
		
		try{
			foreach($data as $row){
				$this->wpdb->insert(
				$this->tabellenname,
					
					array(
						'interne_kurzform' => $row['0'],
						'asd_kuerzel' => $row['1'],
						'bezeichnung' => $row['2'],									
					)
				);
			}
		} catch (Exception $ex) {
			echo "Die Schild-Faecher konnten nicht gespeichert werden: ".$ex;
		}
	 }
	}
