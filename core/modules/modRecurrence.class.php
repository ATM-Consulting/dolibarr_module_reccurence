<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
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
 * 	\defgroup   recurrence     Module Recurrence
 *  \brief      Example of a module descriptor.
 *				Such a file must be copied into htdocs/recurrence/core/modules directory.
 *  \file       htdocs/recurrence/core/modules/modRecurrence.class.php
 *  \ingroup    recurrence
 *  \brief      Description and activation file for module Recurrence
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';
include_once DOL_DOCUMENT_ROOT . '/cron/class/cronjob.class.php';

/**
 *  Description and activation class for module Recurrence
 */
class modRecurrence extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
        global $langs, $conf, $user;

        $this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 104412; // 104000 to 104999 for ATM CONSULTING
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'recurrence';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "ATM";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Gestion des récurrences des charges sociales";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.2.2';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='recurrence@recurrence';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /recurrence/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /recurrence/core/modules/barcode)
		// for specific css file (eg: /recurrence/css/recurrence.css.php)
		//$this->module_parts = array(
		//                        	'triggers' => 0,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
		//							'login' => 0,                                    	// Set this to 1 if module has its own login method directory (core/login)
		//							'substitutions' => 0,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
		//							'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
		//							'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (theme)
		//                        	'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
		//							'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
		//							'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
		//							'css' => array('/recurrence/css/recurrence.css.php'),	// Set this to relative path of css file if module has its own css file
	 	//							'js' => array('/recurrence/js/recurrence.js'),          // Set this to relative path of js file if module must load a js on all pages
		//							'hooks' => array('hookcontext1','hookcontext2')  	// Set here all hooks context managed by module
		//							'dir' => array('output' => 'othermodulename'),      // To force the default directories names
		//							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'! empty($conf->module1->enabled) && ! empty($conf->module2->enabled)', 'picto'=>'yourpicto@recurrence')) // Set here all workflow context managed by module
		//                        );
		$this->module_parts = array('triggers' => 1);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/recurrence/temp");
		$this->dirs = array();

		// Config pages. Put here list of php page, stored into recurrence/admin directory, to use to setup module.
		$this->config_page_url = 'recurrence_setup.php@recurrence';

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array('modTax', 'modCron');		// List of modules id that must be enabled if this module is enabled
		$this->requiredby = array();	// List of modules id to disable if this one is disabled
		$this->conflictwith = array();	// List of modules id this module is in conflict with
		$this->phpmin = array(5,0);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(3,0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("recurrence@recurrence");

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = array();

        $this->tabs = array();

        // Dictionaries
	    if (!isset($conf->recurrence->enabled))
        {
        	$conf->recurrence=new stdClass();
        	$conf->recurrence->enabled=0;
        }
		$this->dictionaries=array();

        // Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes
		// Example:
		//$this->boxes=array(array(0=>array('file'=>'myboxa.php','note'=>'','enabledbydefaulton'=>'Home'),1=>array('file'=>'myboxb.php','note'=>''),2=>array('file'=>'myboxc.php','note'=>'')););

		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r=0;

		$this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'Visualiser les charges sociales';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'all';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][5] = 'read';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

		// Add here entries to declare new menus
		//
		// Example to declare a new Top Menu entry and its Left menu entry:
		if ((float) DOL_VERSION < 7)
		{
			$this->menu[$r] = array(
				'fk_menu' => 'fk_mainmenu=accountancy,fk_leftmenu=tax',
				'type' => 'left',
				'titre' => 'Récurrence charges sociales',
				'mainmenu' => 'accountancy',
				'leftmenu' => 'tax_social',
				'url' => '/recurrence/gestion.php',
				'langs' => 'recurrence@recurrence',
				'position' => 100,
				'enabled' => '1',
				'perms' => '$user->rights->recurrence->all->read',
				'target' => '',
				'level' => 2,
				'user' => 0
			);

			$r++;
		}
		else
		{
			$this->menu[$r] = array(
				'fk_menu' => 'fk_mainmenu=billing,fk_leftmenu=tax',
				'type' => 'left',
				'titre' => 'Récurrence charges sociales',
				'mainmenu' => 'billing',
				'leftmenu' => 'tax_social',
				'url' => '/recurrence/gestion.php',
				'langs' => 'recurrence@recurrence',
				'position' => 100,
				'enabled' => '1',
				'perms' => '$user->rights->recurrence->all->read',
				'target' => '',
				'level' => 2,
				'user' => 0
			);

			$r++;
		}
	}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		global $user;

		$sql = array();

        define('INC_FROM_DOLIBARR', true);
        dol_include_once('/recurrence/config.php');
		dol_include_once('/recurrence/script/create-maj-base.php');

		$TValues = array(
			'label' => 'Mise à jour récurrence',
			'jobtype' => 'method',
			'frequency' => 1,
			'unitfrequency' => 86400,
			'status' => 1,
			'module_name' => 'recurrence',
			'classesname' => 'cronrecurrence.class.php',
			'objectname' => 'TCronRecurrence',
			'methodename' => 'run',
			'params' => '',
			'datestart' => time()
		);

		$req = "
			SELECT rowid
			FROM " . MAIN_DB_PREFIX . "cronjob
			WHERE classesname = '" . $TValues['classesname'] . "'
			AND module_name = '" . $TValues['module_name'] . "'
			AND objectname = '" . $TValues['objectname'] . "'
			AND methodename = '" . $TValues['methodename'] . "'
		";

		$res = $this->db->query($req);
		$job = $this->db->fetch_object($res);

		if (empty($job->rowid)) {
			$cronTask = new Cronjob($this->db);
			foreach ($TValues as $key => $value) {
				$cronTask->{$key} = $value;
			}

			$cronTask->create($user);
		}

		return $this->_init($sql, $options);
	}

	/**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function remove($options='')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}

}
