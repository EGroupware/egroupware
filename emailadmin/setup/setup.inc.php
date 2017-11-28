<?php
/**
 * EGroupware EMailAdmin - Setup
 *
 * @link http://www.egroupware.org
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Ralf Becker <rb@stylite.de>
 * @package emailadmin
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// do NOT offer emailadmin for installation
$setup_info['emailadmin']['only_db']   = array('update');

$setup_info['emailadmin']['name']      = 'emailadmin';
$setup_info['emailadmin']['title']     = 'EMailAdmin';
$setup_info['emailadmin']['version']   = '14.3.001';
$setup_info['emailadmin']['app_order'] = 10;
$setup_info['emailadmin']['enable']    = 2;

$setup_info['emailadmin']['author'] =
$setup_info['emailadmin']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'rb@stylite.de',
);
$setup_info['emailadmin']['license']  = 'GPL';
$setup_info['emailadmin']['description'] =
	'EMailAdmin directory only exists in 16.1+ to allow updating from previous versions, without loosing email configuration.';

$setup_info['emailadmin']['tables'][]	= 'egw_emailadmin';
$setup_info['emailadmin']['tables'][]	= 'egw_mailaccounts';
$setup_info['emailadmin']['tables'][]	= 'egw_ea_accounts';
$setup_info['emailadmin']['tables'][]	= 'egw_ea_credentials';
$setup_info['emailadmin']['tables'][]	= 'egw_ea_identities';
$setup_info['emailadmin']['tables'][]	= 'egw_ea_valid';
$setup_info['emailadmin']['tables'][]	= 'egw_ea_notifications';
