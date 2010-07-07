<?php
/**
 * importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

$setup_info['importexport']['name']      = 'importexport';
$setup_info['importexport']['version']   = '1.7.001';
$setup_info['importexport']['app_order'] = 2;
$setup_info['importexport']['enable']    = 2;
$setup_info['importexport']['tables']    = array('egw_importexport_definitions');

$setup_info['importexport']['author'] = 'Cornelius Weiss';
$setup_info['importexport']['maintainer'] = array(
	'name'  => 'eGroupware core team',
	'email' => 'egroupware-developers@lists.sf.net'
);
$setup_info['importexport']['license']  = 'GPL';
$setup_info['importexport']['description'] =
'';
$setup_info['importexport']['note'] =
'';

/* The hooks this app includes, needed for hooks registration */
$setup_info['importexport']['hooks']['preferences'] =
$setup_info['importexport']['hooks']['admin'] =
$setup_info['importexport']['hooks']['sidebox_menu'] = 'importexport_admin_prefs_sidebox_hooks::all_hooks';

/* Dependencies for this app to work */
$setup_info['importexport']['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.6','1.7')
);
$setup_info['importexport']['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('1.6','1.7')
);

// installation checks for importexport
$setup_info['importexport']['check_install'] = array(
	'dom' => array(
		'func' => 'extension_check',
	),
);
