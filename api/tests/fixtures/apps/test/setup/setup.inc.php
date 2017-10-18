<?php
/**
 * EGroupware test app to test eg. Api\Storage\Base
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2017RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$setup_info['test']['name']      = 'test';
$setup_info['test']['version']   = '17.1.001';
$setup_info['test']['app_order'] = 5;
$setup_info['test']['tables']    = array('egw_test');
$setup_info['test']['enable']    = 1;
$setup_info['test']['index']     = 'timesheet.timesheet_ui.index&ajax=true';

$setup_info['test']['author'] =
$setup_info['test']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'rb@egroupware.org',
);
$setup_info['test']['license']  = 'GPL';
$setup_info['test']['description'] = 'Testapp for phpUnit tests';
$setup_info['test']['note'] = '';

/* The hooks this app includes, needed for hooks registration */
$setup_info['test']['hooks'] = array();

/* Dependencies for this app to work */
$setup_info['test']['depends'][] = array(
	 'appname' => 'api',
	 'versions' => Array('16.1')
);

