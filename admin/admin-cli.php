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
@list(,$_REQUEST['domain']) = explode('@',$arg0s[0]);

if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
{
	ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'admin',
		'noheader' => true,
		'autocreate_session_callback' => 'user_pass_from_argv',
		'no_exception_handler' => 'cli',
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
		return do_delete_account($arg0s[2],0,false);

	case '--allow-app':
	case '--deny-app':
		return do_account_app($arg0s,$action == '--allow-app');

	case '--change-account-id':
		return do_change_account_id($arg0s);

	case '--subscribe-other':
		return do_subscribe_other($arg0s[2],$arg0s[3]);

	case '--check-acl';
		return do_check_acl();

	case '--show-header';
		return run_command(new setup_cmd_showheader($arg0s[2]));

	case '--exit-codes':
		return list_exit_codes();

	default:
		// we allow to call admin_cmd classes directly, if they define the constant SETUP_CLI_CALLABLE
		if (substr($action,0,2) == '--' && class_exists($class = str_replace('-','_',substr($action,2))) &&
			is_subclass_of($class,'admin_cmd') && @constant($class.'::SETUP_CLI_CALLABLE'))
		{
			$args = array();
			$args['domain'] = array_shift($arg0s);	// domain must be first argument, to ensure right domain get's selected in header-include
			foreach($arg0s as $arg)
			{
				list($name,$value) = explode('=',$arg,2);
				if(property_exists('admin_cmd',$name))		// dont allow to overwrite admin_cmd properties
				{
					throw new egw_exception_wrong_userinput(lang("Invalid argument '%1' !!!",$arg),90);
				}
				if (substr($name,-1) == ']')	// allow 1-dim. arrays
				{
					list($name,$sub) = explode('[',substr($name,0,-1),2);
					$args[$name][$sub] = $value;
				}
				else
				{
					$args[$name] = $value;
				}
			}
			return run_command(new $class($args));
		}
		usage($action);
		break;
}
exit(0);

/**
 * run a command object, after checking for additional arguments: sheduled, requested or comment
 *
 * Does not return! Echos success or error messsage and exits with either 0 (success) or the numerical error-code
 *
 * @param admin_cmd $cmd
 */
function run_command(admin_cmd $cmd)
{
	global $arguments;

	$skip_checks = false;
	while ($arguments && ($extra = array_shift($arguments)))
	{
		switch($extra)
		{
			case '--schedule':	// schedule the command instead of running it directly
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

			case '--skip-checks':	//do not yet run the checks for scheduled local commands
				$skip_checks = true;
				break;

			case '--header-access':
				if ($cmd instanceof setup_cmd)
				{
					list($user,$pw) = explode(',',array_shift($arguments),2);
					$cmd->set_header_secret($user,$pw);
				}
				break;

			default:
				//fail(99,lang('Unknown option %1',$extra);
				echo lang('Unknown option %1',$extra)."\n\n";
				usage('',99);
				break;
		}
	}
	//_debug_array($cmd);
	print_r($cmd->run($time,true,$skip_checks));
	echo "\n";

	exit(0);
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
	echo "Usage: $cmd --command admin-account[@domain],admin-password,options,... [--schedule {YYYY-mm-dd|+1 week|+5 days}] [--requested 'Name <email>'] [--comment 'comment ...'] [--remote {id|name}] [--skip-checks]\n\n";

	echo "--edit-user admin-account[@domain],admin-password,account[=new-account-name],first-name,last-name,password,email,expires{never(default)|YYYY-MM-DD|already},can-change-pw{yes(default)|no},anon-user{yes|no(default)},primary-group{Default(default)|...}[,groups,...][,homedirectory,loginshell]\n";
	echo "	Edit or add a user to eGroupWare. If you specify groups, they *replace* the exiting memberships! homedirectory+loginshell are supported only for LDAP and must start with a slash!\n";
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
	run_command(new admin_cmd_account_app($allow,$account,$args));
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

	$data = array(
		'account_lid' => $new_account_name,
		'account_email' => array_shift($args),
		'account_members' => $args,
	);
	try {
		admin_cmd::parse_account($account,false);

		foreach($data as $name => &$value)	// existing account --> empty values mean dont change, not set them empty!
		{
			if ((string)$value === '') $value = null;
		}
	}
	catch (Exception $e) {	// new group
		$data['account_lid'] = $account;
		$account = false;
	};
	run_command(new admin_cmd_edit_group($account,$data));
}

/**
 * Change/Set Password for a given user
 *                    1:                     2:             3:      4:
 * @param array $args admin-account[@domain],admin-password,account,password
 */
function do_change_pw($args)
{
	array_shift($args);     // admin-account
	array_shift($args);     // admin-pw
	$account = array_shift($args);	// account
	$password = array_shift($args);	// pw

	run_command(new admin_cmd_change_pw($account,$password));
}

/**
 * Edit or add a user to eGroupWare. If you specify groups, they *replace* the exiting memberships!
 *                    1:                     2:             3:                         4:         5:        6:       7:    8:                                         9:                                 10:                            11:                                  12
 * @param array $args admin-account[@domain],admin-password,account[=new-account-name],first-name,last-name,password,email,expires{never(default)|YYYY-MM-DD|already},can-change-pw{true(default)|false},anon-user{true|false(default)},primary-group{Default(default)|...}[,groups,...][,homedirectory,loginshell]
 */
function do_edit_user($args)
{
	array_shift($args);	// admin-account
	array_shift($args);	// admin-pw
	list($account,$new_account_name) = explode('=',array_shift($args));	// account[=new-account-name]

	$data = array();
	// do we need to support ldap only attributes: homedirectory and loginshell
	if (($GLOBALS['egw_info']['server']['account_repository'] == 'ldap' ||
		 empty($GLOBALS['egw_info']['server']['account_repository']) && $GLOBALS['egw_info']['server']['auth_type'] == 'ldap') &&
		$GLOBALS['egw_info']['server']['ldap_extra_attributes'] && count($args) > 9 &&	// 9 = primary group
		($last_arg = array_pop($dummy=$args)) && $last_arg[0] == '/')	// last argument start with a slash
	{
		$data['loginshell'] = array_pop($args);
		$data['homedirectory'] = array_pop($args);
	}
	$data += array(
		'account_lid' => $new_account_name,
		'account_firstname' => array_shift($args),
		'account_lastname' => array_shift($args),
		'account_passwd' => array_shift($args),
		'account_email' => array_shift($args),
		'account_expires' => array_shift($args),
		'changepassword' => array_shift($args),
		'anonymous' => array_shift($args),
		'account_primary_group' => array_shift($args),
		'account_groups' => $args,
	);
	try {
		admin_cmd::parse_account($account,true);

		foreach($data as $name => &$value)	// existing account --> empty values mean dont change, not set them empty!
		{
			if ((string)$value === '') $value = null;
		}
	}
	catch (Exception $e) {	// new account
		$data['account_lid'] = $account;
		$account = false;
	};
	run_command(new admin_cmd_edit_user($account,$data));
}

/**
 * Delete a given acount from eGW
 *
 * @param int/string $account account-name of -id
 * @param int/string $new_user=0 for users only: account to move the entries too
 * @param boolean $is_user=true are we called for a user or group
 * @return int 0 on success, 2-4 otherwise (see source)
 */
function do_delete_account($account,$new_user=0,$is_user=true)
{
	run_command(new admin_cmd_delete_account($account,$new_user,$is_user));
}

/**
 * Deletes ACL entries of not longer existing accounts
 *
 * @return int 0 allways
 */
function do_check_acl()
{
	run_command(new admin_cmd_check_acl());
}

/**
 * Changes one or more account_id's in the database (make a backup before!).
 *
 * @param array $args admin-account[@domain],admin-password,from1,to1[...,fromN,toN]
 * @return int 0 on success
 */
function do_change_account_id($args)
{
	if (count($args) < 4) usage();	// 4 means at least user,pw,from1,to1

	$ids2change = array();
	for($n = 2; $n < count($args); $n += 2)
	{
		$from = (int)$args[$n];
		$to   = (int)$args[$n+1];
		$ids2change[$from] = $to;
	}
	run_command(new admin_cmd_change_account_id($ids2change));
}

/**
 * List all exit codes used by the command line interface
 *
 * The list is generated by "greping" this file for calls to the fail() function.
 * Calls to fail() have to be in one line, to be recogniced!
 *
 * @ToDo adapt it to the exceptions
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
		throw new egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$account_lid),15);
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
