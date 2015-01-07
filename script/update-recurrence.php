<?php

require_once '../config.php';
require_once '../class/recurrence.class.php';

$PDOdb = new TPDOdb;

$type		  = __get('type');
$id_charge 	  = __get('id_charge');
$periode 	  = __get('periode');
$date_fin_rec = __get('date_fin_rec');
$nb_prev_rec  = __get('nb_prev_rec');

if (empty($nb_prev_rec))
	$nb_prev_rec = 0;

if ($type == 'delete-recurrence')
	TRecurrence::del($PDOdb, $id_charge);
else	
	TRecurrence::update($PDOdb, $id_charge, $periode, $date_fin_rec, $nb_prev_rec);