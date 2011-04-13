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


function phpgwapi_upgrade1_9_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook','adr_one_countrycode',array(
		'type' => 'varchar',
		'precision' => '2'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook','adr_two_countrycode',array(
		'type' => 'varchar',
		'precision' => '2'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.004';
}


/**
 * Update script to populate country codes
 *
 * Sets country code for any recognized country in any installed language, then clears the country name
 * to avoid conflicts / confusion.
 */
function phpgwapi_upgrade1_9_004()
{
	// Get all installed translations for names
	$country = new country();
	$country_query = 'SELECT DISTINCT message_id, content
		FROM ' . translation::LANG_TABLE . '
		WHERE message_id IN ("' . implode('","', array_values($country->countries())) . '")
		ORDER BY message_id';
	$result = $GLOBALS['egw_setup']->oProc->query($country_query, __LINE__, __FILE__);

	$country_list = array();
	$current_name = null;
	$id = null;
	foreach($result as $row) {
		if($row['message_id'] != $current_name) {
			$current_name = $row['message_id'];
			$id = array_search(strtoupper($current_name), $country->countries());
			if(!$id) continue;
		}
		$country_list[$id][] = $row['content'];
	}

	// Build conversion
	$case = 'CASE UPPER(adr_%1$s_countryname)';
	foreach($country_list as $key => $names) {
		foreach($names as $name) {
			$case .= "\n" . "WHEN UPPER(\"$name\") THEN '$key'";
		}
	}
	$case .= ' END';

	$sql = 'UPDATE egw_addressbook SET ';
	$sql .= "adr_one_countrycode = (" . sprintf($case, 'one') . '),';
	$sql .= "adr_two_countrycode = (" . sprintf($case, 'two') . ')';

	// Change names
	$GLOBALS['egw_setup']->oProc->query($sql,__LINE__,__FILE__);

	// Clear text names
	$GLOBALS['egw_setup']->oProc->query('UPDATE egw_addressbook SET adr_one_countryname = NULL WHERE adr_one_countrycode IS NOT NULL',__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->query('UPDATE egw_addressbook SET adr_two_countryname = NULL WHERE adr_two_countrycode IS NOT NULL',__LINE__,__FILE__);
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.005';
}


/**
 * Add index to li (login time) column to speed up maintenance (periodic delete of old rows)
 *
 * Delete some obsolete / since a long time not used tables:
 * - egw_vfs (replaced by egw_sqlfs in 1.6)
 * - egw_(app_)sessions (not used since 1.4)
 */
function phpgwapi_upgrade1_9_005()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_access_log','li');

	$GLOBALS['egw_setup']->oProc->DropTable('egw_app_sessions');
	$GLOBALS['egw_setup']->oProc->DropTable('egw_sessions');
	$GLOBALS['egw_setup']->oProc->DropTable('egw_vfs');

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.006';
}

/**
 * Add column to store CalDAV name given by client
 */
function phpgwapi_upgrade1_9_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook','carddav_name',array(
		'type' => 'varchar',
		'precision' => '64',
		'comment' => 'name part of CardDAV URL, if specified by client'
	));
	$GLOBALS['egw_setup']->db->query($sql='UPDATE egw_addressbook SET carddav_name='.
		$GLOBALS['egw_setup']->db->concat(
			$GLOBALS['egw_setup']->db->to_varchar('contact_id'),"'.vcf'"),__LINE__,__FILE__);

	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_addressbook','carddav_name');

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.007';
}

/**
 * Add columns for session list (dla, action), make sessionid primary key and TS 64bit
 */
function phpgwapi_upgrade1_9_007()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_access_log',array(
		'fd' => array(
			'sessionid' => array('type' => 'auto','nullable' => False,'comment' => 'primary key'),
			'loginid' => array('type' => 'varchar','precision' => '64','nullable' => False,'comment' => 'username used to login'),
			'ip' => array('type' => 'varchar','precision' => '40','nullable' => False,'comment' => 'ip of user'),
			'li' => array('type' => 'int','precision' => '8','nullable' => False,'comment' => 'TS if login'),
			'lo' => array('type' => 'int','precision' => '8','comment' => 'TD of logout'),
			'account_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'numerical account id'),
			'session_dla' => array('type' => 'int','precision' => '8','comment' => 'TS of last user action'),
			'session_action' => array('type' => 'varchar','precision' => '64','comment' => 'menuaction or path of last user action'),
			'session_php' => array('type' => 'char','precision' => '64','nullable' => False,'comment' => 'php session-id or error-message'),
			'notification_heartbeat' => array('type' => 'int','precision' => '8','comment' => 'TS of last notification request')
		),
		'pk' => array('sessionid'),
		'fk' => array(),
		'ix' => array('li','lo','session_dla','notification_heartbeat'),
		'uc' => array()
	),array(
		'session_php' => 'sessionid',
		'sessionid' => 'NULL',	// to NOT copy old sessionid, but create a new sequence
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.9.008';
}

