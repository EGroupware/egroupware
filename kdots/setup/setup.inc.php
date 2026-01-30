<?php

use EGroupware\Kdots\Hooks;
/**
 * EGroupware: Standard template
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$setup_info['kdots']['name'] = 'kdots';
$setup_info['kdots']['title'] = 'Kdots';
$setup_info['kdots']['version'] = '26.1';
$setup_info['kdots']['autoinstall'] = true;    // install automatically on update

$setup_info['kdots']['author'] = array(
	array('name' => 'EGroupware GmbH', 'url' => 'https://www.egroupware.org/'),
);
$setup_info['kdots']['license'] = 'GPL';
$setup_info['kdots']['maintainer'] = array(
	array('name' => 'EGroupware GmbH', 'url' => 'https://www.egroupware.org/')
);
$setup_info['kdots']['description'] = "Kdots framework of EGroupware.";
$setup_info['kdots']['windowed'] = true;

$setup_info['kdots']['hooks']['settings_preferences'] = Hooks::class . '::common_preferences';

// Dependencies for this template to work
$setup_info['kdots']['depends'][] = array(
	'appname'  => 'api',
	'versions' => array('26.1')
);
$GLOBALS['egw_info']['template']['kdots'] = $setup_info['kdots'];