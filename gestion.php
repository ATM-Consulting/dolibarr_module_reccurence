<?php
require_once 'config.php';
require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/chargesociales.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/recurrence/class/recurrence.class.php';

$langs->load('recurrence@recurrence');

$PDOdb = new TPDOdb;

$action = __get('action', 'view');

$page   = __get('page', 0);
if ($page < 0) $page = 0;

$limit  = $conf->liste_limit;
$offset = $limit * $page;

/*
 * HEADER
 */ 
llxHeader('', 'Récurrence des charges sociales');

print_fiche_titre('Récurrence des charges sociales', '', 'report.png@report');

print dol_get_fiche_head(array(
	array(DOL_URL_ROOT.'/custom/recurrence/gestion.php?action=view', 'Liste des récurrences', 'view'),
	array(DOL_URL_ROOT.'/custom/recurrence/gestion.php?action=add', 'Enregistrer une tâche récurrente', 'add')
)  , $action, '');

_liste_charges_sociales($PDOdb, $action, $page, $limit, $offset);

llxfooter();
?>

<script>
	$('.update-recurrence, .delete-recurrence').click(function() {
		var type 		 = $(this).attr('class');
		var id_charge 	 = $(this).data('chargesociale');
		var periode		 = $('#periode_' + id_charge + ' option:selected').val();
		var date_fin_rec = $('#date_fin_rec_' + id_charge).val();
		var nb_prev_rec  = $('#nb_prev_rec_' + id_charge).val();
		
		$.ajax({
			type: 'POST',
			url: 'script/update-recurrence.php',
			data: { type: type, id_charge: id_charge, periode: periode, date_fin_rec: date_fin_rec, nb_prev_rec: nb_prev_rec }
		}).done(function(data) {
			document.location.reload(true);
		});
	});
</script>

<?php

/*
 * Liste des charges sociales
 */
function _liste_charges_sociales(&$PDOdb, $action, $page, $limit, $offset) {
	global $conf, $db;
	
	$charge_sociale = new ChargeSociales($PDOdb);
	
	$sql = "
		SELECT cs.rowid as id, cs.fk_type as type, cs.amount, cs.date_ech, cs.libelle, cs.paye, cs.periode, c.libelle as type_lib, SUM(pc.amount) as alreadypayed
		FROM " . MAIN_DB_PREFIX . "c_chargesociales as c
		INNER JOIN " . MAIN_DB_PREFIX . "chargesociales as cs ON c.id = cs.fk_type
		LEFT JOIN " . MAIN_DB_PREFIX . "paiementcharge as pc ON pc.fk_charge = cs.rowid
		
		WHERE cs.fk_type = c.id
		AND cs.entity = "  . $conf->entity . "
		AND cs.rowid NOT IN (SELECT fk_target FROM " . MAIN_DB_PREFIX . "element_element WHERE sourcetype = 'chargesociales' AND targettype = 'chargesociales')
	";
	
	if ($action == 'add')
		$sql .= "AND cs.rowid NOT IN (SELECT fk_chargesociale FROM " . MAIN_DB_PREFIX . "recurrence)";
	else
		$sql .= "AND cs.rowid IN (SELECT fk_chargesociale FROM " . MAIN_DB_PREFIX . "recurrence)";

	$sql .= 'GROUP BY cs.rowid, cs.fk_type, cs.amount, cs.date_ech, cs.libelle, cs.paye, cs.periode, c.libelle ';
	$sql .= 'ORDER BY cs.periode DESC ';
	$sql .= 'LIMIT ' . $offset . ', ' . ($limit + 1);

	$res = $PDOdb->Execute($sql);
	
	$result = $PDOdb->Get_All();
	$num = count($result);
	
	$param = '&action=' . $action;
	print_barre_liste('Liste', $page, $_SERVER["PHP_SELF"], $param, '', '', '', $num, 100);
	
	echo '<table class="noborder" width="100%">';
	_print_head_tab_charges_sociales();

	echo '<tbody>';

	foreach ($result as $obj) {
		$charge_sociale->id  = $obj->id;
		$charge_sociale->lib = $obj->id;
		$charge_sociale->ref = $obj->id;
	
		if ($action != 'add') {
			$recurrence = TRecurrence::get_recurrence($PDOdb, $charge_sociale->id);
		}
		
		echo '<tr>';

		echo '<td>' . $charge_sociale->getNomUrl(1,'20') . '</td>';
		echo '<td>' . utf8_encode($obj->libelle) . '</td>';
		echo '<td>' . utf8_encode($obj->type_lib) . '</td>'; // Type
		echo '<td>' . dol_print_date($obj->periode, 'day') . '</td>';
		echo '<td>' . price($obj->amount, 2) . '</td>';
		
		echo '<td>';
		if ($action == 'add')
			TRecurrence::get_liste_periodes($PDOdb, 'periode_' . $obj->id, 'fk_periode');
		else
			TRecurrence::get_liste_periodes($PDOdb, 'periode_' . $obj->id, 'fk_periode', $recurrence->periode);
		echo '</td>';
		
		//echo '<td>' . $form_core->calendrier('', 'date_fin_rec', '') . '</td>';
		if ($action == 'add') {
			echo '<td><input type="text" id="date_fin_rec_' . $obj->id . '" name="date_fin_rec" /></td>';
			echo '<td><input type="text" id="nb_prev_rec_' . $obj->id . '" name="nb_previsionnel_rec" /></td>';
		} else {
			echo '<td><input type="text" id="date_fin_rec_' . $obj->id . '" name="date_fin_rec" value="' . date('d/m/Y', $recurrence->date_fin) . '"/></td>';
			echo '<td><input type="text" id="nb_prev_rec_' . $obj->id . '" name="nb_previsionnel_rec" value="' . $recurrence->nb_previsionnel . '"/></td>';
		}
		
		if ($action == 'add') {
			echo '<td><button class="update-recurrence" data-chargesociale="' . $obj->id . '">Ajouter</button></td>';
		} else {
			echo '<td>
				<button class="update-recurrence" data-chargesociale="' . $obj->id . '">Modifier</button>
				<button class="delete-recurrence" data-chargesociale="' . $obj->id . '">Supprimer</button>
			</td>';
		}
				
		echo '</tr>';	
	}
	echo '</tbody>';
	echo '</table>';
}

function _print_head_tab_charges_sociales() {
	echo '<thead>';
		echo '<tr class="liste_titre">';
		print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 'id');
		print_liste_field_titre('Libellé', $_SERVER['PHP_SELF'], 'libelle');
		print_liste_field_titre('Type', $_SERVER['PHP_SELF'], 'type_lib');
		print_liste_field_titre('Date', $_SERVER['PHP_SELF'], 'periode');
		print_liste_field_titre('Montant', $_SERVER['PHP_SELF'], 'amount');
		print_liste_field_titre('Récurrence', $_SERVER['PHP_SELF'], 'fk_recurrence');
		print_liste_field_titre('Date de fin', $_SERVER['PHP_SELF'], 'fk_recurrence');
		print_liste_field_titre('Nb. prévisionnel', $_SERVER['PHP_SELF'], 'fk_recurrence');
		print_liste_field_titre('Action', $_SERVER['PHP_SELF'], 'fk_recurrence');
		echo '</tr>';
	echo '</thead>';
}
