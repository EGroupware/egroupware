<?php
/**
 * EGroupware - Mail - setup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @subpackage setup
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013-14 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$setup_info['mail']['name']      	= 'mail';
$setup_info['mail']['title']     	= 'mail';
$setup_info['mail']['version']     	= '14.1';
$setup_info['mail']['app_order'] 	= 2;
$setup_info['mail']['enable']    	= 1;
$setup_info['mail']['index']    	= 'mail.mail_ui.index&ajax=true';
$setup_info['mail']['autoinstall'] = true;	// install automatically on update

$setup_info['mail']['author']		= 'Stylite AG';
$setup_info['mail']['license']		= 'GPL';
$setup_info['mail']['description']	= 'IMAP client for EGroupware';
$setup_info['mail']['maintainer'] 	= 'Stylite AG';
$setup_info['mail']['maintainer_email'] 	= 'info@stylite.de';

$setup_info['mail']['tables']    = array(); // former felamimail tables are used by mail_sopreferences

/* The hooks this app includes, needed for hooks registration */
$setup_info['mail']['hooks']['search_link'] = 'mail_hooks::search_link';
$setup_info['mail']['hooks']['admin'] = 'mail_hooks::admin';
$setup_info['mail']['hooks']['settings'] = 'mail_hooks::settings';
$setup_info['mail']['hooks']['sidebox_menu'] = 'mail_hooks::sidebox_menu';
$setup_info['mail']['hooks']['session_creation'] = 'mail_bo::resetConnectionErrorCache';
$setup_info['mail']['hooks']['verify_settings'] = 'mail_bo::forcePrefReload';
$setup_info['mail']['hooks']['clear_cache'] = 'mail_bo::unsetCachedObjects';
$setup_info['mail']['hooks']['check_notify'] = 'mail_hooks::notification_check_mailbox';
$setup_info['mail']['hooks']['emailadmin_edit'] = 'mail_hooks::emailadmin_edit';

/*
$setup_info['mail']['hooks'][] = 'home';
*/

/* Dependencies for this app to work */
$setup_info['mail']['depends'][] = array(
	'appname'  => 'phpgwapi',
	'versions' => Array('14.1')
);
$setup_info['mail']['depends'][] = array(
	'appname'  => 'etemplate',
	'versions' => Array('14.1')
);
$setup_info['mail']['depends'][] = array(
	'appname'  => 'emailadmin',
	'versions' => Array('14.1')
);
// installation checks for mail
$setup_info['mail']['check_install'] = array(
	'' => array(
		'func' => 'pear_check',
		'version' => '1.6.0',	// otherwise install of Mail_Mime fails!
	),
	'Mail_Mime' => array(
		'func' => 'pear_check',
		'version' => '1.4.1',
	),
	'Mail_mimeDecode' => array(
		'func' => 'pear_check',
	),
	'magic_quotes_gpc' => array(
		'func' => 'php_ini_check',
		'value' => 0,
		'verbose_value' => 'Off',
	),
	'mbstring.func_overload' => array(
		'func' => 'php_ini_check',
		'value' => 0,
		'warning' => '<div class="setup_info">' . lang('mbstring.func_overload=0 is required for correct mail processing!') . "</div>",
		'change' => 'mbstring.func_overload = 0',
	),
);
