<?php
/* Copyright (C) 2004-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	    \file       htdocs/compta/paiement_charge.php
 *		\ingroup    tax
 *		\brief      Page to add payment of a tax
 */

require_once 'config.php';
require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/chargesociales.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/paymentsocialcontribution.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

$langs->load('recurrence@recurrence');

$id_charge = GETPOST("id");
$action = GETPOST('action');
$amounts = array();

$TRecurrences = GETPOST('recurrences'); // Tableau des récurrences à payer

// Security check
$socid=0;
if ($user->societe_id > 0)
{
	$socid = $user->societe_id;
}

$cancel = GETPOST('cancel');
if (!empty($cancel)) {
	header('Location: gestion.php');
	exit;
}

/*
 * Actions
 */

if ($action == 'add_payment')
{
	$error=0;
	
	if ($_POST["cancel"])
	{
		$loc = DOL_URL_ROOT.'/compta/sociales/charges.php?id='.$chid;
		header("Location: ".$loc);
		exit;
	}

	$datepaye = dol_mktime(12, 0, 0, $_POST["remonth"], $_POST["reday"], $_POST["reyear"]);

	if (! $_POST["paiementtype"] > 0)
	{
		$mesg = $langs->trans("ErrorFieldRequired",$langs->transnoentities("PaymentMode"));
		$error++;
	}
	
	if ($datepaye == '')
	{
		$mesg = $langs->trans("ErrorFieldRequired",$langs->transnoentities("Date"));
		$error++;
	}
	
    if (! empty($conf->banque->enabled) && ! $_POST["accountid"] > 0)
    {
        $mesg = $langs->trans("ErrorFieldRequired",$langs->transnoentities("AccountToCredit"));
        $error++;
    }

	if (! $error)
	{
		$paymentid = 0;

		// Read possible payments
		foreach ($_POST as $key => $value)
		{
			if (substr($key,0,7) == 'amount_')
			{
				$other_chid = substr($key,7);
				$amounts[$other_chid] = price2num($_POST[$key]);
			}
		}
		
		//var_dump($amounts, $_REQUEST); exit;
        if (count($amounts) <= 0)
        {
            $error++;
            $errmsg='ErrorNoPaymentDefined';
        }

        if (! $error)
        {
    		$db->begin();
			
			$TSelected_charges = GETPOST('selected_charges');
    		if (!empty($TSelected_charges)) {
    			foreach ($TSelected_charges as $id) {
    				$charge = new ChargeSociales($db);
					$charge->fetch($id);
					
    				$amount = array();
					
					// Récupére uniquement le paiement concernant la charge
    				$amount[$id] = $amounts[$id];

    				// Create a line of payments
		    		$paiement = new PaymentSocialContribution($db);
		    		$paiement->chid         = $id;
		    		$paiement->datepaye     = $datepaye;
		    		$paiement->amounts      = $amount;   // Tableau de montant
		    		$paiement->paiementtype = $_POST["paiementtype"];
		    		$paiement->num_paiement = $_POST["num_paiement"];
		    		$paiement->note         = $_POST["note"];
		
		    		if (! $error)
		    		{
		    		    $paymentid = $paiement->create($user);
		                if ($paymentid < 0)
		                {
		                    $errmsg=$paiement->error;
		                    $error++;
		                }
		    		}
		
		            if (! $error)
		            {
		                $result=$paiement->addPaymentToBank($user,'payment_sc','(SocialContributionPayment)',$_POST['accountid'],'','');
						
						if ($charge->amount == $amount[$id]) {
							$charge->set_paid($user);
						}
						
		                if (! $result > 0)
		                {
		                    $errmsg=$paiement->error;
		                    $error++;
		                }
		            }
    			}

				if (! $error) {
	                $db->commit();
	                if((float) DOL_VERSION < 9.0) $loc = DOL_URL_ROOT.'/compta/sociales/index.php?leftmenu=tax_social';
	                else $loc = DOL_URL_ROOT.'/compta/sociales/list.php?leftmenu=tax_social';
	                header('Location: '.$loc);
	                exit;
	            } else {
	                $db->rollback();
	            }
    		}
        }
	}

	$_GET["action"]='create';
} else if (empty($TRecurrences)) {
	$message = 'Veuillez sélectionner au moins une récurrence à payer.';
	setEventMessage($message, 'errors');
			
	header('Location: gestion.php');
	exit;
}


/*
 * View
 */

llxHeader();

$form=new Form($db);

// Formulaire de creation d'un paiement de charge
if (!empty($TRecurrences)) {
	$recurrences = implode(',', $TRecurrences); // Récupération des récurrences pour requête
	
	print_fiche_titre($langs->trans("DoPayment"));
	print "<br>\n";

	if ($mesg) {
		print "<div class=\"error\">$mesg</div>";
	}
	
	print '<form name="add_payment" action="'.$_SERVER['PHP_SELF'].'" method="post">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="id" value="'.$charge_recurrente->id.'">';
	print '<input type="hidden" name="chid" value="'.$charge_recurrente->id.'">';
	print '<input type="hidden" name="action" value="add_payment">';
	
	print '<table cellspacing="0" class="border" width="100%" cellpadding="2">';

	print '<tr class="liste_titre">';
	print '<td colspan="4">'.$langs->trans("Payment").'</td>';
	print '</tr>';
	
	print '<tr><td class="fieldrequired">'.$langs->trans("Date").'</td><td colspan="3">';
	$datepaye = dol_mktime(12, 0, 0, $_POST["remonth"], $_POST["reday"], $_POST["reyear"]);
	$datepayment=empty($conf->global->MAIN_AUTOFILL_DATE)?(empty($_POST["remonth"])?-1:$datepaye):0;
	$form->select_date($datepayment,'','','','',"add_payment",1,1);
	print "</td>";
	print '</tr>';

	print '<tr><td class="fieldrequired">'.$langs->trans("PaymentMode").'</td><td colspan="3">';
	$form->select_types_paiements(isset($_POST["paiementtype"])?$_POST["paiementtype"]:$charge->paiementtype, "paiementtype");
	print "</td>\n";
	print '</tr>';

	print '<tr>';
	print '<td class="fieldrequired">'.$langs->trans('AccountToDebit').'</td>';
	print '<td colspan="3">';
	$form->select_comptes(isset($_POST["accountid"])?$_POST["accountid"]:$charge->accountid, "accountid", 0, '',1);  // Show opend bank account list
	print '</td></tr>';

	// Number
	print '<tr><td>'.$langs->trans('Numero');
	print ' <em>('.$langs->trans("ChequeOrTransferNumber").')</em>';
	print '</td>';
	print '<td colspan="3"><input name="num_paiement" type="text" value="'.GETPOST('num_paiement').'"></td></tr>'."\n";

	print '<tr>';
	print '<td valign="top">'.$langs->trans("Comments").'</td>';
	print '<td valign="top" colspan="3"><textarea name="note" wrap="soft" cols="60" rows="'.ROWS_3.'"></textarea></td>';
	print '</tr>';

	print '</table>';

	print '<br>';
	
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';

	print '<td><input id="checkToggle" type="checkbox"  /> Payer</td>';
	print '<td>Libellé</td>';
	print '<td>Date</td>';
	print '<td>Montant</td>';
	print '<td>Déjà réglé</td>';
	print '<td>Reste à payer</td>';
	print '<td>Montant paiement</td>';
	print "</tr>\n";

	// Récupération de la charge sociale sur laquelle la récurrence a été placée
	$charge_recurrente = new ChargeSociales($db);
	$charge_recurrente->fetch($id_recurrence);

	$price = $charge_recurrente->amount;
	
	// Récupération des charges créées à partir de celle là et non payée
	$sql = '
		SELECT c.rowid, e.fk_source
		FROM ' . MAIN_DB_PREFIX . 'chargesociales as c
		INNER JOIN ' . MAIN_DB_PREFIX . 'element_element as e ON e.fk_target = c.rowid
		WHERE e.fk_source IN (' . $recurrences . ')
		AND e.sourcetype = "chargesociales"
		AND e.targettype = "chargesociales"
		AND c.paye = 0
		ORDER BY c.periode
	';
	var_dump($sql);
	$res = $db->query($sql);

	/*
 	 * Autres charges impayees
	 */
	$num = 1;
	$i = 0;

	$var=True;
	$total=0;
	$totalrecu=0;
	
	$TPreChecked = array();
	while($c=$db->fetch_object($res)) {
		$charge = new ChargeSociales($db);
		$charge->fetch($c->rowid);

		$var=!$var;

		print "<tr ".$bc[$var].">";
		
		if (!in_array($c->fk_source, $TPreChecked)) {
			print '<td><input class="targetCheckToggle" type="checkbox" name="selected_charges[]" value="' . $charge->id . '" checked /></td>';
			$TPreChecked[] = $c->fk_source;	
		} else {
			print '<td><input class="targetCheckToggle" type="checkbox" name="selected_charges[]" value="' . $charge->id . '" /></td>';
		}
		
		print '<td>' . $charge->getNomUrl(1) . ' - ' . htmlentities($charge->lib) . '</td>';
		print '<td>' . dol_print_date($charge->periode, 'day') . '</td>';
		print '<td>' . price($charge->amount, 2) . '</td>';
		
		$sql = "SELECT sum(p.amount) as total";
		$sql.= "FROM ".MAIN_DB_PREFIX."paiementcharge as p";
		$sql.= "WHERE p.fk_charge = ".$charge->id;
		$resql = $db->query($sql);
		
		if ($resql) {
			$obj=$db->fetch_object($resql);
			$sumpaid = $obj->total;
			$db->free();
		}
	
		print '<td>' . price($sumpaid, 2) . '</td>';
		print '<td>' . price($charge->amount - $sumpaid, 2) . '</td>';
		
		$namef = "amount_".$charge->id;
		print '<td><input type="text" size="8" name="'.$namef.'" value="' . price($charge->amount) . '"></td>';
		
		/*
		print '<td align="right">'.price($sumpaid)."</td>";

		print '<td align="right">'.price($objp->amount - $sumpaid)."</td>";
		*/
		
		print '</tr>';
	}
	
	if (empty($TPreChecked)) {
		print "<tr ".$bc[$var].">";
		print '<td colspan="7" style="text-align: center;">Aucune charge impayées.</td>';
		print '</tr>';
	}
	
	print "</table>";
	
	print '<br><center>';

	if (!empty($TPreChecked)) {
		print '<input type="submit" class="butAction" name="save" value="'.$langs->trans("Save").'">';
		print '&nbsp; &nbsp;';
	}
	
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?cancel=1" >'.$langs->trans("Cancel").'</a>';

	print '</center>';

	print "</form>\n";
	
	print '<script >$( document ).ready(function() { ';
    print ' $("[name=\'re\']").prop("required",true);  $("#selectpaiementtype").prop("required",true); $("#selectaccountid").prop("required",true); ';
    print ' $("#checkToggle").click(function() { ';
    print '     var checkBoxes = $(".targetCheckToggle"); ';
    print '     checkBoxes.prop("checked", this.checked); ';
    print ' }); ';
    print '});</script>';
	
	
}


$db->close();

llxFooter();
