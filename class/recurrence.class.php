<?php

dol_include_once('/compta/sociales/class/chargesociales.class.php');
dol_include_once('/recurrence/class/cronrecurrence.class.php');

class Recurrence extends SeedObject {
    
	public static $TPeriodes = array(
		'jour' 		=> 'Journalier',
		'hebdo' 	=> 'Hebdomadaire',
		'mensuel' 	=> 'Mensuel',
		'trim' 		=> 'Trimestriel',
		'annuel' 	=> 'Annuel'
	);
	
	public $element = 'recurrence';
	
	public $table_element='recurrence';
	
	function __construct($db) {
	    
	    $this->db = &$db;
	    
	    global $langs;
	    
	    $this->fields=array(
	        'fk_chargesociale'=>array('type'=>'integer','index'=>true)
	        ,'nb_previsionnel'=>array('type'=>'integer')
	        ,'date_fin'=>array('type'=>'date','index'=>true)
	        ,'montant'=>array('type'=>'double')
	        ,'periode'=>array('type'=>'string','lenght'=>20,'index'=>true)
	    );
	    
	    $this->init();
	    
	    $this->lines = array();
	    $this->nbLines = 0;
	}
	
	static function get_liste_periodes($id, $name, $default = '') {
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
	static function updateReccurence($id_charge, $periode, $date_fin_rec, $nb_previsionnel, $montant) {
	    global $db,$conf,$user,$langs;
		
	    $langs->load("recurrence@recurrence");
	    
		if (!empty($date_fin_rec) && !preg_match('/([0-9]{2}[\/-]?){2}([0-9]{4})/', $date_fin_rec))
			return false;

		if ($nb_previsionnel < 0)
			return false;
		
		$recurrence = self::get_recurrence($id_charge);

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

		$recurrence->update($user);
		
		$message = $langs->trans('EventMsgRecSave', $id_charge, Recurrence::$TPeriodes[$periode] ); // 'Récurrence de la charge sociale #' . $id_charge . ' enregistrée. (' . Recurrence::$TPeriodes[$periode] . ')'; //TODO langs !
		setEventMessage($message);
		
		$task = new CronRecurrence($db);
		$task->run($conf->entity);
		
		return true;
	}
	
	static function del($id_charge) {
		global $conf,$db,$user;
		$recurrence = self::get_recurrence( $id_charge);
		
		if (isset($recurrence)) {
			$message = 'Récurrence de la charge sociale ' . $id_charge . ' supprimée.';
			setEventMessage($message);
			
			//Suppression de toutes les charges sociales créé dans le futur lié à cette récurrence
			if($conf->global->RECURRENCE_DELETE_FUTUR_SOCIAL_TAXES){
				$TCharges = self::get_prochaines_charges($id_charge,date('Y-m-d'));
				
				foreach($TCharges as $charge){
					$chargesocial = new ChargeSociales($db);
					$chargesocial->fetch($charge->rowid);
					$chargesocial->delete($user);
				}
			}
			
			return $recurrence->delete($user);
		} else {
			$message = 'Suppression impossible : Récurrence de la charge sociale ' . $id_charge . ' introuvable.';
			setEventMessage($message, 'errors');
			
			return false;
		}
	}
	
	/*
	 * Fonction permettant de récupérer une récurrence à partir de l'ID de la charge
	 */
	static function get_recurrence($id_charge) {
	    
	    global $db;
	    
		$recurrence = new Recurrence($db);
		$recurrence->fetchBy($id_charge, 'fk_chargesociale');
		
		return $recurrence;
		
	}
	
	static function get_prochaines_charges( $id_recurrence,$dt_deb='') {
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
		
		global $db;
		
		$res = $db->query($sql);
		if(!$res) return false;
		
		$Tab=array();
		while($obj = $db->fetch_object($res)) {
		    $Tab[] = $obj;
		}
		
		return $Tab;
	}
	
	function update(User &$user, $notrigger = false){
		global $db;
		
		parent::update($user, $notrigger);
		
		$TCharges = $this->get_prochaines_charges($this->fk_chargesociale,date('Y-m-d'));

		foreach ($TCharges as $data) {
			
			$chargesociale = new ChargeSociales($db);
			$chargesociale->fetch($data->rowid);
			
			$chargesociale->amount = price2num($this->montant);
			
			//echo $chargesociale->amount.'<br>';//TODO useless ?
			
			$chargesociale->update($user);
		}
	}
}
