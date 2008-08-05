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

/* The hooks this app includes, needed for hooks registration */


/* Dependencies for this app to work */
$setup_info['filemanager']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => array('1.5','1.6','1.7')
);
