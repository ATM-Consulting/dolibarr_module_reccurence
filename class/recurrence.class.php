<?php
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
		parent::add_champs('derniere_application', array('type' => 'date'));
		
		parent::_init_vars();
		parent::start();
		
		$this->lines = array();
		$this->nbLines = 0;
	}
	
	static function get_liste_periodes(&$PDOdb, $id, $name, $default = '') {
		echo '<select id="' . $id . '" name="' . $name . '">';
	
		foreach (self::$TPeriodes as $key => $periode) {
			if ($default == $key)
				echo '<option value="' . $key . '" selected="selected">' . $periode . '</option>';
			else
				echo '<option value="' . $key . '">' . $periode . '</option>';
		}
			
		
		echo '</select>';
	}
	
	/*
	 * Fonction permettant d'ajouter ou modifier une récurrence selon si elle existe ou non
	 */
	static function update(&$PDOdb, $id_charge, $periode, $date_fin_rec, $nb_previsionnel) {
		
		if (!empty($date_fin_rec) && !preg_match('/([0-9]{2}[\/-]?){2}([0-9]{4})/', $date_fin_rec))
			return false;

		if ($nb_previsionnel < 0)
			return false;
		
		$recurrence = self::get_recurrence($PDOdb, $id_charge);

		$recurrence->fk_chargesociale = $id_charge;
		$recurrence->periode 		  = $periode;
		$recurrence->nb_previsionnel  = $nb_previsionnel;
		$recurrence->date_fin 		  = strtotime($date_fin_rec);
			
		$recurrence->save($PDOdb);
	
		$message = 'Récurrence de la charge sociale ' . $id_charge . ' enregistrée. (' . TRecurrence::$TPeriodes[$periode] . ')';
		setEventMessage($message);
		
		return true;
	}
	
	static function del(&$PDOdb, $id_charge) {
		$recurrence = self::get_recurrence($PDOdb, $id_charge);
		
		if (isset($recurrence)) {
			$message = 'Récurrence de la charge sociale ' . $id_charge . ' supprimée.';
			setEventMessage($message);
			
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
}
