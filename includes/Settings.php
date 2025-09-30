<?php
namespace untisSchildConverter;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
include_once 'CSVHandler.php';
include_once 'LoeschFaecherHandler.php';
include_once 'LoeschKlasseHandler.php';
include_once 'SchildImportRepository.php';
include_once 'ExcelHandler.php';
include_once 'SchildFaecherRepository.php';
include_once 'Plugin_Helpers.php';

class Settings{
	private $myLoeschFaecherHandler;
	private $myLoeschKlasseHandler;
	private $mySchildImportRepository;
	private $myPluginHelpers;
	
	public function __construct(){
		add_action('admin_menu', [$this, 'create_menu']);
		do_action( 'untisSchildConverter/Settings/init', $this );
		$this->myPluginHelpers = new Plugin_Helpers;
	}
	public function create_menu(){
		add_menu_page('Untis Schild Converter',
					 'Untis2Schild',
					 'manage_options', 
					 'mh_u2s_main-menu', 
					 [$this, 'render_Untis2Schild_page'],
					'dashicons-migrate',30);
		
		$hook_suffix=add_submenu_page(
					'mh_u2s_main-menu',
					'GPU002 Upload Page',
					'GPU002 Uploads',
					'install_plugins',
					'mh_u2s_uploads',
					[ $this, 'render_uploads' ]
);
		
		$hook_suffix2=add_submenu_page(
					'mh_u2s_main-menu',
					'Klassen konfigurieren',
					'Klassenänderungen',
					'install_plugins',
					'mh_u2s_klassen',
					[ $this, 'render_klassen' ]
);
		$hook_suffix3=add_submenu_page(
					'mh_u2s_main-menu',
					'Fächer konfigurieren',
					'Fächeränderungen',
					'install_plugins',
					'mh_u2s_faecher',
					[ $this, 'render_faecher' ]
		);
		$hook_suffix4=add_submenu_page(
					'mh_u2s_main-menu',
					'SchildNRW-Fächer',
					'SchildNRW-Fächer',
					'install_plugins',
					'mh_u2s_schildfaecher',
					[ $this, 'render_schildfaecher' ]
		);
		
		
		add_action(
			'load-' . $hook_suffix,
			[ $this, 'register_metaboxes' ]
		);
		add_action(
				'load-'.$hook_suffix3,[$this,'register_metaboxes_faecher']
		);
		add_action(
				'load-'.$hook_suffix2,[$this,'register_metaboxes_klassen']
		);
		add_action(
				'load-'.$hook_suffix4,[$this,'register_metaboxes_schildfaecher']
		);
		
	}
	public function register_metaboxes() {
		add_meta_box(
			'mh-u2s-gpu002Upload',
			'GPU002 hochladen',
			[ $this, 'render_gpuUploadField_settings' ],
			get_current_screen(),
			'normal'
		);
		add_meta_box(
			'mh-u2s_gpu002Anzeige2',
			'GPU Daten',
			[ $this, 'render_gpu002Anzeige2_settings' ],
			get_current_screen(),
			'normal'
		);	
	}
	
	public function register_metaboxes_faecher(){
		//Metabox für die Unterseite Fächer
		add_meta_box(
				'mh_u2s_loeschFaecherEingabe',
				'Eingabe der Fächer, die du ändern möchtest',
				[$this,'render_loesch_faecher_eingabe_neu'],
				'mh_u2s_faecher',
				'normal'
		);
		
		add_meta_box(
				'mh_u2s_loeschFaecherAusgabe',
				'Aktuell gespeicherte Fächer zur Löschung',
				[$this,'render_loesch_faecher_ausgabe'],
				'mh_u2s_faecher',
				'normal'
		);
	}
	
	public function register_metaboxes_klassen(){
		//Metabox für die Unterseite Klassen
		add_meta_box(
				'mh_u2s_loeschKlasseEingabe',
				'Zu löschende bzw. zu ändernde Klassen hier eingeben',
				[$this,'render_loesch_klassen_eingabe'],
				'mh_u2s_klassen',
				'normal'
		);
		
		add_meta_box(
				'mh_u2s_loeschKlasseAusgabe',
				'Aktuell gespeicherte Klassen zur Löschung/Änderung',
				[$this,'render_loesch_klasse_ausgabe'],
				'mh_u2s_klassen',
				'normal'
		);
	}
	public function register_metaboxes_schildfaecher(){
		//Metabox für die Unterseite SchildFaecher
		add_meta_box(
				'mh_u2s_schild_faecher_eingabe',
				'Fächerliste aus Schild hier hochladen',
				[$this,'render_schildfaecher_eingabe'],
				'mh_u2s_schildfaecher',
				'normal'
		);
	}
	
public function render_gpuUploadField_settings( $object, array $args ) {
	
		?>
<p>Lade hier deine GPU002.<b>csv</b> oder <b>.txt</b> hoch:</p>
				<form method="post" enctype="multipart/form-data">
					<input type="file" name="csv_file" id="csv_file" class="file"><br/><br/>
					<label>Schuljahr: </label> 
					<select name="schuljahr" id="schuljahr">
					<?php
					//Wähle das aktuelle Jahr - die Liste enthält das Vorjahr und drei Jahre weiter!
					$currentYear = date("Y");
					 for ($i = $currentYear - 1; $i <= $currentYear + 3; $i++) {
						$selected = ($i === (int)$currentYear) ? "selected" : "";
						 echo "<option value=\"$i\" $selected>$i</option>";
					}
					?>
					</select>
					<label>Halbjahr: </label> 
					<select name="halbjahr" id="halbjahr">
						<option>1</option>
						<option>2</option>
					</select><br/><br/>
					<label>CSV-Trennzeichen:</label>
					<select name="trennzeichen" id="trennzeichen">
						<option>Komma (,)</option>
						<option>Semikolon (;)</option>
						<option>Tabulator (	)</option>
					</select>
					<br/><br/>
					<input type="submit" name="submit" value="Datei hochladen" class="button button-primary">
					</form><br/>
					
		<?php
			if (isset($_POST['submit'])){
					// Überprüfen, ob eine Datei ausgewählt wurde
						if (isset($_FILES['csv_file'])) {
							$file_name = $_FILES['csv_file']['name'];
							$file_size = $_FILES['csv_file']['size'];
							$file_tmp = $_FILES['csv_file']['tmp_name'];
							$file_type = $_FILES['csv_file']['type'];
							$schuljahr = sanitize_text_field($_POST['schuljahr']);
							$halbjahr = sanitize_text_field($_POST['halbjahr']);
							$trennzeichen= sanitize_text_field($_POST['trennzeichen']);
							
			}
		?>
				
					<table class="wp-list-table widefat fixed striped table-view-list pages">
						<tr>
							<td>Dateiname</td>
							<td><?php echo $file_name?></td>
						</tr>
						<tr>
							<td>Dateigröße</td>
							<td><?php echo $file_size?></td>
						</tr>
						<tr>
							<td>Dateiordner</td>
							<td><?php echo $file_tmp?></td>
						</tr>
						<tr>
							<td>MimeTyp</td>
							<td><?php echo $file_type?></td>
						</tr>
						<tr>
							<td>Schuljahr/Halbjahr</td>
							<td><?php echo $schuljahr?>/<?php echo $halbjahr?></td>
						</tr>
					</table>
					
				<?php
		}
		else{
			printf('Es wurde bisher keine neue Datei hochgeladen!');
		}
				
}	
		
	public function render_gpu002Anzeige2_settings( $object, array $args ) {
		if (isset($_POST['submit'])) {
			if (isset($_FILES['csv_file'])) {
							$file_name = $_FILES['csv_file']['name'];
							$file_tmp = $_FILES['csv_file']['tmp_name'];
							$schuljahr = sanitize_text_field($_POST['schuljahr']);
							$halbjahr = sanitize_text_field($_POST['halbjahr']);
							$trennzeichen= sanitize_text_field($_POST['trennzeichen']);
							
			}
			if (strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) == 'csv' || strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) == 'txt' ){
				$csvHandler = new CSVHandler($file_tmp, $file_name, $schuljahr,$halbjahr,$trennzeichen);	
				$csvHandler->csvLeague();
				//printf($csvHandler->tabellenAusgabe());

		}
			else{
				printf('<p style="font-size:1.4em; color:red;">'
						. 'Bitte lade eine CSV-Datei oder eine TXT-Datei hoch.</p>');
			}	
		
		}
		else{
			printf('Bisher wurde keine Datei hochgeladen.');
		}
		$mySchildImportRepository = new SchildImportRepository();
		$fachabgleich=$mySchildImportRepository->schildnrwFachabgleich();
		
		echo '<h3>Facheinträge, die nicht in SCHILDNRW sind: <span style="color:red;">'.count($fachabgleich).'</span></h3>';
		?>
		
		<details style="background: #F6E3CE; border-left:5px solid #DF7401; padding:5px; width:50%;">
			<summary style="padding:2px;">Nachstehende Fächer aus Untis sind nicht in der importieren
			SCHILD-NRW-Fächerliste vorhanden (klicken um alle anzeigen zu lassen).</summary>
	
		<?php
			$this->myPluginHelpers->resultsetToTable( $fachabgleich);
		
			echo '</details>';
		
		$mySchildImportRepository->tabelleAusgeben();
		
		
	}
	
	public function render_loesch_faecher_eingabe_neu( $object, array $args ){
	?>
		<p>Füge in das unten stehende Textfeld die zu löschenden bzw. zu ändernden Fächer ein. 
		   In jeder Zeile steht kommagetrennt ein Fach inkl. regulärem Ausdruck
		   in welchen Klassen das Fach gelöscht werden soll. Sollte ein SChild-Fach gesetzt sein, wird in den 
		entsprechenden Klassen das Schild-Fach gelöscht.</p>
		<p style="color:blue;font-style: italic">
			Beispiel 1:<br/> VERT,%, %,Bemerkung 1</br>ER, Reli, %,Gilt für alles Klassen</br>
		</p>
		<p>Möchtest du ein oder mehrere UNTIS-Fächer löschen und ein oder mehrere Schildfächer anlegen, 
			kannst du die Fächer einfach mit einem Strich (<b>|</b>) trennen.
		</p>
		<p style="color:blue;font-style: italic">
			Beispiel 2: Euer Unterricht bei den Automobilern findet im Lernfeld-Unterricht statt. 
			Daher führt UNTIS die Fächer LF1 und LF2. <br/>Diese beiden Fächer sollen jetzt aber
			die Fächer WSP, KUP und KPA werden in Schild. Das erfasst du dann wie folgt: 
			<br/><br/> LFU1|LFU2,WSP|KUP|KPA,AK%,Lernfelder
		</p>
		
			<p>Geben Sie die Daten ein, die in die Datenbank geschrieben werden sollen:</p>
			 <form method="post" enctype="multipart/form-data">
			  <textarea name="daten" rows="15" cols="100" class="code"></textarea>
			  <br><br>
			
			    <label><input type="radio" name="auswahl" value="hinzufuegen" checked> Hinzufügen</label>
				
				<label><input type="radio" name="auswahl" value="neu">neu <i>(bisherige Einträge werden gelöscht)</i></label>
				<br><br>
			<input type="submit" name="submit" value="Speichern" class="button button-primary">
		 </form>
		

<?php
// Überprüfen, ob das Formular abgeschickt wurde
if (isset($_POST['submit'])) {
    // Daten aus dem Textfeld auslesen
    $daten = trim($_POST['daten']);
	//print_r($daten);
    
    // Zeilen in ein Array aufteilen
    $zeilen = explode("\n", $daten);
//	Array für die Fächer
	$loeschFaecher= array();
	
			foreach ($zeilen as $row){
				$row = explode(",",trim($row));
				$loeschFach = array(
					'fach_untis' => $row[0],
					'fach_schild' => $row[1],
					'klasse'=>$row[2],
					'bemerkung' =>$row[3]
				);
				//Füge das oben angelegte Löschfach dem Array LoeschFaecher hinzu
				$loeschFaecher[]=$loeschFach;
				}
//radio Button auswerten
	$operation ="";
	if (isset($_POST["auswahl"])) {
        $auswahl = $_POST["auswahl"];
        if ($auswahl == "hinzufuegen") {
            $operation="add";
        } elseif ($auswahl == "neu") {
            $operation="new";
        }
	}
	
	//LoeschFaecherHandler erzeugen mit Datetime
	
	$myLoeschFaecherHandler=new LoeschFaecherHandler();
	$myLoeschFaecherHandler->loeschFaecherToDb($loeschFaecher, $operation);
	}
	}
	
	public function render_loesch_faecher_ausgabe( $object, array $args ){
		?>
		<?php
		$myLoeschFaecherHandler = new LoeschFaecherHandler();
		$myLoeschFaecherHandler->loeschFaecherAnzeigen();
		?>
<?php		
	}
	
	
	public function render_loesch_klassen_eingabe( $object, array $args ){
	?>
		<p>Füge in das unten stehende Textfeld die zu löschenden bzw. zu ändernden Klassen ein. 
		   In jeder Zeile steht kommagetrennt eine Klasse aus Untis und die Klasse in Schild,</p><!-- comment -->
		in die die Klasse aus Untis geändert werden soll. Steht hingegen bei SCHILD kein Fach, 
		wird die Klasse aus Untis gelöscht.
		<p style="color:blue;font-style: italic">
			Beispiel:<br/> EC1,ECKU1,Die Klasse heißt in Schild ECKU1</br>
			BERAT,,Beratung wird gelöscht!</br>
			
		</p>
		
			<p>Geben Sie die Daten ein, die in die Datenbank geschrieben werden sollen:</p>
			 <form method="post" enctype="multipart/form-data">
			  <textarea name="daten" rows="10" cols="50" class="code"></textarea>
			  <br><br>
			  <label><input type="radio" name="auswahl" value="hinzufuegen" checked> Hinzufügen</label>
			  <label><input type="radio" name="auswahl" value="neu">neu <i>(bisherige Einträge werden gelöscht)</i></label>
			  <br><br>
			<input type="submit" name="submit" value="Speichern" class="button button-primary">
		 </form>
		

		<?php
		// Überprüfen, ob das Formular abgeschickt wurde
		if (isset($_POST['submit'])) {
			// Daten aus dem Textfeld auslesen
			$daten = trim($_POST['daten']);
			//print_r($daten);

			// Zeilen in ein Array aufteilen
			$zeilen = explode("\n", $daten);
		//	Array für die Fächer
			$loeschKlassen= array();

					foreach ($zeilen as $row){
						$row = explode(",",trim($row));
						$loeschKlasse = array(
							'klasse_untis' => $row[0],
							'klasse_schild'=>$row[1],
							'bemerkung' =>$row[2]
						);
						//Füge das oben angelegte Löschfach dem Array LoeschFaecher hinzu
						$loeschKlassen[]=$loeschKlasse;
						}
		//radio Button auswerten
			$operation ="";
			if (isset($_POST["auswahl"])) {
				$auswahl = $_POST["auswahl"];
				if ($auswahl == "hinzufuegen") {
					$operation="add";
				} elseif ($auswahl == "neu") {
					$operation="new";
				}
	}

			//LoeschFaecherHandler erzeugen mit Datetime

			$myLoeschKlasseHandler=new LoeschKlasseHandler();
			$myLoeschKlasseHandler->loeschKlasseToDb($loeschKlassen, $operation);
			}
			}
	
		public function render_loesch_klasse_ausgabe( $object, array $args ){
		?>
		<?php
		$myLoeschKlasseHandler = new LoeschKlasseHandler();
		$myLoeschKlasseHandler->loeschKlasseAnzeigen();
		?>
<?php		
	}
	
	
	public function render_Untis2Schild_page ( ){
	?>
		<h1>Herzlich Willkommen zum Untis2SCHILDNRW Konverter</h1>
		<p>
			<i>Das Plugin hilft dir dabei die aktuell unterichteten Fächer 
				den Schülerinnen und Schülern in SCHILD NRW im aktuellen Lernabschnitt zuzuordnen.
				Dabei wird folgendes gemacht: </i></p>
		<ol>
			<li>Export der Unterrichtsverteilung der aktuellen Periode aus Untis (GPU2....TXT)</li>
			<li>Diese Datei lädst du hier im Plugin hoch.</li>
			<li>Das Plugin macht nun folgende Schritte</li>
			<ol>
				<li>Dubletten entfernen</li>
				<li>Klassenbezeichnungen löschen oder anpassen (z.B. Vertretungsreserven)</li>
				<li>Fächer löschen oder anpassen (z.B: Lernfeldunterricht in Fächer oder Fachumbenennungen</li><!-- comment -->
				<li>Ablgeich mit vorhandenen SchildNRW-Fächern</li>
				<li>Bereitstellung einer Import-Datei für SchildNRW</li>
			</ol>
			<li>Import der Unterrichtsverteilung in SchildNRW</li>
		</ol>
				
		<p>Nach der Verwendung des Plugins hast du in SCHILDNRW bei allen SuS die erteilten
		Unterrichtsstunden in diesem Halbjahr im Klassenverband zugeordnet. Kurse etc.
		müssen dann noch manuell zugeordnet werden. </p>
		
		<h2>Wie solltest du vorgehen?</h2>
		<p>Wenn du das Plugin das <b>allererste Mal</b> einsetzt, exportiere zuerst
			in Schild NRW die vorhandenen Fächer. Die importiert du dann hier mithilfe
			einer Excel-Datei. Im nächsten Schritt würde ich den GPU aus Untis hier 
			importieren und schauen was herauskommt. Du stellst fest, dass Fächer in Schild fehlen
			oder dass in UNTIS Fächer sind, die es in Schild nicht gibt.<br/>
			Jetzt solltest du Fächer und KLassen definieren, die aus den UNTIS-Daten gelöscht oder 
			verändert werden sollen. Im Anschluss importierst du erneute die GPU und prüfst
		was dann als Ergebnis herauskommt. <br/><br/>
		Im letzten Schritt lädst du die Excel-Datei herunter, die du dann in Schild importieren kannst.</p>
		
		
			
		
	<?php
	
	}
	
	function render_Uploads(){
	?>
	<div class="wrap">
		<h1><?php echo get_admin_page_title(); ?></h1>
		<?php
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		?>
		<div id="poststuff" class="metabox-holder">
			
			<div id="post-body" class="">
				<div id="post-body-content" class="h">
				<?php
				do_meta_boxes(  get_current_screen(), 'normal', null );
				?>
				
				</div>
				<div id="post-body-content" class="">
					
				<?php
					if (isset($_POST['submit'])) {
						do_meta_boxes( get_current_screen(), 'advanced', null );
					}
				?>	
				</div>
			</div>
		</div>
	<br class="clear"/>
	</div>
	<?php	 
	}
	
	function render_klassen(){
		?>
<div class="wrap">
		<h1><?php echo get_admin_page_title(); ?></h1>
		<?php
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			
		?>
		
		<div id="poststuff" class="metabox-holder">
			
			<div id="post-body" class="">
				<div id="post-body-content" class="h">
				<?php
				do_meta_boxes( 'mh_u2s_klassen', 'normal', null );
				
				?>
				</div>
			</div>
		</div>
	<br class="clear"/>

	</div>
		<?php
	
	}
	
  function render_faecher(){
		?>
	<div class="wrap">
		<h1><?php echo get_admin_page_title(); ?></h1>
		<?php
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			
		?>
		<div id="poststuff" class="metabox-holder">
			
			<div id="post-body" class="">
				<div id="post-body-content" class="h">
				<?php
				do_meta_boxes( 'mh_u2s_faecher', 'normal', null );
				
				?>
				</div>
				<div id="post-body-content" class="">
				<?php
					if (isset($_POST['submit'])) {
						do_meta_boxes( get_current_screen(), 'advanced', null );
					}
				?>	
				</div>
			</div>
		</div>
	<br class="clear"/>

	</div>
		<?php
	}
	
function render_schildfaecher(){
		?>
	<div class="wrap">
		<h1><?php echo get_admin_page_title(); ?></h1>
		<?php
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			
		?>
		<div id="poststuff" class="metabox-holder">
			
			<div id="post-body" class="">
				<div id="post-body-content" class="h">
				<?php
				do_meta_boxes( 'mh_u2s_schildfaecher', 'normal', null );
				
				?>
				</div>
				<div id="post-body-content" class="">
				<?php
					if (isset($_POST['submit'])) {
						do_meta_boxes( get_current_screen(), 'advanced', null );
					}
				?>	
				</div>
			</div>
		</div>
		</div>
	<div class="wrap">
		
		<?php
		$mySchildFaecherRepository = new SchildFaecherRepository();
		echo $mySchildFaecherRepository->tabelleAusgeben();
	?>
		
	</div>
	<?php	
	}
	public function render_schildfaecher_eingabe(){
		
		$myExcelhandler = new ExcelHandler();
		$mySchildFaecherRepository = new SchildFaecherRepository();
		
		?>
		<p>
			In Schild-NRW kannst du unter <i>Unterrichtsfächer->Excel-Export</i>
			die Fächer exportieren und hier importieren.
		</p>
<p>Lade hier den Excel-Export mit Schild-Fächern hoch (xlsx-Datei):</p>
				<form method="post" enctype="multipart/form-data">
					<input type="file" name="xlsx_file" id="xlsx_file" class="file"><br/><br/>
					<input type="submit" name="submit" value="Datei hochladen" class="button button-primary">
					</form><br/>
					
		<?php
			if (isset($_POST['submit'])){
					//Überprüfe, ob das ZipArchive überhaupt aktiviert ist
					if (!class_exists('ZipArchive')) {
						echo '<p style="colore:red;"><b>Der Import hat nicht funktioniert!</b><br/> Aktivieren Sie in der php.ini die PHP-Extension "zip"</p>';
						wp_die('');
						
					}
					// Überprüfen, ob eine Datei ausgewählt wurde
						if (isset($_FILES['xlsx_file'])) {
							$file_name = $_FILES['xlsx_file']['name'];	
							$file_type = $_FILES['xlsx_file']['type'];
							if ($file_type == "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"){
								$upload_dir = '..\wp-content\plugins\untisSchildConverter\uploads';
								$file_path = $upload_dir . '\\' . $file_name;
								move_uploaded_file($_FILES['xlsx_file']['tmp_name'], $file_path);
								$daten=$myExcelhandler->spreadsheetReader($file_path,3);
								$mySchildFaecherRepository->tabelleEinlesen($daten);
							}
							else{
								echo "<b>Lade eine Excel-Datei noch</b><br/>";
							}						
						}	
						
			}		
	}
	
}
	
