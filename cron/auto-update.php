<?php

set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('INC_FROM_CRON_SCRIPT', true);

require_once '../config.php';
require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/chargesociales.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/recurrence/class/recurrence.class.php';
dol_include_once("/cron/class/cronjob.class.php");

global $db, $user;

$PDOdb = new TPDOdb;

check($PDOdb);

$PDOdb->close();

function check(&$PDOdb) {
	global $db, $user;
	
	// Récupération de la liste des charges récurrentes
	$sql = "
		SELECT rowid, fk_chargesociale, periode, nb_previsionnel, date_fin
		FROM " . MAIN_DB_PREFIX . "recurrence
	";
	
	$PDOdb->Execute($sql);
	$TRecurrences = $PDOdb->Get_All();

	foreach ($TRecurrences as $recurrence) {
		// Récupération de la dernière charge sociale créée
		$sql = "
			SELECT rowid, fk_source, sourcetype, fk_target, targettype
			FROM " . MAIN_DB_PREFIX . "element_element
			WHERE fk_source = " . $recurrence->fk_chargesociale . "
			AND sourcetype = 'chargesociales'
			GROUP BY fk_source, sourcetype, fk_target, targettype
			HAVING rowid >= ALL (SELECT MAX(rowid) 
									FROM " . MAIN_DB_PREFIX . "element_element 
									WHERE fk_source = " . $recurrence->fk_chargesociale . "
									AND sourcetype = 'chargesociales')
		";
				
		$PDOdb->Execute($sql);
		$TLastCharge = $PDOdb->Get_all();
		
		if (empty($TLastCharge)) {
			// Récupérer les informations de la charge sociale source
			$lastCharge = new ChargeSociales($db);
			$lastCharge->fetch($recurrence->fk_chargesociale);
		} else {
			// On récupére les infos de la précédente charge sociale créée
			$last = $TLastCharge[0];
			$lastCharge = new ChargeSociales($db);
			$lastCharge->fetch($last->fk_target);
		}
		
		// Récupération des charges issues de cette recurrence
		$sql = "
			SELECT c.rowid, c.libelle, c.periode
			FROM " . MAIN_DB_PREFIX . "chargesociales as c
			INNER JOIN " . MAIN_DB_PREFIX . "element_element as e ON e.fk_target = c.rowid
			WHERE e.fk_source = " . $recurrence->fk_chargesociale . "
			AND e.sourcetype = 'chargesociales'
			AND c.periode > CURDATE()
		";
		
		$PDOdb->Execute($sql);
		$TCharges = $PDOdb->Get_all();
		
		// Récurrences à ajouter pour correspondre au nombre previsionnel
		$add = $recurrence->nb_previsionnel - count($TCharges);
				
		if ($add < 0)
			$add = 0;
		
		if (empty($lastCharge->id)) {
			$lastCharge = new ChargeSociales($db);
			$lastCharge->fetch($recurrence->fk_chargesociale);
		}
				
		$last_date = new DateTime(date('Y-m-d', $lastCharge->periode));
		$current_date = new DateTime(date('Y-m-d'));
		
		$diff = $current_date->diff($last_date);
		
		$date_fin_recurrence = strtotime($recurrence->date_fin);
		
		var_dump($date_fin_recurrence); exit;
		if (strtotime(date('Y-m-d', time())) < $date_fin_recurrence) {
			var_dump('yo');
			switch ($recurrence->periode) {
				case 'jour':
					// Différence >= 1 jour
					if ($diff->days >= 1 && $lastCharge->periode < strtotime(date('Y-m-d', time()))) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
					
					// Création des charges sociales supplémentaires selon nombre prévisionnel
					if ($add > 0) {
						$counter = 1;
						
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . $counter . 'days'));
							$date = strtotime($date);
							
							if ($date >= $date_fin_recurrence)
								break;
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				case 'hebdo':
					// Différence >= 7 jours
					if ($diff->days >= 7 && $lastCharge->periode < strtotime(date('Y-m-d', time()))) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
					
					if ($add > 0) {
						$counter = 1;
						
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . $counter . 'week'));
							$date = strtotime($date);
							
							if ($date >= $date_fin_recurrence)
								break;
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				case 'mensuel':
					// Différence >= 1 mois
					var_dump('yo'); exit;
					if ($diff->m >= 1 && $lastCharge->periode < strtotime(date('Y-m-d', time()))) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
	
					if ($add > 0) {
						$counter = 1;
						
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . $counter . 'month'));
							$date = strtotime($date);
							
							if ($date >= $date_fin_recurrence)
								break;
	
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				case 'trim':
					// Différence >= 3 mois
					if ($diff->m >= 3 && $lastCharge->periode < strtotime(date('Y-m-d', time()))) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
				
					if ($add > 0) {
						$counter = 1;
						var_dump($add);
						
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d', $lastCharge->periode) . '+' . ($counter * 3) . 'month'));
							$date = strtotime($date);
							
							if ($date >= $date_fin_recurrence)
								break;
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				case 'annuel':
					// Différence >= 1 an
					if ($diff->y >= 1 && $lastCharge->periode < strtotime(date('Y-m-d', time()))) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
					
					if ($add > 0) {
						$counter = 1;
						
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . $counter . 'year'));
							$date = strtotime($date);
							
							if ($date >= $date_fin_recurrence)
								break;
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				default:
			}	
		}
	}
}

function create_charge_sociale($id_source, $date) {
	global $db, $user;
	
	// Récupération de la charge sociale initiale
	$obj = new ChargeSociales($db);
	$obj->fetch($id_source);
	
	if (empty($obj->id)) {
		return false;
	} else {
		// Création de la nouvelle charge sociale
		$chargesociale = new ChargeSociales($db);
		$chargesociale->type = $obj->type;
		$chargesociale->lib = $obj->lib;
		$chargesociale->date_ech = $date;
		$chargesociale->periode = $date;
		$chargesociale->amount = $obj->amount;

		$id = $chargesociale->create($user);
				
		$chargesociale->add_object_linked('chargesociales', $id_source);
		
		print 'CREATION : Charge Sociale (ID = ' . $id . ')<br />';
		
		return $id;
	}
}
