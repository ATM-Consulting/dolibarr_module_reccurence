<?php
require_once 'config.php';
dol_include_once('/compta/sociales/class/chargesociales.class.php');
dol_include_once('/recurrence/class/recurrence.class.php');

$langs->load('recurrence@recurrence');

$PDOdb = new TPDOdb;

$action = __get('action', 'view');

$page   = __get('page', 0);
if ($page < 0) $page = 0;

$limit  = $conf->liste_limit;
$offset = $limit * $page;

if (!$user->rights->recurrence->all->read) {
	accessforbidden();	
}

/*
 * HEADER
 */ 
llxHeader('', 'Récurrence des charges sociales');

print_fiche_titre('Récurrence des charges sociales', '', 'report.png@report');

print dol_get_fiche_head(array(
	array(dol_buildpath('/recurrence/gestion.php?action=view', 1), 'Liste des récurrences', 'view'),
	array(dol_buildpath('/recurrence/gestion.php?action=add', 1), 'Enregistrer une tâche récurrente', 'add')
)  , $action, '');

echo '<form method="POST" action="paiement.php">'; // Formulaire pour la gestion des paiements

_liste_charges_sociales($PDOdb, $action, $page, $limit, $offset);

if ($user->rights->tax->charges->creer) {
	if ($action == 'add') {
		if ($user->rights->tax->charges->creer) {
			echo '<div class="tabsAction">';
			
			$urlPage = 'charges.php';
			if(version_compare(DOL_VERSION, '5.0', '>=')) {
				$urlPage = 'card.php';
			}
			echo '
			<div class="inline-block divButAction">
				<a class="butAction" href="' . DOL_URL_ROOT . '/compta/sociales/'.$urlPage.'?leftmenu=tax_social&action=create">
					Ajouter une charge sociale
				</a>
			</div>';
			
			echo '</div>';
		}
	} else {
		echo '<div class="tabsAction">';
			echo '
			<div class="inline-block divButAction">
				<input type="submit" class="butAction" value="Payer les récurrences sélectionnées" />
			</div>';
			
			echo '<div class="inline-block divButAction"><a class="butAction" href="gestion.php?action=add">Ajouter une récurrence</a></div>';
		echo '</div>';
	}
}

echo '</form>';

echo '<div style="clear: both;"></div>';

llxfooter();
?>

<script>
	$(document).ready(function() {
		$(".date").datepicker({
			dateFormat: 'dd/mm/yy',
			defaultDate: null
		}).val();
	
		$('.update-recurrence, .delete-recurrence').click(function(e) {
			e.preventDefault();
			
			var type 		 = $(this).attr('class');
			var id_charge 	 = $(this).data('chargesociale');
			var periode		 = $('#periode_' + id_charge + ' option:selected').val();
			var date_fin_rec = $('#date_fin_rec_' + id_charge).datepicker().val();
			var nb_prev_rec  = $('#nb_prev_rec_' + id_charge).val();
			var montant  	 = $('#montant_' + id_charge).val();
			
			$.ajax({
				type: 'POST',
				url: 'script/update-recurrence.php',
				data: { 
					type: type, 
					id_charge: id_charge, 
					periode: periode, 
					date_fin_rec: date_fin_rec, 
					nb_prev_rec: nb_prev_rec,
					montant: montant
				}
			}).done(function(data) {
				document.location.reload(true);
			});
		});
	});
</script>

<?php

/*
 * Liste des charges sociales
 */
function _liste_charges_sociales(&$PDOdb, $action, $page, $limit, $offset) {
	global $conf, $db, $user, $bc;
	
	$charge_sociale = new ChargeSociales($PDOdb);
	
	$sql = "
		SELECT cs.rowid as id, cs.fk_type as type, cs.amount, cs.date_ech, cs.libelle, cs.paye, cs.periode, c.libelle as type_lib, SUM(pc.amount) as alreadypayed, 
		(SELECT r.montant FROM " . MAIN_DB_PREFIX . "recurrence as r WHERE r.fk_chargesociale = cs.rowid) montant_reccur
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
	print_barre_liste('Liste', $page, $_SERVER["PHP_SELF"], $param, '', '', '', $num, $limit + 1);
	
	$form = new TFormCore($db);
	
	echo '<table class="noborder" width="100%">';
	_print_head_tab_charges_sociales($action);

	$var = true;
	
	echo '<tbody>';

	foreach ($result as $obj) {
		$var = !$var;
		
		$charge_sociale->id  = $obj->id;
		$charge_sociale->lib = $obj->id;
		$charge_sociale->ref = $obj->id;
	
		if ($action != 'add') {
			$recurrence = TRecurrence::get_recurrence($PDOdb, $charge_sociale->id);
		}
		
		$TNextCharges = TRecurrence::get_prochaines_charges($PDOdb, $charge_sociale->id);
		
		echo '<tr ' . $bc[$var] . '>';
		if ($action != 'add') {
			echo '<td><input type="checkbox" name="recurrences[]" value="' . $charge_sociale->id . '" style="margin: 0 0 0 4px;" /></td>';
		} else {
			echo '<td></td>';
		}
		echo '<td>' . $charge_sociale->getNomUrl(1,'20') . '</td>';
		echo '<td>' . utf8_encode($obj->libelle) . '</td>';
		echo '<td>' . utf8_encode($obj->type_lib) . '</td>'; // Type
		echo '<td>' . dol_print_date($obj->periode, 'day') . '</td>';
		
		if ($action != 'add') {
			// Affiche la date de la prochaine charge créée à partir de cette récurrence
			if (!empty($TNextCharges)) {
				echo '<td>' . dol_print_date($TNextCharges[0]->periode, 'day') . '</td>';
			} else {
				echo '<td></td>';
			}
		}
		
		echo '<td><input type="text" id="montant_' . $obj->id . '" name="montant" value="'.(($obj->montant_reccur > 0) ? $obj->montant_reccur : $obj->amount).'" /></td>';
		
		echo '<td>';
		if ($action == 'add') {
			TRecurrence::get_liste_periodes($PDOdb, 'periode_' . $obj->id, 'fk_periode', 'mensuel');
		} else {
			TRecurrence::get_liste_periodes($PDOdb, 'periode_' . $obj->id, 'fk_periode', $recurrence->periode);
		}
		
		echo '</td>';
		
		if ($action == 'add') {
			echo '<td><input type="text" class="date" id="date_fin_rec_' . $obj->id . '" name="date_fin_rec" /></td>';
			echo '<td><input type="text" id="nb_prev_rec_' . $obj->id . '" name="nb_previsionnel_rec" /></td>';
		} else {
			$date = '';
			
			if ($recurrence->date_fin > 0)
				$date = date('d/m/Y', $recurrence->date_fin);
			
			echo '<td><input type="text" class="date" id="date_fin_rec_' . $obj->id . '" name="date_fin_rec" value="' . $date . '"/></td>';
			echo '<td><input type="text" id="nb_prev_rec_' . $obj->id . '" name="nb_previsionnel_rec" value="' . $recurrence->nb_previsionnel . '"/></td>';
		}

		if ($user->rights->tax->charges->creer) {
			if ($action == 'add') {
				echo '<td><button class="update-recurrence" data-chargesociale="' . $obj->id . '" style="margin: 2px 4px; padding: 2px;">Ajouter</button></td>';
			} else {
				echo '<td>
					<button class="update-recurrence" data-chargesociale="' . $obj->id . '" style="margin: 2px 4px; padding: 2px;">Modifier</button>
					<button class="delete-recurrence" data-chargesociale="' . $obj->id . '" style="margin: 2px 4px; padding: 2px;">Supprimer</button>
				</td>';
			}
		} else {
			echo '<td>Droits requis</td>';
		}
				
		echo '</tr>';	
	}

	if (count($result) <= 0) {
		echo '<tr>';
		if ($action == 'add') {
			echo '<td style="text-align: center;" colspan="9">Aucune récurrence enregistrée. (<a href="gestion.php?action=add">Créer une récurrence</a>)</td>';
		} else {
			echo '<td style="text-align: center;" colspan="10">Aucune récurrence enregistrée. (<a href="gestion.php?action=add">Créer une récurrence</a>)</td>';
		}
		echo '</tr>';	
	}
	
	echo '</tbody>';
	echo '</table>';
}

function _print_head_tab_charges_sociales($action) {
	echo '<thead>';
		echo '<tr class="liste_titre">';
		echo '<th class="liste_titre"></th>';
		print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 'id');
		print_liste_field_titre('Libellé', $_SERVER['PHP_SELF'], 'libelle');
		print_liste_field_titre('Type', $_SERVER['PHP_SELF'], 'type_lib');
		print_liste_field_titre('Date', $_SERVER['PHP_SELF'], 'periode');
		
		if ($action != 'add') {
			print_liste_field_titre('Prochaine charge à payer', $_SERVER['PHP_SELF'], 'periode');
		}
		
		print_liste_field_titre('Montant', $_SERVER['PHP_SELF'], 'amount');
		print_liste_field_titre('Récurrence', $_SERVER['PHP_SELF'], 'fk_recurrence');
		print_liste_field_titre('Date de fin', $_SERVER['PHP_SELF'], 'fk_recurrence');
		print_liste_field_titre('Nb. prévisionnel', $_SERVER['PHP_SELF'], 'fk_recurrence');
		print_liste_field_titre('Action', $_SERVER['PHP_SELF'], 'fk_recurrence');
		echo '</tr>';
	echo '</thead>';
}
