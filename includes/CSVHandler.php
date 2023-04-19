<?php
namespace untisSchildConverter;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
//In Anlehnung an https://www.a-coding-project.de/ratgeber/php/csv-import-in-php
require_once plugin_dir_path(__FILE__) . 'league-csv\autoload.php';
use League\Csv\Reader;
use League\Csv\Exception;

class CSVHandler{
	
  
  private $dateiName;
  private $tmpPfad;
  private $schuljahr;
  private $halbjahr;
  private $schildRows;


  function __construct($file_tmp, $file_name, $schuljahr, $halbjahr){
	$this->dateiName = $file_name;
	$this->tmpPfad = $file_tmp;
	$this->schuljahr = $schuljahr;
	$this->halbjahr = $halbjahr;	
	 
  }

  public function csvLeague(){
	  global $wpdb;
	  try {
		$csv = Reader::createFromPath($this->tmpPfad, 'r');
		 $csv->setDelimiter(',');
		}		
		catch (Exception $e) {	
		 echo $e->getMessage(), PHP_EOL;
		}
		//let's convert the incoming data from iso-88959-15 to utf-8
		$csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
		
		$data = $csv->getRecords(); // CSV-Daten als Iterator abrufen
		
		?>
		<p>Die GPU hat insgesamt <?php echo $csv->count();?> Datensätze.</p>
		<?php
		$columns_to_delete = [0,1,2,3,7,8,9,10,12,13,14,15,16,17,18,19,20,21,22,
			23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,
			46,47,48,49,50,51,52,53,54,55];
		foreach($csv as $row){
			foreach ($columns_to_delete as $column_number) {
				unset($row[$column_number]);
				
			}
			$this->schildRows[]=$row;
		}
		
	echo "Anzahl Spalten: ". count(current($this->schildRows))."<br/>";
		//echo $this->schildRows->count();
				// Hinzufügen der Spaltenüberschriften
	$keys = array("Klasse", "Lehrer", "Fach", "giltfuerHalbjahr");
	foreach ($this->schildRows as &$row){
		$row=array_combine($keys, $row);
		//echo $row['Klasse'].' Keys hinzugefügt<br/>';
 	}
			$tabellenname=$wpdb->prefix.'usc_schildimport';
			
			
		//Speichern der Daten in der Datenbank
		echo '<h3>Daten einlesen</h3>...';
			$this->csvToDB( $this->schildRows);
			
		echo '<h3>Dubletten entfernen</h3>';
		$this->dubletten_entfernen();
		
		echo '<h3>Klassen ändern oder löschen</h3>';
		$this->klassen_loeschen();
		
		
		echo '<h3>Fächer löschen</h3>';
		$this->faecher_loeschen();
		
		echo '<h3>Daten ausgeben</h3>';
		$resultSet=$wpdb->get_results('SELECT * FROM '.$tabellenname.';');
		$this->db_tabellen_ausgabe($resultSet);
		
		
		
	
  }
	  
  /**
	*Die Funktion csvToDB schreibt eine Schild-CSV-Datei in die DAtenbank
   * 
   * @param type $csvSchildDaten
   */
  	public function csvToDB($csvSchildDaten){
		global $wpdb;
		
		$tabellenname=$wpdb->prefix.'usc_schildimport';
		try{
			$wpdb->query("TRUNCATE TABLE $tabellenname");
		} catch (Exception $ex) {
			echo "Die Tabelle konnte nicht geleert werden: ".$ex;
		}
		
		try{
			foreach($csvSchildDaten as $row){
			//Halbjahr ändern
				$giltFuerHalbjahr=$row['giltfuerHalbjahr'];
				$importFuerHalbjahr=$this->halbjahr;
				
				
				if ($giltFuerHalbjahr==="1. HJ"){
					$giltFuerHalbjahr="1";
				}
				elseif ($giltFuerHalbjahr=== "2. HJ"){
					$giltFuerHalbjahr="2";
				}
				else {
					$giltFuerHalbjahr = "1+2";
				}
			/**Import für Halbjahr nur setzen, wenn es für das passende Halbjahr gültig ist
				*Im ersten Halbjahr wird das zweite Halbjahr nicht mit importiert, im zweiten Halbjahr
				*aber das erste, daher muss nur das zweite Halbjahr aus dem ersten Import entfernt werden.
			 */
			if($this->halbjahr==='1'){
				if($giltFuerHalbjahr==='2'){
					$importFuerHalbjahr='';
				}
			}
			
			$rueck=$wpdb->insert(
				$tabellenname,
					
					array(
						'schuljahr' => $this->schuljahr,
						'halbjahr' => $importFuerHalbjahr,
						'klasse' => $row['Klasse'],
						'fach' => $row['Fach'],
						'lehrer' => $row['Lehrer'],
						'giltfuerHalbjahr'=>$giltFuerHalbjahr
					)
				);
				}
			}
			
		catch (Exception $ex) {
			
			echo "Die Schild-Daten konnten nicht in die DB importiert werden: ".$ex;
			
			
		}
		
		if ($rueck == false || $rueck <=0){
			throw new \Exception(
					sprintf(
							__('Einfügen in DB hat nicht geklappt. Folgende'
									. 'Fehler wurden geworfen %s','easy_rating'),
							$wpdb->last_error
							)
					
					);
		}
			
		
		return $rueck;
			
		}
 /**
	*Die Funktion dubletten_entfernen() entfernt jeweils den zweiten Datensatz
    * aller doppelten Datensätze basierend auf klasse, fach und lehrer gleich, 
    * NICHT halbjahr 
   */
	public function dubletten_entfernen(){
		global $wpdb;
		$tabellenname=$wpdb->prefix.'usc_schildimport';
		$resultSet=$wpdb->get_results('SELECT * FROM '.$tabellenname.';');
		$rowsVorLoeschen = count($resultSet);
		
		try{
			$wpdb->query("DELETE FROM ".$tabellenname." "
					. "WHERE id NOT IN ( SELECT id FROM "
					. "( SELECT id, ROW_NUMBER() OVER (PARTITION BY klasse, fach, lehrer, giltfuerHalbjahr ORDER BY id) AS rn "
					. "FROM ". $tabellenname.") AS subquery WHERE rn = 1 ); ");
		} catch (Exception $ex) {
			echo "Die Tabelle konnte nicht geleert werden: ".$ex;
		}
		$resultSet=$wpdb->get_results('SELECT * FROM '.$tabellenname.';');
		$rowsNachLoeschen = count($resultSet);
		$geloeschteDatensaetze = $rowsVorLoeschen-$rowsNachLoeschen;
		echo '<p>Datensätze vor dem Löschen:<b> ' .$rowsVorLoeschen.'</b></br>'
				. 'Datensätze nach dem Löschen:<b> '.$rowsNachLoeschen.'</b></br>'
				. 'Gelöschte Dubletten:<b> <span style="color:red;"> '.$geloeschteDatensaetze.'</span></b></p>';
		
		
	}
	
	public function faecher_loeschen(){
		
		echo "<p>Hier passiert noch nichts</p>";
	}
	
	public function klassen_loeschen(){
		
		echo "<p>Hier passiert noch nichts</p>";
	}
	
		
	public function db_tabellen_ausgabe($resultSet){
		echo '<p>Anzahl Datensätze in der Datenbanktabelle <b>'.count($resultSet).'</b>.</p>';
		if (count($resultSet)){
		 ?>
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

}
?>
