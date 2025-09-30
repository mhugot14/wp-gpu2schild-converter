<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace untisSchildConverter;

require_once __DIR__ . '/../vendor/autoload.php';

class Plugin_Helpers{
	public static function activate(): void{
		/*Hier passiert das, was passiert, wenn das Plugin aktiviert wird
		  */
		
		wp_schedule_event(time() - DAY_IN_SECONDS,'weekly','untisSchildConverter/weekly_cron');
		
		//Tabellen anlegen
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		//tabellennamen
		$usc_loesch_faecher = $wpdb->prefix.'usc_loesch_faecher';
		$usc_loesch_klassen = $wpdb->prefix.'usc_loesch_klassen';
		$usc_schildimport = $wpdb->prefix.'usc_schildimport';
		$usc_schildfaecher = $wpdb->prefix.'usc_schildfaecher';
		$sql_usc_loesch_faecher = "CREATE TABLE `$usc_loesch_faecher` (
									`id` int(11) NOT NULL AUTO_INCREMENT,
									`fach_untis` varchar(50) NOT NULL,
									`fach_schild` varchar(50) NOT NULL,
									`klasse` varchar(50) NOT NULL,
									`bemerkung` varchar(100) NOT NULL,
									`importdatum` datetime NOT NULL,
									PRIMARY KEY (`id`)
									)ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";
		
		$sql_usc_loesch_klassen = "CREATE TABLE `$usc_loesch_klassen` (
									`id` int(11) NOT NULL AUTO_INCREMENT,
									`klasse_untis` varchar(10) NOT NULL,
									`klasse_schild` varchar(10) NOT NULL,
									`bemerkung` varchar(100) NOT NULL,
									`importdatum` datetime NOT NULL,
									PRIMARY KEY (`id`)
								   ) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";
		
		$sql_usc_schildimport = "CREATE TABLE  `$usc_schildimport`(
									`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
									`schuljahr` smallint(6) NOT NULL,
									`halbjahr` varchar(3) NOT NULL,
									`klasse` varchar(10) NOT NULL,
									`fach` varchar(10) NOT NULL,
									`lehrer` varchar(10) NOT NULL,
									`giltfuerHalbjahr` varchar(5) NOT NULL,
									`importID` int(11) NOT NULL,
									PRIMARY KEY (`id`)
								   ) ENGINE=InnoDB AUTO_INCREMENT=1474 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";
		
		$sql_usc_schildfaecher = "CREATE TABLE `$usc_schildfaecher` (
				`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				`interne_kurzform` varchar(20) NOT NULL,
				`asd_kuerzel` varchar(20) NOT NULL,
				`bezeichnung` varchar(150) NOT NULL,
				 PRIMARY KEY (`id`)
			  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta($sql_usc_loesch_faecher);
			dbDelta($sql_usc_loesch_klassen);
			dbDelta($sql_usc_schildimport);
			dbDelta($sql_usc_schildfaecher);
			
			
		
		
	}
	
	public  function strichSeparator(string $text): array{
		 $arraytext = array();
		 
		if (strpos($text, '|') !== false) {
				  $arraytext= explode('|',$text);
			  }
			  else{
				  $arraytext[0]=$text;
			  }
			  
			return $arraytext;
	}
	
	public function resultsetToTable(array $resultSet){	
    // Tabelleninhalte aus dem ResultSet
		echo '<table class="wp-list-table sortable fixed striped table-view-list pages">';
		foreach ($resultSet as $row) {
			echo '<tr>';
			foreach ($row as $columnValue) {
				echo '<td>' . $columnValue . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	
}

?>