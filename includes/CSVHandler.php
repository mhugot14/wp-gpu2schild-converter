<?php
namespace untisSchildConverter;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
//In Anlehnung an https://www.a-coding-project.de/ratgeber/php/csv-import-in-php
require_once plugin_dir_path(__FILE__) . 'league-csv/autoload.php';
use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\Exception;

class CSVHandler{
	
  
  private $dateiName;
  private $tmpPfad;
  private $schuljahr;
  private $halbjahr;
  private $trennzeichen;
  private $schildRows;
  private $wpdb;
  private $tabLoeschFaecher;
  private $tabLoeschKlassen;
  private $tabSchildImport;
  private $pluginhelp;
  

  function __construct($file_tmp, $file_name, $schuljahr, $halbjahr, $trennzeichen){
	$this->dateiName = $file_name;
	$this->tmpPfad = $file_tmp;
	$this->schuljahr = $schuljahr;
	$this->halbjahr = $halbjahr;	
	$this->trennzeichen=$trennzeichen;
	global $wpdb;
	$this->wpdb = $wpdb;
	$this->tabLoeschFaecher = $this->wpdb->prefix.'usc_loesch_faecher';
	$this->tabLoeschKlassen = $this->wpdb->prefix.'usc_loesch_klassen';
	$this->tabSchildImport = $this->wpdb->prefix.'usc_schildimport';
	$this->pluginhelp = new Plugin_Helpers();
	
  }

  public function csvLeague(){
	  global $wpdb;
	  try {
		$csv = Reader::createFromPath($this->tmpPfad, 'r');
		
		switch($this->trennzeichen) {
			case 'Semikolon (;)':
				$this->trennzeichen=';';
				break;
			case 'Komma (,)':
				$this->trennzeichen=',';
				break;
			case 'Tabulator (	)':
				$this->trennzeichen='	';
				break;
			default:
				$this->trennzeichen=';';
		}
		
		$csv->setDelimiter($this->trennzeichen);
		 
		}		
		catch (Exception $e) {	
		 echo $e->getMessage(), PHP_EOL;
		}
		//let's convert the incoming data from iso-88959-15 to utf-8
		//$csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
		
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
		
		
		echo '<h3>Fächer ändern oder löschen</h3>';
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
		//Alle Einträge aus der Tabelle Löschfächer holen
		$loeschFaecher = $this->wpdb->get_results('SELECT * FROM '.$this->tabLoeschFaecher.';');
		?>
		<details style="background: #F6E3CE; border-left:5px solid #DF7401; padding:5px; width:50%;">
			<summary style="padding:2px;">Folgende Enträge wurden aufgrund der Fächertabelle
				gelöscht, geändert oder neu hinzugefügt(zum Aufklappen hier klicken)</summary>
		
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
		$loeschCounter = 0;
		$deleteCount=0;
		$updateCount=0;
		$insertCount=0;
		
		foreach ($loeschFaecher as $loeschfach){
			//hier wird eine einzelne Zeile der Tabelle mit den Fächern bearbeitet.
			$fachUntis = $loeschfach->fach_untis;
			$fachSchild = $loeschfach->fach_schild;
			$klasse = $loeschfach->klasse;
			
			//die Arrays werden zurückgesetzt
			unset($faecherSchild);
			unset($faecherUntis);
			
			//die Werte in den Zeilen Fach Untis und Fach Schild werden in Arrays geschrieben.
			$faecherUntis= $this->pluginhelp->strichSeparator($fachUntis);
			$faecherSchild=$this->pluginhelp->strichSeparator($fachSchild);
			 			  
											/* Ausgabe-Check, ob die Algorithus oben funktioniert
											 * 
											 */
//
//											echo "<ul>"; // Beginne eine ungeordnete Liste in HTML
//												foreach ($faecherSchild as $value) {
//													echo "<li>FachSchild: $value</li>"; // Füge jedes Array-Element in ein Listenelement (li) ein
//												}
//											echo "</ul>"; // Beende die ungeordnete Liste  
//
//											echo "<ul>"; // Beginne eine ungeordnete Liste in HTML
//												foreach ($faecherUntis as $value) {
//													echo "<li>FachUntis: $value</li>"; // Füge jedes Array-Element in ein Listenelement (li) ein
//												}
//											echo "</ul>"; // Beende die ungeordnete Liste  
			
			
		//Wenn das fach_schild leer ist in der Loeschtabelle, kann das Untis-Fach aus der CSV gelöscht werden. 
		if ($faecherSchild[0]==""){
				foreach($faecherUntis as $fachUntis){
				
					$loeschErgebnisSQL = $this->wpdb->prepare("SELECT * FROM "
						. "$this->tabSchildImport WHERE fach LIKE %s AND klasse LIKE %s", 
							$fachUntis, $klasse);
					$loeschErgebnis= $this->wpdb->get_results($loeschErgebnisSQL);
					echo "<tr style=\"background-color:#f6f7f7;\"><td colspan=\"8\"><b>Löschung von Fach: "
							.$fachUntis." in Klasse: ".$klasse."</b></td></tr>";
					$this->pluginhelp->resultsetToTable( $loeschErgebnis);
					
						$deleteSQL = $this->wpdb->prepare(
							"DELETE FROM $this->tabSchildImport WHERE fach LIKE %s AND klasse LIKE %s", 
							$fachUntis, $klasse);
						//print_r($deleteSQL);
						$deleteCountSingle = $this->wpdb->query($deleteSQL);
						//echo "Löschzeilen: ".$deleteCountSingle."<br>";
						$deleteCount=$deleteCount+$deleteCountSingle;
					}
			}
			
			else {
					/*Hier müssen verschiedene Fälle abgefangen werdne => Umbennennung, Neufachanlage mit und ohne Lösung)
					 * 
					 */
					 //Umbenennung => die zweiten Felder der Arrays gibt es nicht
					 if (isset($faecherUntis[1])==false && isset($faecherSchild[1])==false)
					 {
						$updateErgebnisSQL = $this->wpdb->prepare("SELECT * FROM "
						. "$this->tabSchildImport WHERE fach LIKE %s AND klasse LIKE %s", 
							$fachUntis, $klasse);
						$updateErgebnis= $this->wpdb->get_results($updateErgebnisSQL);
					echo "<tr style=\"background-color:#f6f7f7;\"><td colspan=\"8\"><b>Umbennung von UntisFach: "
							.$fachUntis." in SchildFach ".$fachSchild ." in Klasse: ".$klasse."</b></td></tr>";
						$this->pluginhelp->resultsetToTable( $updateErgebnis);
						
						$updateSQL = $this->wpdb->prepare(
								"UPDATE  $this->tabSchildImport "
					 . "SET fach = %s WHERE fach= %s AND klasse LIKE %s;",$faecherSchild[0],
								$faecherUntis[0],$klasse);
						//print_r($updateSQL);
						$updateCountSingle = $this->wpdb->query($updateSQL);
						//echo "<br>Update <b>Umbenennung</b>: ".$updateCountSingle."<br>";
						$updateCount =$updateCount+$updateCountSingle;
					 }
					 
					 //Änderung der Fächer / Neuananlage
					 
					 else{
						 //ein oder mehrere Fach bei faecherUntis und mehrere bei $faecherSchild
						 $platzhalter = implode(',', array_fill(0,count($faecherUntis),'%s'));
						 $abfrage = $this->wpdb->prepare(
								 "SELECT * FROM ".$this->tabSchildImport
								 ." WHERE fach IN ($platzhalter)"
								 ." AND klasse LIKE %s order by klasse;",
								 array_merge($faecherUntis,array($klasse)));
						// print_r($abfrage);
						//Das Resultset beinhaltet alle Einträge der DB mit den beiden UNTIS-Fächern.
						//Alle genannten Untis-Fächer einer Klasse sollen ersetzt werden durch
						//die Schild-Fächer.
						 $schildImportPart = $this->wpdb->get_results($abfrage);
						// echo "<p>schildimportPart Anzahl: ".count($schildImportPart)."</p>";
						 
						 $rowKlasse="";
						 foreach ($schildImportPart as $row){
							// echo "<p><b>rowKlasse: ".$rowKlasse." | row->klasse: ".$row->klasse."</b></p>";
							 if($rowKlasse == $row->klasse){
								 echo "<tr style=\"background-color:#f6f7f7;\"><td colspan=\"8\"><b>Löschung von UntisFach: "
							.$fachUntis." in Klasse: ".$klasse."</b></td></tr>";
								 $deleteErgebnis = $this->wpdb->get_results("SELECT * FROM $this->tabSchildImport WHERE id=$row->id;");
								 $this->pluginhelp->resultsetToTable( $deleteErgebnis);
								 $result=$this->wpdb->delete($this->tabSchildImport,array('id'=>$row->id));
								 if($result === false){echo "Fehler beim Löschen der Datenbankzeile mit der ID ".$row['id'];}
								else{
									//echo "<p>Zeile mit ID ". $row->id . " | " .$row->klasse."| " .$row->fach. " gelöscht. </p>";  
									$deleteCount++;
								}
																			
								}
							else{
							 $rowKlasse = $row->klasse;
								foreach($faecherSchild as $fachSchild){
									$neueZeile = array(
										'schuljahr' => $row->schuljahr,
										'halbjahr' => $row->halbjahr,
										'klasse' => $rowKlasse,
										'fach' => $fachSchild,
										'lehrer' => $row->lehrer,
										'giltfuerHalbjahr' => $row->giltfuerHalbjahr
										);
									
									$result = $this->wpdb->insert($this->tabSchildImport,$neueZeile);
									
									if($result === false){
										echo "Fehler beim Einfügen der Datenbankzeile!";}
									else{
										echo "<tr style=\"background-color:#f6f7f7;\"><td colspan=\"8\"><b>Hinzufügen von Schildfach "
										.$fachSchild." für UntisFach: ".$fachUntis." in Klasse: ".$klasse."</b></td></tr>";
										echo "<tr><td></td><td>". $neueZeile['schuljahr'] . "</td><td> " .$neueZeile['halbjahr']."</td><td> " .$neueZeile['klasse']. "</td><td>".$neueZeile['fach']."</td><td>".$neueZeile['lehrer']."</td><td>".$neueZeile['giltfuerHalbjahr']."</td></tr>"; 
									//	$deleteErgebnis = $this->wpdb->get_results("SELECT * FROM $this->tabSchildImport WHERE id=$row->id;");

										}
										$insertCount++;
								 }
								  echo "<tr style=\"background-color:#f6f7f7;\"><td colspan=\"8\"><b>Löschung von UntisFach: "
							.$fachUntis." in Klasse: ".$klasse."</b></td></tr>";
								 $deleteErgebnis = $this->wpdb->get_results("SELECT * FROM $this->tabSchildImport WHERE id=$row->id;");
								 $this->pluginhelp->resultsetToTable( $deleteErgebnis);
								 $result=$this->wpdb->delete($this->tabSchildImport,array('id'=>$row->id));
									if($result === false){
										echo "Fehler beim Löschen der Datenbankzeile mit der ID ".$row->id;}
									else{
										$deleteCount++;
										//echo "Zeile mit ID ". $row->id . " | " .$row->klasse." | " .$row->fach. " gelöscht"; 
										}
						 }}

					 }
					 
			}
		}
		echo "</tbody></table>";
		echo "</details>";
		echo "Insgesamt wurden <b>".$deleteCount."</b> Einträge gelöscht!<br/>";
		echo "Insgesamt wurden <b>".$updateCount."</b> Einträge aktualisiert!<br/>";
		echo "Insgesamt wurden <b>".$insertCount."</b> Einträge angelegt!";
		
	}
	
	public function klassen_loeschen(){
		
		$loeschKlassen = $this->wpdb->get_results('SELECT * FROM '.$this->tabLoeschKlassen.';');
		?>
		<details style="background: #F6E3CE; border-left:5px solid #DF7401; padding:5px; width:50%;">
			<summary style="padding:2px;">Folgende Einträge wurden aufgrund der LoeschKlassen gelöscht oder geändert (zum Aufklappen hier klicken)</summary>
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
		$deleteCount=0;
		$updateCount=0;
		foreach ($loeschKlassen as $loeschKlasse){
			$loeschErgebnis = $this->wpdb->get_results(
						'SELECT * '
						. 'FROM '.$this->tabSchildImport
						. ' WHERE klasse LIKE \''.$loeschKlasse->klasse_untis.'\';');
			
			echo "<tr style=\"background-color:#f6f7f7;\"><td colspan=\"7\">Löschung/Änderung von Klasse <b>"
			.$loeschKlasse->klasse_untis."</b> mit der Schild_Klasse <b>".$loeschKlasse->klasse_schild. "</b></td></tr>";
			foreach($loeschErgebnis as $loeschErgebnisRow){
				echo "<tr>";
				echo "<td>" .$loeschErgebnisRow->id  . "</td>";
				echo "<td>" .$loeschErgebnisRow->schuljahr . "</td>";
				echo "<td>" .$loeschErgebnisRow->halbjahr . "</td>";
				echo "<td>" .$loeschErgebnisRow->klasse . "</td>";
				echo "<td>" .$loeschErgebnisRow->fach . "</td>";
				echo "<td>" .$loeschErgebnisRow->lehrer . "</td>";
				echo "<td>" .$loeschErgebnisRow->giltfuerHalbjahr . "</td>";
				echo "</tr>";
			}
			if ($loeschKlasse->klasse_schild==""){
				$deleteSQL = $this->wpdb->prepare(
					"DELETE FROM $this->tabSchildImport WHERE klasse LIKE %s", 
					 $loeschKlasse->klasse_untis);
		//	print_r($deleteSQL);
			$deleteCountSingle = $this->wpdb->query($deleteSQL);
			
			$deleteCount=$deleteCount+$deleteCountSingle;
			}
			else {
				$updateSQL = $this->wpdb->prepare(
						"UPDATE  $this->tabSchildImport "
						. "SET klasse = %s WHERE klasse= %s",$loeschKlasse->klasse_schild,
						$loeschKlasse->klasse_untis);
				$updateCountSingle = $this->wpdb->query($updateSQL);
				$updateCount =$updateCount+$updateCountSingle;
			} 
		}
		echo "</tbody></table>";
		echo "</details>";
	//	echo "Insgesamt wurden <b>".$loeschCounter."</b> Einträge gelöscht<br/>";
		echo "Insgesamt wurden <b>".$deleteCount."</b> Einträge gelöscht<br/>";
		echo "Insgesamt wurden <b>".$updateCount."</b> Einträge aktualisiert<br/>";
	}
			
	public function db_tabellen_ausgabe($resultSet){
		echo '<p>Anzahl Datensätze in der Datenbanktabelle <b>'.count($resultSet).'</b>.</p>';
		if (count($resultSet)){
			$this->csv_erzeugen( $resultSet );
			?>
			<a href='schildimport.csv'>CSV-Datei herunterladen</a> <br/>
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
	
	public function csv_erzeugen($resultset){
		$columns=array('schuljahr','halbjahr','klasse', 'fach','lehrer');	
		$csv = Writer::createFromString('');
		//let's convert the incoming data from iso-88959-15 to utf-8
		$csv->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
		$filename="schildimport.csv";
		 $csv->insertOne($columns); // Spaltenüberschriften hinzufügen
		$i=0;
		foreach ($resultset as $row) {

				$rowArray=array($row->schuljahr,$row->halbjahr,$row->klasse,$row->fach,$row->lehrer);
			$csv->insertOne($rowArray); // Datenzeile hinzufügen
			$i++;
		}
		echo "Die nachstehende Datei hat ".$i." Datensätze:";

		// CSV in eine Datei schreiben
		$file = fopen($filename, 'w');
		fwrite($file, $csv->getContent());
		fclose($file);

	}

}
?>