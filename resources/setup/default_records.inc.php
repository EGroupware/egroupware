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
$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->cats_table,array('cat_parent' => 0, 'cat_owner' => categories::GLOBAL_ACCOUNT,'cat_access' => 'public','cat_appname' => 'resources','cat_name' => 'General resources','cat_description' => 'This category has been added by setup','last_mod' => time()),false,__LINE__,__FILE__);
$cat_id = $GLOBALS['egw_setup']->db->get_last_insert_id($GLOBALS['egw_setup']->cats_table,'cat_id');
$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->cats_table,array('cat_parent' => 0, 'cat_owner' => categories::GLOBAL_ACCOUNT,'cat_access' => 'public','cat_appname' => 'resources','cat_name' => 'Locations','cat_description' => 'This category has been added by setup','last_mod' => time()),false,__LINE__,__FILE__);
$locations_cat_id = $GLOBALS['egw_setup']->db->get_last_insert_id($GLOBALS['egw_setup']->cats_table,'cat_id');
config::save_value('location_cats', $locations_cat_id, 'resources');

// Give default group all rights to this general cat
$defaultgroup = $GLOBALS['egw_setup']->add_account('Default','Default','Group',False,False);
$GLOBALS['egw_setup']->add_acl('resources','run',$defaultgroup);
$GLOBALS['egw_setup']->add_acl('resources',"L$cat_id",$defaultgroup,399);
$GLOBALS['egw_setup']->add_acl('resources',"L$locations_cat_id",$defaultgroup,399);

// Add two rooms to give user an idea of what resources is...
$oProc->query("INSERT INTO {$resources_table_prefix} (name,cat_id,bookable,picture_src,accessory_of) VALUES ( 'Meeting room 1',$locations_cat_id,1,'cat_src',-1)");
$oProc->query("INSERT INTO {$resources_table_prefix} (name,cat_id,bookable,picture_src,accessory_of) VALUES ( 'Meeting room 2',$locations_cat_id,1,'cat_src',-1)");
$res_id = $oProc->m_odb->get_last_insert_id($resources_table_prefix,'res_id');
$oProc->query("INSERT INTO {$resources_table_prefix} (name,cat_id,bookable,picture_src,accessory_of) VALUES ( 'Fixed Beamer',$cat_id,0,'cat_src',$res_id)");
