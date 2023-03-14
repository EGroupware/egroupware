<?php
/**
 * EGroupware - API Setup
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/* Basic information about this app */
$setup_info['api']['name']      = 'api';
$setup_info['api']['title']     = 'EGroupware API';
$setup_info['api']['version']   = '23.1';
$setup_info['api']['versions']['current_header'] = '1.29';
// maintenance release in sync with changelog in doc/rpm-build/debian.changes
$setup_info['api']['versions']['maintenance_release'] = '23.1.20230314';
$setup_info['api']['enable']    = 3;
$setup_info['api']['app_order'] = 1;
$setup_info['api']['license'] = 'GPL';
$setup_info['api']['maintainer']	= $setup_info['api']['author']	= array(
	'name'  => 'EGroupware GmbH',
	'email' => 'info@egroupware.org',
	'url'   => 'http://www.egroupware.org',
);

// The tables this app creates
$setup_info['api']['tables'][]  = 'egw_config';
$setup_info['api']['tables'][]  = 'egw_applications';
$setup_info['api']['tables'][]  = 'egw_acl';
$setup_info['api']['tables'][]  = 'egw_accounts';
$setup_info['api']['tables'][]  = 'egw_preferences';
$setup_info['api']['tables'][]  = 'egw_access_log';
$setup_info['api']['tables'][]  = 'egw_languages';
$setup_info['api']['tables'][]  = 'egw_lang';
$setup_info['api']['tables'][]  = 'egw_categories';
$setup_info['api']['tables'][]  = 'egw_history_log';
$setup_info['api']['tables'][]  = 'egw_async';
$setup_info['api']['tables'][]  = 'egw_links';
$setup_info['api']['tables'][]  = 'egw_addressbook';
$setup_info['api']['tables'][]  = 'egw_addressbook_extra';
$setup_info['api']['tables'][]  = 'egw_addressbook_lists';
$setup_info['api']['tables'][]  = 'egw_addressbook2list';
$setup_info['api']['tables'][]  = 'egw_sqlfs';
$setup_info['api']['tables'][]  = 'egw_locks';
$setup_info['api']['tables'][]  = 'egw_sqlfs_props';
$setup_info['api']['tables'][]  = 'egw_customfields';
$setup_info['api']['tables'][]  = 'egw_sharing';
$setup_info['api']['tables'][]  = 'egw_mailaccounts';
$setup_info['api']['tables'][]  = 'egw_ea_accounts';
$setup_info['api']['tables'][]  = 'egw_ea_credentials';
$setup_info['api']['tables'][]  = 'egw_ea_identities';
$setup_info['api']['tables'][]  = 'egw_ea_valid';
$setup_info['api']['tables'][]  = 'egw_ea_notifications';
$setup_info['api']['tables'][]  = 'egw_addressbook_shared';

// hooks used by vfs_home_hooks to manage user- and group-directories for the new stream based VFS
$setup_info['api']['hooks']['addaccount'] = array('EGroupware\\Api\\Vfs\\Hooks::addAccount', 'EGroupware\\Api\\Mail\\Hooks::addaccount');
$setup_info['api']['hooks']['deleteaccount'] = array('EGroupware\\Api\\Vfs\\Hooks::deleteAccount', 'EGroupware\\Api\\Mail\\Hooks::deleteaccount');
$setup_info['api']['hooks']['editaccount'] = array('EGroupware\\Api\\Vfs\\Hooks::editAccount', 'EGroupware\\Api\\Mail\\Hooks::addaccount');
$setup_info['api']['hooks']['addgroup'] = 'EGroupware\\Api\\Vfs\\Hooks::addGroup';
$setup_info['api']['hooks']['deletegroup'] = array('EGroupware\\Api\\Vfs\\Hooks::deleteGroup', 'EGroupware\\Api\\Mail\\Hooks::deletegroup');
$setup_info['api']['hooks']['editgroup'] = 'EGroupware\\Api\\Vfs\\Hooks::editGroup';
$setup_info['api']['hooks']['changepassword'] = 'EGroupware\\Api\\Mail\\Hooks::changepassword';

// Hooks to delete shares when file is deleted
$setup_info['api']['hooks']['vfs_unlink'] = 'EGroupware\\Api\\Vfs\\Sharing::vfsUpdate';
$setup_info['api']['hooks']['vfs_rename'] = 'EGroupware\\Api\\Vfs\\Sharing::vfsUpdate';
$setup_info['api']['hooks']['vfs_rmdir'] = 'EGroupware\\Api\\Vfs\\Sharing::vfsUpdate';

// hook to update SimpleSAMLphp config
$setup_info['api']['hooks']['setup_config'] = [\EGroupware\Api\Auth\Saml::class.'::setupConfig', \EGroupware\Api\Accounts\Import::class.'::setupConfig'];
$setup_info['api']['hooks']['login_discovery'] = \EGroupware\Api\Auth\Saml::class.'::discovery';

// installation checks
$setup_info['api']['check_install'] = array(
		'' => array(
				'func' => 'pear_check',
				'from' => 'Api/Mail',
		),
		'pear.horde.org/Horde_Imap_Client' => array(
				'func' => 'pear_check',
				'from' => 'Api/Mail',
		'version' => '2.24.2',
	),
	'pear.horde.org/Horde_Nls' => array(
		'func' => 'pear_check',
		'from' => 'Api/Mail',
		'version' => '2.0.3',
	),
	'pear.horde.org/Horde_Mail' => array(
		'func' => 'pear_check',
		'from' => 'Api/Mail',
		'version' => '2.1.2',
	),
	'pear.horde.org/Horde_Smtp' => array(
		'func' => 'pear_check',
		'from' => 'Api/Mail',
		'version' => '1.3.0',
	),
	'pear.horde.org/Horde_ManageSieve' => array(
		'func' => 'pear_check',
		'from' => 'Api/Mail',
		'version' => '1.0.1',
	),
	// next 4 are required for TNEF support
	'pear.horde.org/Horde_Compress' => array(
		'func' => 'pear_check',
		'from' => 'Api/Mail',
		'version' => '2.0.8',
	),
	'pear.horde.org/Horde_Icalendar' => array(
		'func' => 'pear_check',
		'from' => 'Api/Mail',
		'version' => '2.0.0',
	),
	'pear.horde.org/Horde_Mapi' => array(
		'func' => 'pear_check',
		'from' => 'Api/Mail',
		'version' => '1.0.0',
	),
	'bcmath' => array(
		'func' => 'extension_check',
		'from' => 'Api/Mail',
	),
);

// CalDAV / CardDAV Sync
$setup_info['groupdav']['name']      = 'groupdav';
$setup_info['groupdav']['version']   = '23.1';
$setup_info['groupdav']['enable']    = 2;
$setup_info['groupdav']['app_order'] = 1;
$setup_info['groupdav']['icon']      = 'groupdav';
$setup_info['groupdav']['icon_app']  = 'api';
$setup_info['groupdav']['author'] = $setup_info['groupdav']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@outdoor-training.de'
);
$setup_info['groupdav']['license'] = 'GPL';
$setup_info['groupdav']['hooks']['preferences']	= 'EGroupware\\Api\\CalDAV\\Hooks::menus';
$setup_info['groupdav']['hooks']['settings']	= 'EGroupware\\Api\\CalDAV\\Hooks::settings';