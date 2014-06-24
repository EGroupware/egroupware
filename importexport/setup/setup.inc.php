<?php
/**
 * EGroupware - importexport
 *
 * @link www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

$setup_info['importexport']['name']      = 'importexport';
$setup_info['importexport']['version']   = '14.1';
$setup_info['importexport']['app_order'] = 2;
$setup_info['importexport']['enable']    = 2;
$setup_info['importexport']['tables']    = array('egw_importexport_definitions');

$setup_info['importexport']['author'] = 'Cornelius Weiss';
$setup_info['importexport']['maintainer'] = array(
	'name'  => 'eGroupware core team',
	'email' => 'egroupware-developers@lists.sf.net'
);
$setup_info['importexport']['autoinstall'] = true;	// install automatically on update

$setup_info['importexport']['license']  = 'GPL';
$setup_info['importexport']['description'] =
'';
$setup_info['importexport']['note'] =
'';

/* The hooks this app includes, needed for hooks registration */
$setup_info['importexport']['hooks']['admin'] =
$setup_info['importexport']['hooks']['sidebox_menu'] = 'importexport_admin_prefs_sidebox_hooks::all_hooks';
$setup_info['importexport']['hooks']['sidebox_all'] = 'importexport_admin_prefs_sidebox_hooks::other_apps';
$setup_info['importexport']['hooks']['etemplate2_register_widgets'] = 'importexport_admin_prefs_sidebox_hooks::widgets';

/* Dependencies for this app to work */
$setup_info['importexport']['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('14.1')
);
$setup_info['importexport']['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('14.1')
);

// installation checks for importexport
$setup_info['importexport']['check_install'] = array(
	'dom' => array(
		'func' => 'extension_check',
	),
	'' => array(
		'func' => 'pear_check',
	),
	'Console_Getopt' => array(
		'func' => 'pear_check',
	),
);
