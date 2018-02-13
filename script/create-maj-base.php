<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */
 
if(!defined('INC_FROM_DOLIBARR')) {
    define('INC_FROM_CRON_SCRIPT', true);
    
    require '../config.php';
    
    
    
}

global $db;

dol_include_once('/recurrence/class/recurrence.class.php');

$o = new Recurrence($db);
$o->init_db_by_vars();

$db->query("ALTER TABLE ".MAIN_DB_PREFIX."recurrence CHANGE rowid rowid int(11) NOT NULL AUTO_INCREMENT FIRST");

$db->query("UPDATE".MAIN_DB_PREFIX."recurrence SET date_creation=date_cre,tms=date_maj WHERE date_creation IS NULL");
