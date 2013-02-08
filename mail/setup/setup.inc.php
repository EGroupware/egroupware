<?php
/**
 * EGroupware - Mail - setup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @subpackage setup
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2013 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$setup_info['mail']['name']      	= 'mail';
$setup_info['mail']['title']     	= 'mail';
$setup_info['mail']['version']     	= '1.9.001';
$setup_info['mail']['app_order'] 	= 2;
$setup_info['mail']['enable']    	= 1;
$setup_info['mail']['index']    	= 'mail.mail_ui.index&ajax=true';

$setup_info['mail']['author']		= 'Klaus Leithoff';
$setup_info['mail']['license']		= 'GPL';
$setup_info['mail']['description']	= 'IMAP emailclient for eGroupWare';
$setup_info['mail']['maintainer'] 	= 'Klaus Leithoff';
$setup_info['mail']['maintainer_email'] 	= 'kl@stylite.de';

$setup_info['mail']['tables']    = array(); // former felamimail tables are used by mail_sopreferences

/* The hooks this app includes, needed for hooks registration */
$setup_info['mail']['hooks']['addaccount']	= 'mail_hooks::accountHooks';
$setup_info['mail']['hooks']['deleteaccount']	= 'mail_hooks::accountHooks';
$setup_info['mail']['hooks']['editaccount']	= 'mail_hooks::accountHooks';
$setup_info['mail']['hooks']['search_link'] = 'mail_hooks::search_link';

/*
$setup_info['mail']['hooks']['admin'] = 'mail_hooks::admin';
$setup_info['mail']['hooks']['preferences'] = 'mail_hooks::preferences';
$setup_info['mail']['hooks']['settings'] = 'mail_hooks::settings';
$setup_info['mail']['hooks'][] = 'home';
$setup_info['mail']['hooks']['sidebox_menu'] = 'mail_hooks::sidebox_menu';
$setup_info['mail']['hooks']['verify_settings'] = 'mail_bo::forcePrefReload';
$setup_info['mail']['hooks']['edit_user']		= 'mail_hooks::adminMenu';
$setup_info['mail']['hooks']['session_creation'] = 'mail_bo::resetConnectionErrorCache';
*/

/* Dependencies for this app to work */
$setup_info['mail']['depends'][] = array(
	'appname'  => 'phpgwapi',
	'versions' => Array('1.7','1.8','1.9')
);
$setup_info['mail']['depends'][] = array(
	'appname'  => 'emailadmin',
	'versions' => Array('1.7','1.8','1.9')
);
$setup_info['mail']['depends'][] = array(
	'appname'  => 'egw-pear',
	'versions' => Array('1.8','1.9')
);
// installation checks for mail
$setup_info['mail']['check_install'] = array(
	'' => array(
		'func' => 'pear_check',
		'version' => '1.6.0',	// otherwise install of Mail_Mime fails!
	),
# get's provided by egw-pear temporarly
	'Mail_Mime' => array(
		'func' => 'pear_check',
		'version' => '1.4.1',
	),
	'Mail_mimeDecode' => array(
		'func' => 'pear_check',
	),
	'imap' => array(
		'func' => 'extension_check',
	),
	'magic_quotes_gpc' => array(
		'func' => 'php_ini_check',
		'value' => 0,
		'verbose_value' => 'Off',
	),
	'tnef' => array(
		'func' => 'tnef_check',
	),
);
