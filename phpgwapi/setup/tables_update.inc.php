<?php
/**
 * EGroupware - API Setup
 *
 * Update scripts 1.8 --> 2.0
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/* Include older eGroupWare update support */
include('tables_update_0_9_9.inc.php');
include('tables_update_0_9_10.inc.php');
include('tables_update_0_9_12.inc.php');
include('tables_update_0_9_14.inc.php');
include('tables_update_1_0.inc.php');
include('tables_update_1_2.inc.php');
include('tables_update_1_4.inc.php');
include('tables_update_1_6.inc.php');

/**
 * Update from the stable 1.8 branch to the new devel branch 1.9.xxx
 */
function phpgwapi_upgrade1_8_001()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.001';
}

/**
 * Add index to improve import of contacts using a custom field as primary key
 * 
 * @return string
 */
function phpgwapi_upgrade1_9_001()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook_extra',
		array('contact_name','contact_value(32)'));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.002';
}

function phpgwapi_upgrade1_9_002()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_links','deleted',array(
		'type' => 'timestamp'
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_links',array(
		'fd' => array(
			'link_id' => array('type' => 'auto','nullable' => False),
			'link_app1' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_remark' => array('type' => 'varchar','precision' => '100'),
			'link_lastmod' => array('type' => 'int','precision' => '8','nullable' => False),
			'link_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'deleted' => array('type' => 'timestamp')
		),
		'pk' => array('link_id'),
		'fk' => array(),
		'ix' => array('deleted',array('link_app1','link_id1','link_lastmod'),array('link_app2','link_id2','link_lastmod')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.003';
}

