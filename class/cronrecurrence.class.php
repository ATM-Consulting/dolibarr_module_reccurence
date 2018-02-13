<?php
require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/chargesociales.class.php';

class CronRecurrence {
	public $db;
	
	function __construct(&$db) {
		$this->db = $db;
	}
	
	// TODO WARNING si véritable cron : voir methode create_charge_sociale()
	function run($entity=0) {
	    
		// Récupération de la liste des charges récurrentes
		$sql = "
			SELECT r.rowid, r.fk_chargesociale, r.periode, r.nb_previsionnel, r.date_fin
			FROM " . MAIN_DB_PREFIX . "recurrence r
		";
		
		if ($entity > 0) $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'chargesociales cs ON (cs.rowid = r.fk_chargesociale AND cs.entity = '.$entity.')';
		
		$res = $this->db->query($sql);
		
		$TRecurrences = array();
		while ($rec = $this->db->fetch_object($res)) {
			$TRecurrences[] = $rec;
		}
		
		foreach ($TRecurrences as $recurrence) {
			// Récupération de la dernière charge sociale créée
			
			$lastCharge = null;
			
			$sql = '
				SELECT e.rowid, e.fk_source, e.sourcetype, e.fk_target, e.targettype, (
					SELECT COUNT(*) 
					FROM ' . MAIN_DB_PREFIX . 'element_element
					INNER JOIN ' . MAIN_DB_PREFIX . 'chargesociales as cs ON cs.rowid = fk_target
					WHERE fk_source = ' . $recurrence->fk_chargesociale . ' 
					AND sourcetype = "chargesociales" 
					AND targettype = "chargesociales"
					AND cs.periode > CURDATE()
				) as nb_charges_futur
				FROM ' . MAIN_DB_PREFIX . 'element_element as e
				INNER JOIN ' . MAIN_DB_PREFIX . 'chargesociales as cs ON cs.rowid = e.fk_target
				WHERE fk_source = ' . $recurrence->fk_chargesociale . '
				AND sourcetype = "chargesociales"
				AND cs.periode > CURDATE()
				GROUP BY fk_source, sourcetype, fk_target, targettype
				ORDER BY cs.periode DESC
				LIMIT 1;
			';
			
			//$message = $sql;
			//setEventMessage($message);
			
			$res = $this->db->query($sql);	
			$result = $this->db->fetch_object($res);
			
			$nb_charges_futur = 0;

			if (!empty($result)) {
				// On récupére les infos de la précédente charge sociale créée
				$lastCharge = new ChargeSociales($this->db);
				$lastCharge->fetch($result->fk_target);
				
				$nb_charges_futur = $result->nb_charges_futur;
			}
			
			// Récupérer les informations de la charge sociale source
			if (empty($lastCharge)) {
				$lastCharge = new ChargeSociales($this->db);
				$lastCharge->fetch($recurrence->fk_chargesociale);
				
				$nb_charges_futur = 0;
			}
			
			// Récurrences à ajouter pour correspondre au nombre previsionnel
			$nb_ajouts = $recurrence->nb_previsionnel - $nb_charges_futur;
			
			//$message = $recurrence->rowid . ' => ' . $recurrence->nb_previsionnel . ' : ' . $nb_ajouts . ' à ajouter (' . $nb_charges_futur .' présents)';
			//setEventMessage($message);
				
			if ($nb_ajouts < 0) $nb_ajouts = 0;
					
			$last_date = new DateTime(date('Y-m-d', $lastCharge->periode));
			$current_date = new DateTime(date('Y-m-d'));
			
			// Récupére la différence entre la date de la dernière charge créée et la date actuelle
			$diff = $current_date->diff($last_date);
			
			$date_fin_recurrence = strtotime($recurrence->date_fin);
			
			// Si la date de fin de la récurrence n'a pas été dépassée
			if ($date_fin_recurrence < 0 || strtotime('now') < $date_fin_recurrence) {
				switch ($recurrence->periode) {
					case 'jour': // JOURNALIER
					
						// Si un nombre prévisionnel n'a pas été défini, les charges sont créées au jour le jour
						if ($recurrence->nb_previsionnel == 0 && $diff->days >= 1 && $lastCharge->periode < strtotime('now')) {
							$id = $this->create_charge_sociale($recurrence->fk_chargesociale, time());
							$lastCharge->fetch($id);
                            $nb_ajouts--;
						} else if ($nb_charges_futur < $recurrence->nb_previsionnel) {
							// Création des charges sociales supplémentaires 
							// si le nombre de charges créées et inférieur au nombre prévisionnel
							
							$nb_jours = 1;
							
							while ($nb_ajouts > 0) {
								// A partir de la dernière charge créée, on prend date + 1, date + 2, ..., date + n
								$date = strtotime(date('Y-m-d', $lastCharge->periode) . ' +' . $nb_jours . 'days');
								
								if ($date_fin_recurrence > 0 && $date > $date_fin_recurrence)
									break;
								
								$id = $this->create_charge_sociale($recurrence->fk_chargesociale, $date);
								
								$nb_jours++;
								$nb_ajouts--;
							}
						}						
						break;
					case 'hebdo': // HEBDOMADAIRE
					
						// Différence >= 7 jours
						if ($recurrence->nb_previsionnel == 0 && $diff->days >= 7 && $lastCharge->periode < strtotime('now')) {
							$id = $this->create_charge_sociale($recurrence->fk_chargesociale, time());
							$lastCharge->fetch($id);
							$nb_ajouts--;
						} else if ($nb_charges_futur < $recurrence->nb_previsionnel) {
							$nb_semaines = 1;
							
							while ($nb_ajouts > 0) {
								$date = strtotime(date('Y-m-d', $lastCharge->periode) . '+' . $nb_semaines . 'week');
								
								if ($date_fin_recurrence > 0 && $date > $date_fin_recurrence)
									break;
								
								$id = $this->create_charge_sociale($recurrence->fk_chargesociale, $date);
								
								$nb_semaines++;
								$nb_ajouts--;
							}
						}
						break;
					case 'mensuel': // MENSUEL
						// Différence >= 1 mois
						if ($recurrence->nb_previsionnel == 0 && $diff->m >= 1 && $lastCharge->periode < strtotime('now')) {
							$id = $this->create_charge_sociale($recurrence->fk_chargesociale, time());
							$lastCharge->fetch($id);
							$nb_ajouts--;
						} else if ($nb_charges_futur < $recurrence->nb_previsionnel) {
							$nb_mois = 1;
							
							while ($nb_ajouts > 0) {
								$date_charge = date('Y-m-d', $lastCharge->periode);
								$month = intval(date('m', $lastCharge->periode));
								$day = intval(date('d', $lastCharge->periode));
								$year = intval(date('Y', $lastCharge->periode));
								
								$first_day_date = $year . '-' . $month . '-01';
								$last_day_of_next_month = intval(date('t', strtotime($first_day_date . ' +' . $nb_mois . 'month')));
								
								// Récupération dernier jour du mois si, pour le mois concerné, le jour n'existe pas
								if ($day > $last_day_of_next_month) {
									$date = date('Y-m-t', strtotime($first_day_date . ' +' . $nb_mois .  'month'));
								} else {
									$date = date('Y-m-d', strtotime($date_charge . '+' . $nb_mois . 'month'));
								}

								$date = strtotime($date);
								
								if ($date_fin_recurrence > 0 && $date > $date_fin_recurrence)
									break;

								$id = $this->create_charge_sociale($recurrence->fk_chargesociale, $date);
								
								
								$nb_mois++;
								$nb_ajouts--;
							}
						}
						break;
					case 'trim':
						// Différence >= 3 mois
						if ($recurrence->nb_previsionnel == 0 && $diff->m >= 3 && $lastCharge->periode < strtotime('now')) {
							$id = $this->create_charge_sociale($recurrence->fk_chargesociale, time());
							$lastCharge->fetch($id);
                            $nb_ajouts--;
						} else if ($nb_charges_futur < $recurrence->nb_previsionnel) {
							$nb_trimestres = 1;
							
							while ($nb_ajouts > 0) {
								$date = strtotime(date('Y-m-d', $lastCharge->periode) . '+' . ($nb_trimestres * 3) . 'month');							
								
								if ($date_fin_recurrence > 0 && $date > $date_fin_recurrence)
									break;
								
								$id = $this->create_charge_sociale($recurrence->fk_chargesociale, $date);
								
								$nb_trimestres++;
								$nb_ajouts--;
							}
						}
						break;
					case 'annuel':
						// Différence >= 1 an
						if ($recurrence->nb_previsionnel == 0 && $diff->y >= 1 && $lastCharge->periode < strtotime('now')) {
							$id = $this->create_charge_sociale($recurrence->fk_chargesociale, time());
							$lastCharge->fetch($id);
                            $nb_ajouts--;
						} else if ($nb_charges_futur < $recurrence->nb_previsionnel) {
							$nb_annees = 1;
							
							while ($nb_ajouts > 0) {
								$date = strtotime(date('Y-m-d', $lastCharge->periode) . '+' . $nb_annees . 'year');
								
								if ($date_fin_recurrence > 0 && $date > $date_fin_recurrence)
									break;
								
								$id = $this->create_charge_sociale($recurrence->fk_chargesociale, $date);
								
								$nb_annees++;
								$nb_ajouts--;
							}
						}
						break;
					default:
				}	
			}
		}

		return true;
	}
	
	function create_charge_sociale($id_source, $date) {
		global $user;
		
		// Récupération de la charge sociale initiale
		$obj = new ChargeSociales($this->db);
		$obj->fetch($id_source);
		
		if (empty($obj->id)) {
			return false;
		} else {
			// Création de la nouvelle charge sociale
			$chargesociale = new ChargeSociales($this->db);
			$chargesociale->type = $obj->type;
			$chargesociale->lib = $obj->lib;
			$chargesociale->date_ech = $date;
			$chargesociale->periode = $date;
			$chargesociale->amount = $obj->amount;
	
			// TODO WARNING : si une tache cron fait appel à une routine pour créer les charges, il va y avoir un problème d'entité si multicompany et plusieurs charges sur plusieurs entités
			$id = $chargesociale->create($user);
					
			$chargesociale->add_object_linked('chargesociales', $id_source);
						
			return $id;
		}
	}
}
