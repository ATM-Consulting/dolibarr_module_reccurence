<?php

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
		// Récupération des liaisons
		$sql = "
			SELECT rowid, fk_source, sourcetype, fk_target, targettype
			FROM " . MAIN_DB_PREFIX . "element_element
			WHERE fk_source = " . $recurrence->fk_chargesociale . "
			AND sourcetype = 'chargesociales'
		";
		
		$PDOdb->Execute($sql);
		$TLinks = $PDOdb->Get_all();
		
		// Si il s'agit de la première occurrence
		if (empty($TLinks)) {
			$id = create_charge_sociale($recurrence->fk_chargesociale);
		}
	}
}

function create_charge_sociale($id_source) {
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
		$chargesociale->date_ech = time();
		$chargesociale->periode = time();
		$chargesociale->amount = $obj->amount;

		$id = $chargesociale->create($user);
				
		$chargesociale->add_object_linked('chargesociales', $id_source);
		
		return $id;
	}
}
