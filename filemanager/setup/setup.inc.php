<?php
/**
 * eGroupWare - Filemanager - setup
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$setup_info['filemanager']['name']    = 'filemanager';
$setup_info['filemanager']['title']   = 'Filemanager';
$setup_info['filemanager']['version'] = '1.6';
$setup_info['filemanager']['app_order'] = 6;
$setup_info['filemanager']['enable']  = 1;

$setup_info['infolog']['author'] =
$setup_info['infolog']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'ralfbecker@outdoor-training.de'
);
$setup_info['infolog']['license']  = 'GPL';

/* The hooks this app includes, needed for hooks registration */
$setup_info['filemanager']['hooks']['preferences'] = 'filemanager_hooks::preferences';
$setup_info['filemanager']['hooks']['settings'] = 'filemanager_hooks::settings';
$setup_info['filemanager']['hooks']['sidebox_menu'] = 'filemanager_hooks::sidebox_menu';
#$setup_info['filemanager']['hooks']['verify_settings'] = 'filemanager.filemanager_hooks.verify_settings';
$setup_info['filemanager']['hooks']['admin'] = 'filemanager_hooks::admin';


/* Dependencies for this app to work */
$setup_info['filemanager']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => array('1.5','1.6','1.7')
);
