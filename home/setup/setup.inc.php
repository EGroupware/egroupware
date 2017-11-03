<?php
/**
 * EGroupware Home
 *
 * @link http://www.egroupware.org
 * @package home
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/* Basic information about this app */
$setup_info['home']['name']      = 'home';
$setup_info['home']['title']     = 'Home';
$setup_info['home']['version']   = '17.1';
$setup_info['home']['app_order'] = 1;
$setup_info['home']['enable']    = 1;
$setup_info['home']['index']    = 'home.home_ui.index&ajax=true';

$setup_info['home']['author'] = 'Nathan Gray';
$setup_info['home']['license']  = 'GPL';
$setup_info['home']['description'] = 'Displays EGroupware\' homepage';
$setup_info['home']['maintainer'] = array(
	'name' => 'EGroupware GmbH',
	'email' => 'info@egroupware.org'
);

/* Dependencies for this app to work */
$setup_info['home']['depends'][] = array(
	'appname' => 'api',
	'versions' => Array('17.1')
);
