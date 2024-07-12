<?php
/**
 * EGroupware: Standard template
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$GLOBALS['egw_info']['template']['kdots']['name'] = 'kdots';
$GLOBALS['egw_info']['template']['kdots']['title'] = 'Kdots WIP ';
$GLOBALS['egw_info']['template']['kdots']['version'] = '24.1';

$GLOBALS['egw_info']['template']['kdots']['author'] = array(
	array('name' => 'EGroupware GmbH', 'url' => 'http://www.egroupware.org/'),
);
$GLOBALS['egw_info']['template']['kdots']['license'] = 'GPL';
$GLOBALS['egw_info']['template']['kdots']['maintainer'] = array(
	array('name' => 'EGroupware GmbH', 'url' => 'http://www.egroupware.org/')
);
$GLOBALS['egw_info']['template']['kdots']['description'] = "WIP framework of EGroupware.";
$GLOBALS['egw_info']['template']['kdots']['windowed'] = true;

// Dependencies for this template to work
$GLOBALS['egw_info']['template']['kdots']['depends'][] = array(
	'appname'  => 'api',
	'versions' => array('23.1')
);