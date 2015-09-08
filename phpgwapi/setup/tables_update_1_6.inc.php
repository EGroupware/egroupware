<?php
/**
 * EGroupware - API Setup
 *
 * Update scripts 1.6 --> 1.8
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Update from the stable 1.6 branch to the new devel branch 1.7.xxx
 */
function phpgwapi_upgrade1_6_001()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.7.001';
}

function phpgwapi_upgrade1_6_002()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.7.001';
}

function phpgwapi_upgrade1_6_003()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.7.001';
}

function phpgwapi_upgrade1_7_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_sqlfs','fs_link',array(
		'type' => 'varchar',
		'precision' => '255'
	));
	// moving symlinks from fs_content to fs_link
	$GLOBALS['egw_setup']->oProc->query("UPDATE egw_sqlfs SET fs_link=fs_content,fs_content=NULL WHERE fs_mime='application/x-symlink'",__LINE__,__FILE__);

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.7.002';
}


function phpgwapi_upgrade1_7_002()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sqlfs','fs_mime',array(
		'type' => 'varchar',
		'precision' => '96',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.7.003';
}


function phpgwapi_upgrade1_7_003()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sqlfs','fs_mime',array(
		'type' => 'varchar',
		'precision' => '96',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.8.001';
}
