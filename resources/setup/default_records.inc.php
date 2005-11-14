<?php
	/**
	 * eGroupWare - resources
	 * http://www.egroupware.org 
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package resources
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @version $Id$
	 */

	$resources_table_prefix = 'egw_resources';
	
	// Add a general category for resources
	$oProc->query("INSERT INTO {$GLOBALS['egw_setup']->cats_table} (cat_parent,cat_owner,cat_access,cat_appname,cat_name,cat_description,last_mod) VALUES (0,-1,'public','resources','General resources','This category has been added by setup',".time().")");
	$cat_id = $oProc->m_odb->get_last_insert_id($GLOBALS['egw_setup']->cats_table,'cat_id');
	
	// Give default group all rights to this general cat
	$defaultgroup = $GLOBALS['egw_setup']->add_account('Default','Default','Group',False,False);
	$GLOBALS['egw_setup']->add_acl('resources','run',$defaultgroup);
	$GLOBALS['egw_setup']->add_acl('resources',"L$cat_id",$defaultgroup,399);
	
	// Add two rooms to give user an idea of what resources is...
	$oProc->query("INSERT INTO {$resources_table_prefix} (name,cat_id,bookable,picture_src,accessory_of) VALUES ( 'Meeting room 1',$cat_id,1,'cat_src',-1)");
	$oProc->query("INSERT INTO {$resources_table_prefix} (name,cat_id,bookable,picture_src,accessory_of) VALUES ( 'Meeting room 2',$cat_id,1,'cat_src',-1)");
	$res_id = $oProc->m_odb->get_last_insert_id($resources_table_prefix,'res_id');
	$oProc->query("INSERT INTO {$resources_table_prefix} (name,cat_id,bookable,picture_src,accessory_of) VALUES ( 'Fixed Beamer',$cat_id,0,'cat_src',$res_id)");
	

