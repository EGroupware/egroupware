<?php
/**
 * EGroupware - FMail
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package felamimail
 * @subpackage setup
 * @version $Id$
 */

$setup_info['felamimail']['name']      		= 'felamimail';
$setup_info['felamimail']['title']     		= 'FeLaMiMail';
$setup_info['felamimail']['version']     	= '1.9';
$setup_info['felamimail']['app_order'] 		= 2;
$setup_info['felamimail']['enable']    		= 1;
$setup_info['felamimail']['index']    		= 'felamimail.uifelamimail.viewMainScreen&ajax=true';

$setup_info['felamimail']['author']		= 'Lars Kneschke';
$setup_info['felamimail']['license']		= 'GPL';
$setup_info['felamimail']['description']	= 'IMAP emailclient for eGroupWare';
$setup_info['felamimail']['maintainer'] 	= 'Klaus Leithoff';
$setup_info['felamimail']['maintainer_email'] 	= 'kl@stylite.de';

$setup_info['felamimail']['tables']    = array('egw_felamimail_displayfilter','egw_felamimail_accounts','egw_felamimail_signatures');

/* The hooks this app includes, needed for hooks registration */
$setup_info['felamimail']['hooks']['admin'] = 'felamimail_hooks::admin';
$setup_info['felamimail']['hooks']['preferences'] = 'felamimail_hooks::preferences';
$setup_info['felamimail']['hooks']['settings'] = 'felamimail_hooks::settings';
$setup_info['felamimail']['hooks'][] = 'home';
$setup_info['felamimail']['hooks']['sidebox_menu'] = 'felamimail_hooks::sidebox_menu';
$setup_info['felamimail']['hooks']['addaccount']	= 'felamimail_hooks::accountHooks';
$setup_info['felamimail']['hooks']['deleteaccount']	= 'felamimail_hooks::accountHooks';
$setup_info['felamimail']['hooks']['editaccount']	= 'felamimail_hooks::accountHooks';
$setup_info['felamimail']['hooks']['verify_settings'] = 'felamimail_bo::forcePrefReload';
$setup_info['felamimail']['hooks']['edit_user']		= 'felamimail_hooks::adminMenu';
$setup_info['felamimail']['hooks']['search_link'] = 'felamimail_hooks::search_link';
$setup_info['felamimail']['hooks']['session_creation'] = 'felamimail_bo::resetConnectionErrorCache';

/* Dependencies for this app to work */
$setup_info['felamimail']['depends'][] = array(
	'appname'  => 'phpgwapi',
	'versions' => Array('1.7','1.8','1.9')
);
$setup_info['felamimail']['depends'][] = array(
	'appname'  => 'emailadmin',
	'versions' => Array('1.7','1.8','1.9')
);
$setup_info['felamimail']['depends'][] = array(
	'appname'  => 'egw-pear',
	'versions' => Array('1.8','1.9')
);
// installation checks for felamimail
$setup_info['felamimail']['check_install'] = array(
	'' => array(
		'func' => 'pear_check',
		'version' => '1.6.0',	// otherwise install of Mail_Mime fails!
	),
# get's provided by egw-pear temporarly
	'Net_Sieve' => array(
		'func' => 'pear_check',
	),
	'Net_IMAP' => array(
		'func' => 'pear_check',
	),
	'Auth_SASL' => array(
		'func' => 'pear_check',
	),
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
	// as alternative for PHP imap extension
	'pear.horde.org/Horde_Mime' => array(
		'func' => 'pear_check',
		'from' => 'EMailAdmin',
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
