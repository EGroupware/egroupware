<?php
/**
 * EGgroupware admin - Deny access
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['acl_manager']['admin']['site_config_acce'] = array(
	'name' => 'Deny access to site configuration',
	'rights' => array(
		'List config settings'   => 1,
		'Change config settings' => 2
	)
);	// added and working ralfbecker

$GLOBALS['acl_manager']['admin']['account_access'] = array(
	'name' => 'Deny access to user accounts',
	'rights' => array(
		'Account list'    => 1,
		'Search accounts' => 2,
		'Add account'     => 4,
		'View account'    => 8,
		'Edit account'    => 16,
		'Delete account'  => 32,
		'change ACL Rights' => 64
	)
);	// was already there and seems to work ralfbecker

$GLOBALS['acl_manager']['admin']['group_access'] = array(
	'name' => 'Deny access to groups',
	'rights' => array(
		'Group list'    => 1,
		'Search groups' => 2,
		'Add group'     => 4,
//			'View group'    => 8,			// Will be added in the future
		'Edit group'    => 16,
		'Delete group'  => 32
	)
);	// was already there and seems to work ralfbecker

$GLOBALS['acl_manager']['admin']['applications_acc'] = array(
	'name' => 'Deny access to applications',
	'rights' => array(
/* not usefull --> setup
		'Applications list' => 1,
		'Add application'   => 2,
		'Edit application'  => 4,
		'Delete application'  => 8,
*/
		'Register application hooks' => 16
	)
);	// added and working ralfbecker

$GLOBALS['acl_manager']['admin']['global_categorie'] = array(
	'name' => 'Deny access to global categories',
	'rights' => array(
		'Categories list'   => 1,
		'Search categories' => 2,
		'Add category'      => 4,
		'View category'     => 8,
		'Edit category'     => 16,
		'Delete category'   => 32,
		'Add sub-category'  => 64
	)
);	// added and working ralfbecker

$GLOBALS['acl_manager']['admin']['mainscreen_messa'] = array(
	'name' => 'Deny access to home screen message',
	'rights' => array(
		'Main screen message' => 1,
		'Login message'       => 2
	)
);	// added and working ralfbecker

$GLOBALS['acl_manager']['admin']['current_sessions'] = array(
	'name' => 'Deny access to current sessions',
	'rights' => array(
		'List current sessions'   => 1,
		'Show current action'     => 2,
		'Show session IP address' => 4,
		'Kill session'            => 8
	)
);	// checked and working ralfbecker

$GLOBALS['acl_manager']['admin']['access_log_acces'] = array(
	'name' => 'Deny access to access log',
	'rights' => array(
		'Show access log' => 1
	)
);	// added and working ralfbecker

$GLOBALS['acl_manager']['admin']['error_log_access'] = array(
	'name' => 'Deny access to error log',
	'rights' => array(
		'Show error log' => 1
	)
);	// added and working ralfbecker

$GLOBALS['acl_manager']['admin']['asyncservice_acc'] = array(
	'name' => 'Deny access to asynchronous timed services',
	'rights' => array(
		'Asynchronous timed services' => 1
	)
);	// added and working ralfbecker

$GLOBALS['acl_manager']['admin']['db_backup_access'] = array(
	'name' => 'Deny access to DB backup and restore',
	'rights' => array(
		'DB backup and restore' => 1
	)
);	// added and working ralfbecker

$GLOBALS['acl_manager']['admin']['info_access'] = array(
	'name' => 'Deny access to phpinfo',
	'rights' => array(
		'Show phpinfo()' => 1
	)
);	// added and working ralfbecker

