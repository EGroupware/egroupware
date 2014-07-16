<?php
/**
 * eGroupware EMailAdmin - Setup
 *
 * @link http://www.egroupware.org
 * @author Klaus Leithoff <kl@stylite.de>
 * @package emailadmin
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$setup_info['emailadmin']['name']      = 'emailadmin';
$setup_info['emailadmin']['title']     = 'EMailAdmin';
$setup_info['emailadmin']['version']   = '1.9.006';
$setup_info['emailadmin']['app_order'] = 10;
$setup_info['emailadmin']['enable']    = 2;
$setup_info['emailadmin']['index']     = 'emailadmin.emailadmin_ui.listProfiles';

$setup_info['emailadmin']['author'] = 'Klaus Leithoff';
$setup_info['emailadmin']['license']  = 'GPL';
$setup_info['emailadmin']['description'] =
	'A central Mailserver management application for EGroupWare. Completely rewritten by K.Leithoff in 10-2009';
$setup_info['emailadmin']['note'] =
	'';
$setup_info['emailadmin']['maintainer'] = array(
	'name'  => 'Leithoff, Klaus',
	'email' => 'kl@stylite.de'
);

$setup_info['emailadmin']['tables'][]	= 'egw_emailadmin';
$setup_info['emailadmin']['tables'][]	= 'egw_mailaccounts';

/* The hooks this app includes, needed for hooks registration */
#$setup_info['emailadmin']['hooks'][] = 'preferences';
$setup_info['emailadmin']['hooks']['admin'] = 'emailadmin_hooks::admin';
$setup_info['emailadmin']['hooks']['edit_user'] = 'emailadmin_hooks::edit_user';
$setup_info['emailadmin']['hooks']['view_user'] = 'emailadmin_hooks::edit_user';
$setup_info['emailadmin']['hooks']['edit_group'] = 'emailadmin_hooks::edit_group';
$setup_info['emailadmin']['hooks']['group_manager'] = 'emailadmin_hooks::edit_group';
$setup_info['emailadmin']['hooks']['deleteaccount'] = 'emailadmin_hooks::deleteaccount';
$setup_info['emailadmin']['hooks']['deletegroup'] = 'emailadmin_hooks::deletegroup';
$setup_info['emailadmin']['hooks']['changepassword'] = 'emailadmin_bo::changepassword';

// SMTP and IMAP support
$setup_info['emailadmin']['hooks']['smtp_server_types'] = 'emailadmin_hooks::server_types';
$setup_info['emailadmin']['hooks']['imap_server_types'] = 'emailadmin_hooks::server_types';

/* Dependencies for this app to work */
$setup_info['emailadmin']['depends'][] = array(
	'appname'  => 'phpgwapi',
	'versions' => Array('1.7','1.8','1.9')
);
$setup_info['emailadmin']['depends'][] = array(
	'appname'  => 'egw-pear',
	'versions' => Array('1.8','1.9')
);
// installation checks for felamimail
$setup_info['emailadmin']['check_install'] = array(
	'' => array(
		'func' => 'pear_check',
		'from' => 'EMailAdmin',
	),
	'Auth_SASL' => array(
		'func' => 'pear_check',
		'from' => 'EMailAdmin',
	),
	'Net_IMAP' => array(
		'func' => 'pear_check',
		'from' => 'EMailAdmin',
	),
	'imap' => array(
		'func' => 'extension_check',
		'from' => 'EMailAdmin',
	),
	// as alternative for PHP imap extension
	'pear.horde.org/Horde_Mime' => array(
		'func' => 'pear_check',
		'from' => 'EMailAdmin',
	),
);

