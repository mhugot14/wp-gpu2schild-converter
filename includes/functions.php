<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace untisSchildConverter;

class Plugin_Helpers{
	public static function activate(): void{
		/*Hier passiert das, was passiert, wenn das Plugin aktiviert wird
		  */
		
		wp_schedule_event(time() - DAY_IN_SECONDS,'weekly','untisSchildConverter/weekly_cron');
	}
}

?>