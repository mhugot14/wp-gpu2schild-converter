<?php
namespace untisSchildConverter;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once __DIR__ . '/../vendor/autoload.php';

class LoeschKlasseHandler{

	private $loeschKlasse = array();
	private $tabellenname;
	private $wpdb;
	
	
	public function __construct(){
		global $wpdb;
		$this->wpdb=$wpdb;
		$this->tabellenname = $this->wpdb->prefix.'usc_loesch_klassen';
	}
	 
	
	public function loeschKlasseToDb($loeschKlasse, $operation){
		
		$timestamp = time();
		$date = date("Y-m-d H:i:s", $timestamp);

		if ($operation == "new"){
			try{
				$this->wpdb->query("TRUNCATE TABLE ".$this->tabellenname.';');
			} catch (Exception $ex) {
				echo "Die Tabelle konnte nicht geleert werden: ".$ex;
			}
		}
		try{
			foreach($loeschKlasse as $row){
				$this->wpdb->insert(
				$this->tabellenname,
					
					array(
						'klasse_untis' => $row['klasse_untis'],
						'klasse_schild' => $row['klasse_schild'],
						'bemerkung' => $row['bemerkung'],
						'importdatum' => $date
					
					)
				);
			
			}
		} catch (Exception $ex) {
			echo "Die Faecher konnten nicht gespeichert werden: ".$ex;
		}
		
	}
	
	public function loeschKlasseAnzeigen(){
		
		$resultSet=$this->wpdb->get_results('SELECT * FROM '.$this->tabellenname.' ORDER BY klasse_untis;');
		
			echo '<p>Anzahl Datensätze in der Datenbanktabelle: <b>'.count($resultSet).'</b>.</p>';
		if (count($resultSet)){
		 ?>
		<table class="wp-list-table sortable fixed striped">
			<thead>
				<tr>
					<th>id</th><!-- comment -->
					<th>Klasse Untis</th>
					<th>Klasse Schild</th>
					<th>Bemerkung</th>
					<th>Importdatum</th>
				</tr>	
			</thead>
			<tbody>
			<?php
			
			foreach ( $resultSet as $row ) {
				echo "<tr>";
				echo "<td>" .$row->id  . "</td>";
				echo "<td>" .$row->klasse_untis . "</td>";
				echo "<td>" .$row->klasse_schild . "</td>";
				echo "<td>" .$row->bemerkung . "</td>";
				echo "<td>" .$row->importdatum . "</td>";
				echo "</tr>";
			}
			echo "</tbody></table>";
			
			echo "<h3>Und jetzt zum schnellen kopieren bei Neuanlage</h3> <p>";
				foreach ( $resultSet as $row ) {
					echo $row->klasse_untis.",".$row->klasse_schild.",".$row->bemerkung."<br/>";
				}
			echo "</p>";

		}
		else{
			echo "Es sind keine Datensätze vorhanden.";
		}
			
	
	}
	
	
}