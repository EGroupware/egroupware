#!/usr/bin/php -qC 
<?php
/**
 * Admin - Command line interface
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006/7 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling admin-cli as web-page
{
	die('<h1>admin-cli.php must NOT be called as web-page --> exiting !!!</h1>');
}
elseif ($_SERVER['argc'] > 1)
{
	$arguments = $_SERVER['argv'];
	array_shift($arguments);
	$action = array_shift($arguments);
}
else
{
	$action = '--help';
}

// this is kind of a hack, as the autocreate_session_callback can not change the type of the loaded account-class
// so we need to make sure the right one is loaded by setting the domain before the header gets included.
$arg0s = explode(',',@array_shift($arguments));
@list(,$_GET['domain']) = explode('@',$arg0s[0]);

if (!is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
{
	ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'admin',
		'noheader' => true,
		'autocreate_session_callback' => 'user_pass_from_argv',
	)
);

include('../header.inc.php');

switch($action)
{
	case '--edit-user':
		return do_edit_user($arg0s);
		
	case '--change-pw':
		return do_change_pw($arg0s);

	case '--delete-user':
		return do_delete_account($arg0s[2],$arg0s[3]);
		
	case '--edit-group':
		return do_edit_group($arg0s);
		
	case '--delete-group':
		return do_delete_account($arg0s[2],0,'g');
		
	case '--allow-app':
	case '--deny-app':
		return do_account_app($arg0s,$action == '--allow-app');
		
	case '--change-account-id':
		return do_change_account_id($arg0s);
		
	case '--subscribe-other':
		return do_subscribe_other($arg0s[2],$arg0s[3]);
		
	case '--check-acl';
		return do_check_acl();
		
	case '--exit-codes':
		return list_exit_codes();

	default:
		usage($action);
		break;
}
exit(0);

/**
 * run a command object, after checking for additional arguments: sheduled, requested or comment
 *
 * @param admin_cmd $cmd
 * @return string see admin_cmd::run()
 */
function run_command($cmd)
{
	global $arguments;

	while ($arguments && ($extra = array_shift($arguments)))
	{
		switch($extra)
		{
			case '--shedule':	// shedule the command instead of running it directly
				$time = admin_cmd::parse_date(array_shift($arguments));
				break;
				
			case '--requested':	// note who requested to run the command
				$cmd->requested = 0;
				$cmd->requested_email = array_shift($arguments);
				break;
				
			case '--comment':	// note a comment
				$cmd->comment = array_shift($arguments);
				break;
				
			case '--remote':	// run the command on a remote install
				$cmd->remote_id = admin_cmd::parse_remote(array_shift($arguments));
				break;
				
			default:
				//fail(99,lang('Unknown option %1',$extra);
				echo lang('Unknown option %1',$extra)."\n\n";
				usage('',99);
				break;
		}
	}
	//_debug_array($cmd);
	return $cmd->run($time);
}

/**
 * callback to authenticate with the user/pw specified on the commandline
 * 
 * @param array &$account account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean/string true if we allow the access and account is set, a sessionid or false otherwise
 */
function user_pass_from_argv(&$account)
{
	$account = array(
		'login'  => $GLOBALS['arg0s'][0],
		'passwd' => $GLOBALS['arg0s'][1],
		'passwd_type' => 'text',
	);
	//print_r($account);
	if (!($sessionid = $GLOBALS['egw']->session->create($account)))
	{
		//fail(1,lang("Wrong admin-account or -password !!!"));
		echo lang("Wrong admin-account or -password !!!")."\n\n";
		usage('',1);
	}
	if (!$GLOBALS['egw_info']['user']['apps']['admin'])	// will be tested by the header too, but whould give html error-message
	{
		//fail(2,lang("Permission denied !!!"));
		echo lang("Permission denied !!!")."\n\n";
		usage('',2);
	}
	return $sessionid;
}

/**
 * Give a usage message and exit
 *
 * @param string $action=null
 * @param int $ret=0 exit-code
 */
function usage($action=null,$ret=0)
{
	$cmd = basename($_SERVER['argv'][0]);
	echo "Usage: $cmd --command admin-account[@domain],admin-password,options,... [--schedule {YYYY-mm-dd|+1 week|+5 days}] [--requested 'Name <email>'] [--comment 'comment ...'] [--remote {id|name}]\n\n";
	
	echo "--edit-user admin-account[@domain],admin-password,account[=new-account-name],first-name,last-name,password,email,expires{never(default)|YYYY-MM-DD|already},can-change-pw{yes(default)|no},anon-user{yes|no(default)},primary-group{Default(default)|...}[,groups,...]\n";
	echo "	Edit or add a user to eGroupWare. If you specify groups, they *replace* the exiting memberships!\n";
	echo "--change-pw admin-account[@domain],admin-password,account,password\n";
	echo "  Change/set the password for a given user\n";
	echo "--delete-user admin-account[@domain],admin-password,account-to-delete[,account-to-move-data]\n";
	echo "	Deletes a user from eGroupWare. It's data can be moved to an other user or it get deleted too.\n";
	echo "--edit-group admin-account[@domain],admin-password,group[=new-group-name],email[,members,...]\n";
	echo "	Edit or add a group to eGroupWare. If you specify members, they *replace* the exiting members!\n";
	echo "--delete-group admin-account[@domain],admin-password,group-to-delete\n";
	echo "	Deletes a group from eGroupWare.\n";
	echo "--allow-app admin-account[@domain],admin-password,account,application,...\n";
	echo "--deny-app admin-account[@domain],admin-password,account,application,...\n";
	echo "	Give or deny an account (user or group specified by account name or id) run rights for the given applications.\n";
	echo "--change-account-id admin-account[@domain],admin-password,from1,to1[...,fromN,toN]\n";
	echo "	Changes one or more account_id's in the database (make a backup before!).\n";
	echo "--check-acl admin-account[@domain],admin-password\n";
	echo "	Deletes ACL entries of not longer existing accounts (make a database backup before! --> setup-cli.php).\n";
	echo "--exit-codes admin-account[@domain],admin-password\n";
	echo "	List all exit codes of the command line interface\n";

	exit($ret);	
}

/**
 * Give or deny an account (user or group specified by account name or id) run rights for the given applications.
 *
 * @param array $args admin-account[@domain],admin-password,account,application,...
 * @param boolean $allow true=allow, false=deny
 * @return int 0 on success
 */
function do_account_app($args,$allow)
{
	array_shift($args);	// admin-account
	array_shift($args);	// admin-pw
	$account = array_shift($args);
	
	include_once(EGW_INCLUDE_ROOT.'/admin/inc/class.admin_cmd_account_app.inc.php');
	$cmd = new admin_cmd_account_app($allow,$account,$args);
	$msg = run_command($cmd);
	if ($cmd->errno)
	{
		fail($cmd->errno,$cmd->error);
	}
	echo $msg."\n\n";
	return 0;
	
	if ($GLOBALS['egw']->acl->check('account_access',16,'admin'))	// user is explicitly forbidden to edit accounts
	{
		fail(2,lang("Permission denied !!!"));
	}
	if (!($type = $GLOBALS['egw']->accounts->exists($account)) || !is_numeric($id=$account) && !($id = $GLOBALS['egw']->accounts->name2id($account)))
	{
		fail(15,lang("Unknown account: %1 !!!",$account));
	}
	if ($type == 2 && $id > 0) $id = -$id;	// groups use negative id's internally, fix it, if user given the wrong sign

	if (!($apps = _parse_apps($args)))
	{
		return false;
	}
	//echo "account=$account, type=$type, id=$id, apps: ".implode(', ',$apps)."\n";
	foreach($apps as $app)
	{
		if ($allow)
		{
			$GLOBALS['egw']->acl->add_repository($app,'run',$id,1);
		}
		else
		{
			$GLOBALS['egw']->acl->delete_repository($app,'run',$id);
		}
	}
	echo lang('Applications run rights updated.')."\n\n";
	return 0;
}

/**
 * Edit or add a group to eGroupWare. If you specify members, they *replace* the exiting member!
 *                    1:                     2:             3:                     4:     5:
 * @param array $args admin-account[@domain],admin-password,group[=new-group-name],email[,members,...]
 */
function do_edit_group($args)
{
	array_shift($args);	// admin-account
	array_shift($args);	// admin-pw
	list($account,$new_account_name) = explode('=',array_shift($args));	// account[=new-account-name]
	
	$account_exists = true;
	if (!($data = $GLOBALS['egw']->accounts->read($account)) || $data['account_type'] != 'g')
	{
		$account_exists = false;
		$data = array(
			'account_type' => 'g',
			'account_status' => 'A',	// not used, but so we do the same thing as the web-interface
			'account_expires' => -1,
		);
	}
	if ($GLOBALS['egw']->acl->check('account_access',$account_exists?16:4,'admin'))	// user is explicitly forbidden to edit or add groups
	{
		fail(2,lang("Permission denied !!!"));
	}
	if (!$account_exists && $new_account_name)
	{
		fail(12,lang("Unknown group to edit: %1 !!!",$account));
	}
	if (($email = array_shift($args)))
	{
		$data['account_email'] = $email;
	}
	if (($data['account_members'] = _parse_users($args)) === false)
	{
		return false;
	}
	if (!$account_exists && !$account)
	{
		fail(13,lang("You have to specify an non-empty group-name!"));
	}
	if (!$account_exists || $new_account_name) $data['account_lid'] = $new_account_name ? $new_account_name : $account;
	
	if (!$account_exists && !$args)
	{
		fail(14,lang("A group needs at least one member!"));
	}
	if (!$GLOBALS['egw']->accounts->save($data))
	{
		fail(11,lang("Error saving account!"));
	}
	$GLOBALS['hook_values'] = $data;
	if (!$account_exists) $GLOBALS['hook_values']['old_name'] = $account;
	$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
		'location' => $account_exists ? 'editgroup' : 'addgroup'
	),False,True);  // called for every app now, not only enabled ones)

	if ($data['account_members'])
	{
		$GLOBALS['egw']->accounts->set_members($data['account_members'],$data['account_id']);
	}
	echo lang("Account %1 %2",$account,$account_exists ? lang('updated') : lang("created with id #%1",$data['account_id']))."\n\n";
	return 0;
}

/**
 * Change/Set Password for a given user
 *                    1:                     2:             3:                         4: 
 * @param array $args admin-account[@domain],admin-password,account,password
 */
function do_change_pw($args)
{
	array_shift($args);     // admin-account
	array_shift($args);     // admin-pw
	$account = array_shift($args);     // account

	$account_exists = true;
	if (!($data = $GLOBALS['egw']->accounts->read($account)) || $data['account_type'] != 'u')
	{
		$account_exists = false;
		$data = array('account_type' => 'u');
	}
	if ($GLOBALS['egw']->acl->check('account_access',$account_exists?16:4,'admin')) // user is explicitly forbidden to edit or add users
	{
		fail(2,lang("Permission denied !!!"));
	}
	if (!$account_exists)
	{
		fail(5,lang("Unknown user to change pw: %1 !!!",$account));
	}

	foreach(array(
		'account_lid' => $account,
		'account_passwd' => !($arg=array_shift($args)) ? null : $arg,
	) as $name => $value)
	{
		if ($value === false) return false;     // error in _parse_xyz()
		//echo $name.': '.(is_array($value) ? implode(', ',$value) : $value)."\n";
		if (!is_null($value)) $data[$name] = $value;
	}
	if($account_exists && $data['account_passwd'])
	{
		$auth =& CreateObject('phpgwapi.auth');
		$auth->change_password(null, $data['account_passwd'], $data['account_id']);
		$GLOBALS['hook_values']['account_id'] = $data['account_id'];
		$GLOBALS['hook_values']['old_passwd'] = null;
		$GLOBALS['hook_values']['new_passwd'] = $data['account_passwd'];
		$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
			'location' => 'changepassword'
		),False,True);  // called for every app now, not only enabled ones)
	}

	echo lang("Account %1 %2 ",$account,$account_exists).  lang('updated')."\n\n";
	return 0;

}

/**
 * Edit or add a user to eGroupWare. If you specify groups, they *replace* the exiting memberships!
 *                    1:                     2:             3:                         4:         5:        6:       7:    8:                                         9:                                 10:                            11:                                  12
 * @param array $args admin-account[@domain],admin-password,account[=new-account-name],first-name,last-name,password,email,expires{never(default)|YYYY-MM-DD|already},can-change-pw{true(default)|false},anon-user{true|false(default)},primary-group{Default(default)|...}[,groups,...]
 */
function do_edit_user($args)
{
	array_shift($args);	// admin-account
	array_shift($args);	// admin-pw
	list($account,$new_account_name) = explode('=',array_shift($args));	// account[=new-account-name]
	
	$account_exists = true;
	if (!($data = $GLOBALS['egw']->accounts->read($account)) || $data['account_type'] != 'u')
	{
		$account_exists = false;
		$data = array('account_type' => 'u');
	}
	if ($GLOBALS['egw']->acl->check('account_access',$account_exists?16:4,'admin'))	// user is explicitly forbidden to edit or add users
	{
		fail(2,lang("Permission denied !!!"));
	}
	if (!$account_exists && $new_account_name)
	{
		fail(5,lang("Unknown user to edit: %1 !!!",$account));
	}
	//echo !$account_exists ? "add account $account:\n" : "edit account $account:\n";
	foreach(array(
		'account_lid' => $new_account_name ? $new_account_name : ($account_exists ? $data['account_lid'] : $account),
		'account_firstname' => !($arg=array_shift($args)) ? null : $arg,
		'account_lastname' => !($arg=array_shift($args)) ? null : $arg,
		'account_passwd' => !($arg=array_shift($args)) ? null : $arg,
		'account_email' => !($arg=array_shift($args)) ? null : $arg,
		'account_expires' => $expires=_parse_expired(!($expires=array_shift($args)) && !$account_exists ? 'never' : $expires),
		'account_status' => !$expires ? null : ($expires === -1 || $expires > time() ? 'A' : ''),
		'changepassword' => !($can_pw=array_shift($args)) && !$account_exists ? 'yes' : ($can_pw ? $can_pw : null),
		'anonymous' => !($is_anon=array_shift($args)) && !$account_exists ? 'no' : ($is_anon ? $is_anon : null),
		'account_primary_group' => _parse_groups(!($primary=array_shift($args)) && !$account_exists ? ($primary='Default') : $primary),
		'account_groups' => _parse_groups(!$args && !$account_exists ? array($primary ? $primary : 'Default') : $args),
	) as $name => $value)
	{
		if ($value === false) return false;	// error in _parse_xyz()

		//echo $name.': '.(is_array($value) ? implode(', ',$value) : $value)."\n";
		if (!is_null($value)) $data[$name] = $value;
	}
	if (!$data['account_lid'] || !$account_exists && !$data['account_lastname'])
	{
		fail(9,lang("You have to specify an non-empty account-name and lastname!"));
	}
	if (!$account_exists && !$data['account_primary_group'])
	{
		fail(10,lang("You have to specify at least a primary group!"));
	}
	if ($data['groups'] && !in_array($data['account_primary_group'],$data['groups']) || !$account_exists && !$data['groups'])
	{
		$data['groups'][] = $data['account_primary_group'];
	}
	if (!$GLOBALS['egw']->accounts->save($data))
	{
		fail(11,lang("Error saving account!"));
	}
	if ($data['account_groups'])
	{
		$GLOBALS['egw']->accounts->set_memberships($data['account_groups'],$data['account_id']);
	}
	if ($data['anonymous'])
	{
		if ($data['anonymous']{0} != 'n')
		{
			$GLOBALS['egw']->acl->add_repository('phpgwapi','anonymous',$data['account_id'],1);
		}
		else
		{
			$GLOBALS['egw']->acl->delete_repository('phpgwapi','anonymous',$data['account_id']);
		}
	}
	if ($data['changepassword'])
	{
		if ($data['changepassword']{0} == 'n')
		{
			$GLOBALS['egw']->acl->add_repository('preferences','nopasswordchange',$data['account_id'],1);
		}
		else
		{
			$GLOBALS['egw']->acl->delete_repository('preferences','nopasswordchange',$data['account_id']);
		}
	}
	if($account_exists && $data['account_passwd'])
	{
		$auth =& CreateObject('phpgwapi.auth');
		$auth->change_password(null, $data['account_passwd'], $data['account_id']);
		$GLOBALS['hook_values']['account_id'] = $data['account_id'];
		$GLOBALS['hook_values']['old_passwd'] = null;
		$GLOBALS['hook_values']['new_passwd'] = $data['account_passwd'];

		$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
			'location' => 'changepassword'
		),False,True);	// called for every app now, not only enabled ones)
	}
	$GLOBALS['hook_values'] = $data;
	$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
		'location' => $account_exists ? 'editaccount' : 'addaccount'
	),False,True);	// called for every app now, not only enabled ones)
	
	echo lang("Account %1 %2",$account,$account_exists ? lang('updated') : lang("created with id #%1",$data['account_id']))."\n\n";
	return 0;
}

/**
 * parse application names, titles or localised names and return array of app-names
 *
 * @param array $apps names, titles or localised names
 * @return array/boolean array of app-names or false if an app is not found
 */
function _parse_apps($apps)
{
	foreach($apps as $key => $name)
	{
		if (!isset($GLOBALS['egw_info']['apps'][$name]))
		{
			foreach($GLOBALS['egw_info']['apps'] as $app => $data)	// check against title and localised name
			{
				if (!strcasecmp($name,$data['title']) || !strcasecmp($name,lang($app)))
				{
					$apps[$key] = $name = $app;
					break;
				}
			}
		}
		if (!isset($GLOBALS['egw_info']['apps'][$name]))
		{
			fail(8,lang("Application '%1' not found (maybe not installed or misspelled)!",$name));
			return false;
		}
	}
	return $apps;
}

/**
 * parse groups and return the group-id's
 *
 * @param string/array $groups group-id's or names
 * @return string/array/boolean false on error
 */
function _parse_groups($groups)
{
	if (!$groups) return null;
	
	$ids = array();
	foreach(is_array($groups) ? $groups : array($groups) as $group)
	{
		if ($GLOBALS['egw']->accounts->exists($id = is_numeric($group) && $group > 0 ? -$group : $group) != 2 ||
			(!is_numeric($group) && !($id = $GLOBALS['egw']->accounts->name2id($group,'account_lid','g'))))
		{
			fail(8,lang("Unknown group: %1 !!!",$group));
			return false;
		}
		$ids[] = $id;
	}
	return is_string($groups) ? $ids[0] : $ids;
}

/**
 * parse users and return the user-id's
 *
 * @param string/array $users user-id's or names
 * @return string/array/boolean false on error
 */
function _parse_users($users)
{
	if (!$users) return null;
	
	$ids = array();
	foreach(is_array($users) ? $users : array($users) as $user)
	{
		if ($GLOBALS['egw']->accounts->exists($id = $user) != 1 ||
			(!is_numeric($group) && !($id = $GLOBALS['egw']->accounts->name2id($user,'account_lid','u'))))
		{
			fail(7,lang("Unknown user: %1 !!!",$user));
			return false;
		}
		$ids[] = $id;
	}
	return is_string($groups) ? $ids[0] : $ids;
}

/**
 * parse the expired string and return the expired date as timestamp
 *
 * @param string $str
 * @return int/boolean false on error
 */
function _parse_expired($str)
{
	switch($str)
	{
		case 'never':
			return -1;
		case 'already':
			return 0;
		case '':
			return null;
	}
	// YYYY-MM-DD
	list($y,$m,$d) = explode('-',$str);
	if (!checkdate((int)$m,(int)$d,(int)$y))
	{
		fail(6,lang("Invalid date '%1' use YYYY-MM-DD!",$str));
		return false;
	}
	return mktime(0,0,0,$m,$d,$y);
}

/**
 * Delete a given acount from eGW
 *
 * @param int/string $account account-name of -id
 * @param int/string $new_user=0 for uses only: account to move the entries too
 * @param boolean $type='u' are we called for a user or group
 * @return int 0 on success, 2-4 otherwise (see source)
 */
function do_delete_account($account,$new_user=0,$type='u')
{
	//echo "do_delete_account('$account','$new_user',$do_group)\n";
	if ($GLOBALS['egw']->acl->check('account_access',32,'admin'))	// user is explicitly forbidden to delete users
	{
		fail(2,lang("Permission denied !!!"));
	}
	if (!is_numeric($account) && !($id = $GLOBALS['egw']->accounts->name2id($lid=$account)) ||
	     is_numeric($account) && !($lid = $GLOBALS['egw']->accounts->id2name($id=$account)) ||
	     $GLOBALS['egw']->accounts->get_type($id) != $type)
	{
		fail(3,lang("Unknown account to delete: %1 !!!",$account));
	}
	if ($new_user && (!is_numeric($new_user) && !($new_uid = $GLOBALS['egw']->accounts->name2id($new_user)) ||
	                   is_numeric($new_user) && !$GLOBALS['egw']->accounts->id2name($new_uid=$new_user)))
	{
		fail(4,lang("Unknown user to move to: %1 !!!",$new_user));
	}
	// delete the account
	$GLOBALS['hook_values'] = array(
		'account_id'  => $id,
		'account_lid' => $lid,
		'account_name'=> $lid,				// pericated name for deletegroup hook
		'new_owner'   => (int)$new_uid,		// deleteaccount only
		'location'    => $type == 'u' ? 'deleteaccount' : 'deletegroup',
	);
	// first all other apps, then preferences and admin
	foreach(array_merge(array_diff(array_keys($GLOBALS['egw_info']['apps']),array('preferences','admin')),array('preferences','admin')) as $app)
	{
		$GLOBALS['egw']->hooks->single($GLOBALS['hook_values'],$app);
	}			
	if ($type == 'g') $GLOBALS['egw']->accounts->delete($id);	// groups get not deleted via the admin hook, as users

	echo lang("Account '%1' deleted.",$account)."\n\n";
	return 0;
}

/**
 * Deletes ACL entries of not longer existing accounts
 * 
 * @return int 0 allways
 */
function do_check_acl()
{
	$deleted = 0;
	if (($all_accounts = $GLOBALS['egw']->accounts->search(array('type'=>'both'))))
	{
		$ids = array();
		foreach($all_accounts as $account)
		{
			$ids[] = $account['account_id'];
		}
		// does not work for LDAP! $ids = array_keys($all_accounts);
		$GLOBALS['egw']->db->query("DELETE FROM egw_acl WHERE acl_account NOT IN (".implode(',',$ids).") OR acl_appname='phpgw_group' AND acl_location NOT IN ('".implode("','",$ids)."')",__LINE__,__FILE__);
		$deleted = $GLOBALS['egw']->db->affected_rows();
	}
	echo lang("%1 ACL records of not (longer) existing accounts deleted.",$deleted)."\n\n";
	return 0;
}

/**
 * Changes one or more account_id's in the database (make a backup before!).
 *
 * @param array $args admin-account[@domain],admin-password,from1,to1[...,fromN,toN]
 * @return int 0 on success
 */
function do_change_account_id($args)
{
	/**
	 * App-, Table- and column-names containing nummeric account-id's
	 * @var array
	 */
	$columns2change = array(
		'phpgwapi' => array(
			'egw_access_log'     => 'account_id',
			'egw_accounts'       => array(array('account_id','.type'=>'abs'),'account_primary_group'),
			'egw_acl'            => array('acl_account','acl_location'),
			'egw_addressbook'    => array('contact_owner','contact_creator','contact_modifier','account_id'),
			'egw_addressbook2list' => array('list_added_by'),
			'egw_addressbook_extra' => 'contact_owner',
			'egw_addressbook_lists' => array('list_owner','list_creator'),
			'egw_api_content_history' => 'sync_changedby',
			'egw_applications'   => false,
			'egw_app_sessions'   => 'loginid',
			'egw_async'          => 'async_account_id',
			'egw_categories'     => array(array('cat_owner','cat_owner > 0')),	// -1 are global cats, not cats from group 1!
			'egw_config'         => false,
			'egw_history_log'    => 'history_owner',
			'egw_hooks'          => false,
			'egw_interserv'      => false,
			'egw_lang'           => false,
			'egw_languages'      => false,
			'egw_links'          => 'link_owner',
			'egw_log'            => 'log_user',
			'egw_log_msg'        => false,
			'egw_nextid'         => false,
			'egw_preferences'    => array(array('preference_owner','preference_owner > 0')),
			'egw_sessions'       => false,	// only account_lid stored
			'egw_vfs'            => array('vfs_owner_id','vfs_createdby_id','vfs_modifiedby_id'),	// 'vfs_directory' contains account_lid for /home/account 
		),
		'etemplate' => array(
			'egw_etemplate'      => 'et_group',
		),
		'bookmarks' => array(
			'egw_bookmarks'      => 'bm_owner',
		),
		'calendar' => array(
			'egw_cal'            => array('cal_owner','cal_modifier'),
			'egw_cal_dates'      => false,
			'egw_cal_extra'      => false,
			'egw_cal_holidays'   => false,
			'egw_cal_repeats'    => false,
			'egw_cal_user'       => array(array('cal_user_id','cal_user_type' => 'u')),	// cal_user_id for cal_user_type='u'
		),
		'emailadmin' => array(
			'egw_emailadmin'     => false,
		),
		'felamimail' => array(
			'egw_felamimail_accounts'      => 'fm_owner',
			'egw_felamimail_cache'         => 'fmail_accountid',	// afaik not used in 1.4+
			'egw_felamimail_displayfilter' => 'fmail_filter_accountid',
			'egw_felamimail_folderstatus'  => 'fmail_accountid',	// afaik not used in 1.4+
			'egw_felamimail_signatures'    => 'fm_accountid',
		),
		'infolog' => array(
			'egw_infolog'        => array('info_owner',array('info_responsible','.type' => 'comma-sep'),'info_modifier'),
			'egw_infolog_extra'  => false,
		),
		'news_admin' => array(
			'egw_news'           => 'news_submittedby',
			'egw_news_export'    => false,
		),
		'projectmanager' => array(
			'egw_pm_constraints' => false,
			'egw_pm_elements'    => array('pe_modifier',array('pe_resources','.type' => 'comma-sep')),
			'egw_pm_extra'       => false,
			'egw_pm_members'     => 'member_uid',
			'egw_pm_milestones'  => false,
			'egw_pm_pricelist'   => false,
			'egw_pm_prices'      => 'pl_modifier',
			'egw_pm_projects'    => array('pm_creator','pm_modifier'),
			'egw_pm_roles'       => false,
		),
		'registration' => array(
			'egw_reg_accounts'   => false,
			'egw_reg_fields'     => false,
		),
		'resources' => array(
			'egw_resources'      => false,
			'egw_resources_extra'=> 'extra_owner',
		),
		'sitemgr' => array(
			'egw_sitemgr_active_modules'   => false,
			'egw_sitemgr_blocks'           => false,
			'egw_sitemgr_blocks_lang'      => false,
			'egw_sitemgr_categories_lang'  => false,
			'egw_sitemgr_categories_state' => false,
			'egw_sitemgr_content'          => false,
			'egw_sitemgr_content_lang'     => false,
			'egw_sitemgr_modules'          => false,
			'egw_sitemgr_notifications'    => false,
			'egw_sitemgr_notify_messages'  => false,
			'egw_sitemgr_pages'            => false,
			'egw_sitemgr_pages_lang'       => false,
			'egw_sitemgr_properties'       => false,
			'egw_sitemgr_sites'            => false,
		),
		'syncml' => array(
			'egw_contentmap'        => false,
			'egw_syncmldeviceowner' => false,	// Lars: is owner_devid a account_id???
			'egw_syncmldevinfo'     => false,
			'egw_syncmlsummary'     => false,
		),
		'tracker' => array(
			'egw_tracker'           => array('tr_assigned','tr_creator','tr_modifier'),
			'egw_tracker_bounties'  => array('bounty_creator','bounty_confirmer'),
			'egw_tracker_replies'   => array('reply_creator'),
			'egw_tracker_votes'     => array('vote_uid'),
		),
		'timesheet' => array(
			'egw_timesheet'      => array('ts_owner','ts_modifier'),
			'egw_timesheet_extra'=> false,
		),
		'wiki' => array(
			'egw_wiki_interwiki' => false,
			'egw_wiki_links'     => false,
			'egw_wiki_pages'     => array(array('wiki_readable','wiki_readable < 0'),array('wiki_writable','wiki_writable < 0')),	// only groups
			'egw_wiki_rate'      => false,
			'egw_wiki_remote_pages' => false,
			'egw_wiki_sisterwiki'=> false,
		),
		'phpbrain' => array(	// aka knowledgebase
			'phpgw_kb_articles'  => array('user_id','modified_user_id'),
			'phpgw_kb_comment'   => 'user_id',
			'phpgw_kb_files'     => false,
			'phpgw_kb_questions' => 'user_id',
			'phpgw_kb_ratings'   => 'user_id',
			'phpgw_kb_related_art' => false,
			'phpgw_kb_search'    => false,
			'phpgw_kb_urls'      => false,
		),
		'polls' => array(
			'egw_polls'         => false,
			'egw_polls_answers' => false,
			'egw_polls_votes'   => 'vote_uid',
		),
		'gallery' => array(
			'g2_ExternalIdMap' => array(array('g_externalId',"g_entityType='GalleryUser'")),
		),
		// MyDMS	ToDo!!!
		// VFS2		ToDo!!!
	);
	
	if (count($args) < 4) usage();	// 4 means at least user,pw,from1,to1
	
	$ids2change = array();
	for($n = 2; $n < count($args); $n += 2)
	{
		$from = (int)$args[$n];
		$to   = (int)$args[$n+1];
		if (!$from || !$to)
		{
			fail(16,lang("Account-id's have to be integers!"));
		}
		$ids2change[$from] = $to;
	}
	$total = 0;
	foreach($columns2change as $app => $data)
	{
		$db = clone($GLOBALS['egw']->db);
		$db->set_app($app);
		
		foreach($data as $table => $columns)
		{
			if (!$columns)
			{
				echo "$app: $table no columns with account-id's\n";
				continue;	// noting to do for this table
			}
			if (!is_array($columns)) $columns = array($columns);
			
			foreach($columns as $column)
			{
				$type = $where = null;
				if (is_array($column))
				{
					$type = $column['.type'];
					unset($column['.type']);
					$where = $column;
					$column = array_shift($where);
				}
				$total += ($changed = _update_account_id($ids2change,$db,$table,$column,$where,$type));
				echo "$app: $table.$column $changed id's changed\n";
			}
		}
	}
	echo lang("Total of %1 id's changed.",$total)."\n\n";
	return 0;
}

function _update_account_id($ids2change,$db,$table,$column,$where=null,$type=null)
{
	static $update_sql;
	
	if (is_null($update_sql))
	{
		foreach($ids2change as $from => $to)
		{
			$update_sql .= "WHEN $from THEN $to ";
		}
		$update_sql .= "END";
	}
	switch($type)
	{
		case 'comma-sep':
			if (!$where) $where = array();
			$where[] = "$column IS NOT NULL";
			$where[] = "$column != ''";
			$db->select($table,'DISTINCT '.$column,$where,__LINE__,__FILE__);
			$change = array();
			while(($row = $db->row(true)))
			{
				$ids = explode(',',$old_ids=$row[$column]);
				foreach($ids as $key => $id)
				{
					if (isset($account_id2change[$id])) $ids[$key] = $account_id2change[$id];
				}
				$ids = implode(',',$ids);
				if ($ids != $old_ids)
				{
					$change[$old_ids] = $ids;
				}
			}
			$changed = 0;
			foreach($change as $from => $to)
			{
				$db->update($table,array($column=>$to),$where+array($column=>$from),__LINE__,__FILE__);
				$changed += $db->affected_rows();
			}
			break;
			
		case 'abs':
			if (!$where) $where = array();
			$where[$column] = array();
			foreach(array_keys($ids2change) as $id)
			{
				$where[$column][] = abs($id);
			}
			$db->update($table,$column.'= CASE '.$column.' '.preg_replace('/-([0-9]+)/','\1',$update_sql),$where,__LINE__,__FILE__);
			$changed = $db->affected_rows();
			break;
			
		default:
			if (!$where) $where = array();
			$where[$column] = array_keys($ids2change);
			$db->update($table,$column.'= CASE '.$column.' '.$update_sql,$where,__LINE__,__FILE__);
			$changed = $db->affected_rows();
			break;
	}
	return $changed;
}

/**
 * Exit the script with a numeric exit code and an error-message, does NOT return
 *
 * @param int $exit_code
 * @param string $message
 */
function fail($exit_code,$message)
{
	echo $message."\n";
	exit($exit_code);
}

/**
 * List all exit codes used by the command line interface
 *
 * The list is generated by "greping" this file for calls to the fail() function. 
 * Calls to fail() have to be in one line, to be recogniced!
 */
function list_exit_codes()
{
	error_reporting(error_reporting() & ~E_NOTICE);

	$codes = array('Ok');
	foreach(file(__FILE__) as $n => $line)
	{
		if (preg_match('/fail\(([0-9]+),(.*)\);/',$line,$matches))
		{
			//echo "Line $n: $matches[1]: $matches[2]\n";
			@eval('$codes['.$matches[1].'] = '.$matches[2].';');
		}
	}
	ksort($codes,SORT_NUMERIC);
	foreach($codes as $num => $msg)
	{
		echo $num."\t".str_replace("\n","\n\t",$msg)."\n";
	}
}

/**
 * Read the IMAP ACLs
 *
 * @param array $args admin-account[@domain],admin-password,accout_lid[,pw]
 * @return int 0 on success
 */
function do_subscribe_other($account_lid,$pw=null)
{
	if (!($account_id = $GLOBALS['egw']->accounts->name2id($account_lid)))
	{
		fail(15,lang("Unknown account: %1 !!!",$account_lid));		
	}
	$GLOBALS['egw_info']['user'] = array(
		'account_id' => $account_id,
		'account_lid' => $account_lid,
		'passwd' => $pw,
	);
	include_once(EGW_INCLUDE_ROOT.'/emailadmin/inc/class.bo.inc.php');

	$emailadmin = new bo();
	$user_profile = $emailadmin->getUserProfile('felamimail');
	unset($emailadmin);
	
	$icServer = new cyrusimap();
	//$icServer =& $user_profile->ic_server[0];
	//print_r($icServer);
	
	$icServer->openConnection(!$pw);
	
	$delimiter = $icServer->getHierarchyDelimiter();

	$mailboxes = $icServer->getMailboxes();
	//print_r($mailboxes);
	
	$own_mbox = 'user'.$delimiter.$account_lid;
	
	foreach($mailboxes as $n => $mailbox)
	{
//		if ($n < 1) continue;

		if (substr($mailbox,0,5) != 'user'.$delimiter || substr($mailbox,0,strlen($own_mbox)) == $own_mbox) continue;

		if (!$pw) $mailbox = str_replace('INBOX','user'.$delimiter.$account_lid,$mailbox);

/*		$rights = $icServer->getACL($mailbox);
		echo "getACL($mailbox)\n";
		foreach($rights as $data)
		{
			echo $data['USER'].' '.$data['RIGHTS']."\n";
		}*/
		echo "subscribing $mailbox for $account_lid\n";
		//$icServer->subscribeMailbox($mailbox);
		//exit;
	}
}