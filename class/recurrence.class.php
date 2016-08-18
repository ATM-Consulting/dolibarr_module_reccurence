<?php
dol_include_once('/compta/sociales/class/chargesociales.class.php');
dol_include_once('/recurrence/class/cronrecurrence.class.php');

class TRecurrence extends TObjetStd {
	public static $TPeriodes = array(
		'jour' 		=> 'Journalier',
		'hebdo' 	=> 'Hebdomadaire',
		'mensuel' 	=> 'Mensuel',
		'trim' 		=> 'Trimestriel',
		'annuel' 	=> 'Annuel'
	);
	
	function __construct() {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX . 'recurrence');
		
		parent::add_champs('fk_chargesociale', array('type' => 'entier', 'index' => true));
		parent::add_champs('periode', array('type' => 'text'));
		parent::add_champs('nb_previsionnel', array('type' => 'entier'));
		parent::add_champs('date_fin', array('type' => 'date'));
		parent::add_champs('montant', array('type' => 'float'));
		
		parent::_init_vars();
		parent::start();
		
		$this->lines = array();
		$this->nbLines = 0;
	}
	
	static function get_liste_periodes(&$PDOdb, $id, $name, $default = '') {
		echo '<select id="' . $id . '" name="' . $name . '">';
	
		foreach (self::$TPeriodes as $key => $periode) {
			if ($default == $key) {
				echo '<option value="' . $key . '" selected="selected">' . $periode . '</option>';
			} else {
				echo '<option value="' . $key . '">' . $periode . '</option>';
			}
		}
			
		
		echo '</select>';
	}
	
	/*
	 * Fonction permettant d'ajouter ou modifier une récurrence selon si elle existe ou non
	 */
	static function update(&$PDOdb, $id_charge, $periode, $date_fin_rec, $nb_previsionnel, $montant) {
		global $db;
		
		if (!empty($date_fin_rec) && !preg_match('/([0-9]{2}[\/-]?){2}([0-9]{4})/', $date_fin_rec))
			return false;

		if ($nb_previsionnel < 0)
			return false;
		
		$recurrence = self::get_recurrence($PDOdb, $id_charge);

		$recurrence->fk_chargesociale = $id_charge;
		$recurrence->periode 		  = $periode;
		$recurrence->nb_previsionnel  = $nb_previsionnel;
		$recurrence->montant  		  = $montant;

		if (!empty($date_fin_rec)) {
			$date = explode('/', $date_fin_rec); // $recurrence->date_fin je dirais que c'est déjà init
			$recurrence->date_fin = dol_mktime(0, 0, 0, $date[1], $date[0], $date[2]);
		} else {
			$recurrence->date_fin = null;
		}

		$recurrence->save($PDOdb);
		
		$message = 'Récurrence de la charge sociale ' . $id_charge . ' enregistrée. (' . TRecurrence::$TPeriodes[$periode] . ')';
		setEventMessage($message);
		
		$task = new TCronRecurrence($db);
		$task->run();
		
		return true;
	}
	
	static function del(&$PDOdb, $id_charge) {
		global $conf,$db,$user;
		$recurrence = self::get_recurrence($PDOdb, $id_charge);
		
		if (isset($recurrence)) {
			$message = 'Récurrence de la charge sociale ' . $id_charge . ' supprimée.';
			setEventMessage($message);
			
			//Suppression de toutes les charges sociales créé dans le futur lié à cette récurrence
			if($conf->global->RECURRENCE_DELETE_FUTUR_SOCIAL_TAXES){
				$TCharges = self::get_prochaines_charges($PDOdb, $id_charge,date('Y-m-d'));
				
				foreach($TCharges as $charge){
					$chargesocial = new ChargeSociales($db);
					$chargesocial->fetch($charge->rowid);
					$chargesocial->delete($user);
				}
			}
			
			return $recurrence->delete($PDOdb);
		} else {
			$message = 'Suppression impossible : Récurrence de la charge sociale ' . $id_charge . ' introuvable.';
			setEventMessage($message, 'errors');
			
			return false;
		}
	}
	
	/*
	 * Fonction permettant de récupérer une récurrence à partir de l'ID de la charge
	 */
	static function get_recurrence(&$PDOdb, $id_charge) {
		$recurrence = new TRecurrence;
		$recurrence->loadBy($PDOdb, $id_charge, 'fk_chargesociale');
		
		return $recurrence;
	}
	
	static function get_prochaines_charges(&$PDOdb, $id_recurrence,$dt_deb='') {
		$sql = '
			SELECT c.rowid, c.date_ech, c.libelle, c.entity, c.fk_type, c.amount, c.paye, c.periode, c.tms, c.date_creation, c.date_valid, e.fk_source
			FROM ' . MAIN_DB_PREFIX . 'chargesociales as c
			INNER JOIN ' . MAIN_DB_PREFIX . 'element_element as e ON e.fk_target = c.rowid
			WHERE e.fk_source = ' . $id_recurrence . '
			AND e.sourcetype = "chargesociales"
			AND e.targettype = "chargesociales"
			AND c.paye = 0';
		
		if($dt_deb){
			$sql .= ' AND c.periode > '.$dt_deb.' ';
		}
		
		$sql .= ' ORDER BY c.periode';
		
		$Tab = $PDOdb->ExecuteAsArray($sql);
		
		return $Tab;
	}
	
	function save(&$PDOdb){
		global $db,$user;
		
		parent::save($PDOdb);
		
		$TCharges = $this->get_prochaines_charges($PDOdb,$this->fk_chargesociale,date('Y-m-d'));

		foreach ($TCharges as $data) {
			
			$chargesociale = new ChargeSociales($db);
			$chargesociale->fetch($data->rowid);
			
			$chargesociale->amount = price2num($this->montant);
			
			echo $chargesociale->amount.'<br>';
			
			$chargesociale->update($user);
		}
	}
}
