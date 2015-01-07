<?php

define('INC_FROM_CRON_SCRIPT', true);

require_once '../config.php';
require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/chargesociales.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/recurrence/class/recurrence.class.php';

$PDOdb = new TPDOdb;

check($PDOdb);

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

		// Si il s'agit de la première occurrence
		if (empty($TLastCharge)) {
			// Récupérer les informations de la charge sociale source
			$chargesociale = new ChargeSociales($db);
			$chargesociale->fetch($recurrence->fk_chargesociale);
			
			$last_date = new DateTime(date('Y-m-d', $chargesociale->periode));
			$current_date = new DateTime(date('Y-m-d'));
			
			$diff = $current_date->diff($last_date);

			switch ($recurrence->periode) {
				case 'jour':
					// Différence >= 1 jour
					if ($diff->days >= 1) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
					
					// Création des charges sociales supplémentaires selon nombre prévisionnel
					if ($recurrence->nb_previsionnel > 0) {
						$i = $recurrence->nb_previsionnel;
						$counter = 1;
						
						while ($i--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . $counter . 'days'));
							$date = strtotime($date);
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				case 'hebdo':
					// Différence >= 7 jours
					if ($diff->days >= 7) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
					
					if ($recurrence->nb_previsionnel > 0) {
						$i = $recurrence->nb_previsionnel;
						$counter = 1;
						
						while ($i--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . $counter . 'week'));
							$date = strtotime($date);
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				case 'mensuel':
					// Différence >= 1 mois
					if ($diff->m >= 1) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
					
					if ($recurrence->nb_previsionnel > 0) {
						$i = $recurrence->nb_previsionnel;
						$counter = 1;
						
						while ($i--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . $counter . 'month'));
							$date = strtotime($date);
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				case 'trim':
					// Différence >= 4 mois (trimestre)
					if ($diff->m >= 4) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
					
					if ($recurrence->nb_previsionnel > 0) {
						$i = $recurrence->nb_previsionnel;
						$counter = 1;
						
						while ($i--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . ($counter * 4) . 'month'));
							$date = strtotime($date);
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				case 'annuel':
					// Différence >= 1 an
					if ($diff->y >= 1) {
						$id = create_charge_sociale($recurrence->fk_chargesociale, time());
					}
					
					if ($recurrence->nb_previsionnel > 0) {
						$i = $recurrence->nb_previsionnel;
						$counter = 1;
						
						while ($i--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d') . '+' . $counter . 'year'));
							$date = strtotime($date);
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							
							$counter++;
						}
					}
					break;
				default:
			}
		} else {
			// On récupére les infos de la précédente charge sociale créée
			$last = $TLastCharge[0];
			$lastCharge = new ChargeSociales($db);
			$lastCharge->fetch($last->fk_target);
			
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
			
			switch ($recurrence->periode) {
				case 'jour':
					if ($recurrence->nb_previsionnel > 0) {
						$counter  = 1;
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d', $lastCharge->periode) . ' +' . $counter . 'day'));
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							$counter++;
						}
					} else {
						// Si la dernière charge enregistrée ne correspond pas à la date du jour
						if ($lastCharge->periode < time() && $diff->days >= 1) {
							$last_date = new DateTime(date('Y-m-d', $lastCharge->periode));
							$current_date = new DateTime(date('Y-m-d'));
							
							$diff = $current_date->diff($last_date);
						}
					}
					break;
				case 'hebdo':
					if ($recurrence->nb_previsionnel > 0) {
						$counter  = 1;
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d', $lastCharge->periode) . ' +' . $counter . 'week'));
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							$counter++;
						}
					} else {
						// Si la dernière charge enregistrée ne correspond pas à la date du jour
						if ($lastCharge->periode < time() && $diff->days >= 7) {
							$last_date = new DateTime(date('Y-m-d', $lastCharge->periode));
							$current_date = new DateTime(date('Y-m-d'));
							
							$diff = $current_date->diff($last_date);
						}
					}
					break;
				case 'mensuel':
					if ($recurrence->nb_previsionnel > 0) {
						$counter  = 1;
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d', $lastCharge->periode) . ' +' . $counter . 'month'));
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							$counter++;
						}
					} else {
						// Si la dernière charge enregistrée ne correspond pas à la date du jour
						if ($lastCharge->periode < time() && $diff->m >= 1) {
							$last_date = new DateTime(date('Y-m-d', $lastCharge->periode));
							$current_date = new DateTime(date('Y-m-d'));
							
							$diff = $current_date->diff($last_date);
						}
					}
					break;
				case 'trim':
					if ($recurrence->nb_previsionnel > 0) {
						$counter  = 1;
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d', $lastCharge->periode) . ' +' . ($counter * 4) . 'month'));
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							$counter++;
						}
					} else {
						// Si la dernière charge enregistrée ne correspond pas à la date du jour
						if ($lastCharge->periode < time() && $diff->m >= 4) {
							$last_date = new DateTime(date('Y-m-d', $lastCharge->periode));
							$current_date = new DateTime(date('Y-m-d'));
							
							$diff = $current_date->diff($last_date);
						}
					}
					break;
				case 'annuel':
					if ($recurrence->nb_previsionnel > 0) {
						$counter  = 1;
						while ($add--) {
							$date = date('Y-m-d', strtotime(date('Y-m-d', $lastCharge->periode) . ' +' . $counter . 'year'));
							
							$id = create_charge_sociale($recurrence->fk_chargesociale, $date);
							$counter++;
						}
					} else {
						// Si la dernière charge enregistrée ne correspond pas à la date du jour
						if ($lastCharge->periode < time() && $diff->y >= 1) {
							$last_date = new DateTime(date('Y-m-d', $lastCharge->periode));
							$current_date = new DateTime(date('Y-m-d'));
							
							$diff = $current_date->diff($last_date);
						}
					}
					break;
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
