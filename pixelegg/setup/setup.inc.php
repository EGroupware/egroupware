<?php
/**
 * EGroupware: Standard template
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Stefan Reinhard <stefan.reinhard@pixelegg.de>
 * @package pixelegg
 */

$GLOBALS['egw_info']['template']['pixelegg']['name']      = 'pixelegg';
$GLOBALS['egw_info']['template']['pixelegg']['title']     = 'Standard ';
$GLOBALS['egw_info']['template']['pixelegg']['version']   = '19.1';

$GLOBALS['egw_info']['template']['pixelegg']['author'] = array(
   array('name' => 'EGroupware GmbH', 'url' => 'http://www.egroupware.org/'),
   array('name' => 'Pixelegg Informationsdesign', 'url' => 'http://www.pixelegg.de/'),
);
$GLOBALS['egw_info']['template']['pixelegg']['license'] = 'GPL';
$GLOBALS['egw_info']['template']['pixelegg']['icon'] = "pixelegg/images/logo.png";
$GLOBALS['egw_info']['template']['pixelegg']['maintainer'] = array(
   array('name' => 'EGroupware GmbH', 'url' => 'http://www.egroupware.org/')
);
$GLOBALS['egw_info']['template']['pixelegg']['description'] = "Standard template set of EGroupware.";
$GLOBALS['egw_info']['template']['pixelegg']['windowed'] = true;

// specify (different) labels for default themes
$GLOBALS['egw_info']['template']['pixelegg']['themes'] = array(
	'pixelegg' => 'Standard',
	'monochrome' => 'Monochrome',
	'mobile'   => 'Compact',
);

// Dependencies for this template to work
$GLOBALS['egw_info']['template']['pixelegg']['depends'][] = array(
	'appname' => 'api',
	'versions' => Array('19.1')
);
