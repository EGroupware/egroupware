<?php
/**
 * EGroupware - API Setup
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/* Basic information about this app */
$setup_info['phpgwapi']['name']      = 'phpgwapi';
$setup_info['phpgwapi']['title']     = 'old EGroupware API';
$setup_info['phpgwapi']['version']   = '14.3.909';
$setup_info['phpgwapi']['versions']['current_header'] = '1.29';
$setup_info['phpgwapi']['enable']    = 3;
$setup_info['phpgwapi']['app_order'] = 1;
$setup_info['phpgwapi']['license'] = 'GPL';
$setup_info['phpgwapi']['maintainer']	= $setup_info['phpgwapi']['author']	= array(
	'name'  => 'EGroupware coreteam',
	'email' => 'egroupware-developers@lists.sourceforge.net',
);

// old Api depends on new one
$setup_info['phpgwapi']['depends']['api'] = array(
	'appname' => 'api',
	'versions' => Array('16.1')
);
