<?php

/*
 * 
 * Pour appel ajax
 */

require_once '../config.php';
require_once '../class/recurrence.class.php';

$type		  = __get('type');
$id_charge 	  = __get('id_charge');
$periode 	  = __get('periode');
$date_fin_rec = __get('date_fin_rec');
$nb_prev_rec  = __get('nb_prev_rec');
$montant  	  = __get('montant');

if (empty($nb_prev_rec))
	$nb_prev_rec = 0;

if ($type == 'delete-recurrence')
	Recurrence::del( $id_charge);
else	
	Recurrence::updateReccurence( $id_charge, $periode, $date_fin_rec, $nb_prev_rec, $montant);