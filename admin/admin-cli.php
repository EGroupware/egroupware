#!/usr/bin/env php
<?php
/**
 * Admin - Command line interface
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Vfs;

chdir(dirname(__FILE__));	// to enable our relative pathes to work

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling admin-cli as web-page
{
	die('<h1>admin-cli.php must NOT be called as web-page --> exiting !!!</h1>');
}
elseif ($_SERVER['argc'] <= 1 || $_SERVER['argc'] == 2 && in_array($_SERVER['argv'][1], array('-h', '--help')))
{
	usage();
}
elseif ($_SERVER['argv'][1] == '--exit-codes')
{
	list_exit_codes();
	exit(0);
}
else
{
	$arguments = $_SERVER['argv'];
	array_shift($arguments);
	$action = array_shift($arguments);
}

// allow to specify instance by using a username with appended @domain-name
$arg0s = explode(',',@array_shift($arguments));
@list($user,$domain) = explode('@',$arg0s[0].'@');
load_egw($user, @$arg0s[1], $domain);

switch($action)
{
	case '--edit-user':
		return do_edit_user($arg0s);

	case '--add-user':	// like --edit-account, but always runs addaccount hook
		return do_edit_user($arg0s,true);

	case '--edit-alias':
	case '--edit-forward':
	case '--edit-quota':
		return do_edit_mail(substr($action, 7), $arg0s);

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

	/* ToDo: get this working again
	case '--subscribe-other':
		return do_subscribe_other($arg0s[2],$arg0s[3]);
	*/
	case '--check-acl';
		return do_check_acl();

	case '--show-header';
		return run_command(new setup_cmd_showheader($arg0s[2]));

	default:
		// we allow to call admin_cmd classes directly, if they define the constant SETUP_CLI_CALLABLE
		if (substr($action,0,2) == '--' && (class_exists($class = str_replace('-','_',substr($action, 2))) ||
			class_exists($class = preg_replace('/^--([a-z0-9_]+)-([a-z0-9_]+)$/i', 'EGroupware\\$1\\$2', $action)) ||
			class_exists($class = preg_replace('/^--([a-z0-9_]+)-([a-z0-9_]+)$/i', 'EGroupware\\$1\\AdminCmds\\$2', $action))) &&
			is_subclass_of($class,'admin_cmd') && @constant($class.'::SETUP_CLI_CALLABLE'))
		{
			$args = array();
			$args['domain'] = array_shift($arg0s);	// domain must be first argument, to ensure right domain get's selected in header-include
			foreach($arg0s as $arg)
			{
				list($name,$value) = explode('=',$arg,2);
				if(property_exists('admin_cmd',$name))		// dont allow to overwrite admin_cmd properties
				{
					throw new Api\Exception\WrongUserinput(lang("Invalid argument '%1' !!!",$arg),90);
				}
				if (substr($name,-1) == ']')	// allow 1-dim. arrays
				{
					list($name,$sub) = explode('[',substr($name,0,-1),2);
					if (empty($sub))
					{
						$args[$name][] = $value;
					}
					else
					{
						$args[$name][$sub] = $value;
					}
				}
				else
				{
					$args[$name] = $value;
				}
			}
			return run_command(new $class($args));
		}
		usage($action, 1);
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
	global $arguments,$user,$arg0s,$domain;

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

			case '--skip-checks':	// do not yet run the checks for scheduled local commands
				$skip_checks = true;
				break;

			case '--try-run':	// only run checks
			case '--dry-run':	// only run checks
				$dry_run = true;
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
	if ($dry_run && $skip_checks)
	{
		echo lang('You can NOT use --dry-run together with --skip-checks!')."\n\n";
		usage('', 99);
	}
	//_debug_array($cmd);
	try {
		$msg = $cmd->run($time, true, $skip_checks, $dry_run);
		if (!is_bool($msg) && $msg) print_r($msg);

		// cli can NOT clear instance cache of APC(u), as cli uses different shared memory then webserver
		// --> we use a webservice call to clear cache (might fail if no domain in specified in webserver_url or on command line)
		if (!$dry_run)
		{
			$url = $GLOBALS['egw_info']['server']['webserver_url'].'/json.php?menuaction=admin.admin_hooks.ajax_clear_cache';
			if ($url[0] == '/') $url = 'http://'.(!empty($domain) && $domain != 'default' ? $domain : 'localhost').$url;
			$data = file_get_contents($url, false, Framework::proxy_context($user,$arg0s[1]));
			//error_log("file_get_contents('$url') returned ".array2string($data));
			if ($data && strpos($data, '"success"') !== false)
			{
				//error_log('Instance cache cleared.');
			}
			else
			{
				error_log('You might need to clear the cache for changes to become visible: Admin >> Clear cache!');
			}
		}
	}
	catch (Api\Exception $e) {
		echo "\n".$e->getMessage()."\n\n";
		exit($e->getCode());
	}
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
 * Start the eGW session, exits on wrong credintials
 *
 * @param string $user
 * @param string $passwd
 * @param string $domain
 */
function load_egw($user,$passwd,$domain='default')
{
	//echo "load_egw($user,$passwd,$domain)\n";
	$_REQUEST['domain'] = $domain;
	$GLOBALS['egw_login_data'] = array(
		'login'  => $user,
		'passwd' => $passwd,
		'passwd_type' => 'text',
	);

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

	if (substr($user,0,5) != 'root_')
	{
		include('../header.inc.php');
	}
	else
	{
		$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
		include('../header.inc.php');

		if ($user == 'root_'.$GLOBALS['egw_info']['server']['header_admin_user'] &&
			_check_pw($GLOBALS['egw_info']['server']['header_admin_password'],$passwd) ||
			$user == 'root_'.$GLOBALS['egw_domain'][$_GET['domain']]['config_user'] &&
			_check_pw($GLOBALS['egw_domain'][$_GET['domain']]['config_passwd'],$passwd))
		{
			echo "\nRoot access granted!\n";
			Vfs::$is_root = true;
		}
		else
		{
			die("Unknown user or password!\n");
		}
	}
}

/**
 * Check password against a md5 hash or cleartext password
 *
 * @param string $hash_or_cleartext
 * @param string $pw
 * @return boolean
 */
function _check_pw($hash_or_cleartext,$pw)
{
	return Api\Auth::compare_password($pw, $hash_or_cleartext,
		// old header.inc.php allows md5 or plain passwords with out {type} prefix, which takes precedence
		preg_match('/^[0-9a-f]{32}$/', $hash_or_cleartext) ? 'md5' : 'plain');
}

/**
 * Give a usage message and exit
 *
 * @param string $action =null
 * @param int $ret =0 exit-code
 */
function usage($action=null,$ret=0)
{
	unset($action);
	$cmd = basename($_SERVER['argv'][0]);
	echo "Usage: $cmd --command admin-account[@domain],admin-password,options,... [--schedule {YYYY-mm-dd|+1 week|+5 days}] [--requested 'Name <email>'] [--comment 'comment ...'] [--remote {id|name}] [--skip-checks] [--try-run]\n\n";

	echo "\n\t--skip-checks\tdo NOT run checks\n";
	echo "\t--try-run\tonly run checks\n";

	echo "\tAlternativly you can also use a setup user and password by prefixing it with 'root_', eg. 'root_admin' for setup user 'admin'.\n\n";

	echo "--edit-user admin-account[@domain],admin-password,account[=new-account-name],first-name,last-name,password,email,expires{never(default)|YYYY-MM-DD|already},can-change-pw{yes(default)|no},anon-user{yes|no(default)},primary-group{Default(default)|...}[,groups,...][,homedirectory,loginshell]\n";
	echo "	Edit or add a user to EGroupware. If you specify groups, they *replace* the exiting memberships! homedirectory+loginshell are supported only for LDAP and must start with a slash!\n";
	echo "--change-pw admin-account[@domain],admin-password,account,password\n";
	echo "  Change/set the password for a given user\n";
	echo "--delete-user admin-account[@domain],admin-password,account-to-delete[,account-to-move-data]\n";
	echo "	Deletes a user from EGroupware. It's data can be moved to an other user or it get deleted too.\n";
	echo "	You can use '--not-existing' for accounts-to-delete, to delete all no (longer) existing users and groups.\n";
	echo "--edit-group admin-account[@domain],admin-password,group[=new-group-name],email[,members,...]\n";
	echo "	Edit or add a group to EGroupware. If you specify members, they *replace* the exiting members!\n";
	echo "--delete-group admin-account[@domain],admin-password,group-to-delete\n";
	echo "	Deletes a group from EGroupware.\n";
	echo "--allow-app admin-account[@domain],admin-password,account,application,...\n";
	echo "--deny-app admin-account[@domain],admin-password,account,application,...\n";
	echo "	Give or deny an account (user or group specified by account name or id) run rights for the given applications.\n";
	echo "--change-account-id admin-account[@domain],admin-password,from1,to1[...,fromN,toN]\n";
	echo "	Changes one or more account_id's in the database (make a backup before!).\n";
	echo "--check-acl admin-account[@domain],admin-password\n";
	echo "	Deletes ACL entries of not longer existing accounts (make a database backup before! --> setup-cli.php).\n";
	echo "--admin-cmd-check-cats admin-account[@domain],admin-password\n";
	echo "	Deletes categories of not longer existing accounts.\n";
	echo "--edit-alias admin-account[@domain],admin-password,account[=acc_id],create-identity(yes,no/default),[+/-]alias1,...\n";
	echo "--edit-forward admin-account[@domain],admin-password,account[=acc_id],mode(forwardOnly),[+/-]forward1,...\n";
	echo "--edit-quota admin-account[@domain],admin-password,account[=acc_id],quota(mb)\n";
	echo "  Edit mail account of EGroupware managed mail-server for a given user and optional acc_id (can't be scheduled or try-run)\n";
	echo "--exit-codes admin-account[@domain],admin-password\n";
	echo "	List all exit codes of the command line interface\n";

	exit($ret);
}

/**
 * Edit mail account of EGroupware managed mail-server
 *
 * @param string $type "alias", "forward", "quota"
 * @param array $arg0s admin-account[@domain],admin-password,account[=acc_id],...
 *	- alias:   create-identity(yes,no/default),[+/-]alias1,...aliasN
 *	- forward: mode(forwardOnly),[+/-]forward1,...forwardN
 *	- quota:   quota(mb)
 * @return int 0 on success
 */
function do_edit_mail($type, array $arg0s)
{
	array_shift($arg0s); // admin-account
	array_shift($arg0s);	// admin-pw
	list($account, $acc_id) = explode('=', array_shift($arg0s));
	$account_id = is_numeric($account) ? (int)$account : $GLOBALS['egw']->accounts->name2id($account);
	if (!$GLOBALS['egw']->accounts->exists($account_id) && !($account_id = $GLOBALS['egw']->accounts->name2id($account)))
	{
		echo "Unknown user-account '$account'!\n";
		exit(1);
	}
	$found = 0;
	foreach($acc_id ? array(Api\Mail\Account::read($acc_id, $account_id)) :
		Api\Mail\Account::search($account_id, false) as $account)
	{
		if (!isset($acc_id) && !Api\Mail\Account::is_multiple($account)) continue;	// no need to waste time on personal accounts

		$args = $arg0s;
		try {
			if (!($data = $account->getUserData($account_id)))
			{
				continue;	// not a managed mail-server
			}
			switch($type)
			{
				case 'alias':
					$create_identity = strtolower(array_shift($args)) === 'yes';
					$delete_identity = $args[0][0] == '-';
					array_modify($data['mailAlternateAddress'], $args);
					break;
				case 'forward':
					$data['deliveryMode'] = strtolower(array_shift($args)) === 'forwardonly' ? Api\Mail\Smtp::FORWARD_ONLY : '';
					array_modify($data['mailForwardingAddress'], $args);
					break;
				case 'quota':
					$data['quotaLimit'] = int($args[0]);
					break;
			}
			$account->saveUserData($account_id, $data);
			echo "Data in mail-account (acc_id=$account->acc_id) updated.\n";
			++$found;

			// create identities for all aliases
			if ($type == 'alias' && $create_identity && $args)
			{
				// check if user allready has an identity created for given aliases
				foreach(Api\Mail\Account::identities($account, false, 'ident_email', $account_id) as $ident_id => $email)
				{
					if (($key = array_search($email, $args)) !== false)
					{
						// delete identities, if "-" is used and email of identity matches given ones and is not standard identity
						if ($delete_identity && $ident_id != $account->ident_id)
						{
							Api\Mail\Account::delete_identity($ident_id);
						}
						unset($args[$key]);
					}
				}
				// create not existing identities by copying standard identity plus alias as email
				foreach($args as $email)
				{
					$identity = $account->params;
					unset($identity['ident_id']);
					unset($identity['ident_name']);
					$identity['ident_email'] = $email;
					$identity['account_id'] = $account_id;	// make this a personal identity for $account_id
					Api\Mail\Account::save_identity($identity);
				}
				if ($args) echo "Identity(s) for ".implode(', ', $args)." created.\n";
			}
		}
		catch(\Exception $e) {
			_egw_log_exception($e);
			echo $e->getMessage()."\n";
		}
	}
	if (!$found)
	{
		echo "No mailserver managed by this EGroupware instance!\n";
		exit(2);
	}
	exit(0);
}

/**
 * Set, add or remove from array depending on $mod[0][0] being '+', '-' or something else (set)
 *
 * @param array& $arr
 * @param array& $mod eg. ["+some-alias@egroupware.org","other-alias@egroupware.org"] will add all given alias to $arr
 *  on return optional +/- prefix has been removed
 * @return array
 */
function array_modify(&$arr, array &$mod)
{
	if (!is_array($arr)) $arr = array();

	switch($mod[0][0])
	{
		case '-':
			$mod[0] = substr($mod[0], 1);
			$arr = array_values(array_unique(array_diff($arr, $mod)));
			break;

		case '+';
			$mod[0] = substr($mod[0], 1);
			$arr = array_values(array_unique(array_merge($arr, $mod)));
			break;

		default:
			$arr = array_values(array_unique($mod));
	}
	return $arr;
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
 * Edit or add a group to EGroupware. If you specify members, they *replace* the exiting member!
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

		foreach($data as &$value)	// existing account --> empty values mean dont change, not set them empty!
		{
			if ((string)$value === '') $value = null;
		}
	}
	catch (Exception $e) {	// new group
		unset($e);	// not used
		$data['account_lid'] = $account;
		$account = false;
	}
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
 * Edit or add a user to EGroupware. If you specify groups, they *replace* the exiting memberships!
 *                    1:                     2:             3:                         4:         5:        6:       7:    8:                                         9:                                 10:                            11:                                  12
 * @param array $args admin-account[@domain],admin-password,account[=new-account-name],first-name,last-name,password,email,expires{never(default)|YYYY-MM-DD|already},can-change-pw{true(default)|false},anon-user{true|false(default)},primary-group{Default(default)|...}[,groups,...][,homedirectory,loginshell]
 * @param boolean $run_addaccount_hook =null default run hook depending on account existence, true=allways run addaccount hook
 */
function do_edit_user($args,$run_addaccount_hook=null)
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

		foreach($data as &$value)	// existing account --> empty values mean dont change, not set them empty!
		{
			if ((string)$value === '') $value = null;
		}
	}
	catch (Exception $e) {	// new account
		unset($e);	// not used
		$data['account_lid'] = $account;
		$account = false;
	}
	run_command(new admin_cmd_edit_user($account,$data,null,$run_addaccount_hook));
}

/**
 * Delete a given acount from eGW
 *
 * @param int/string $account account-name of -id
 * @param int/string $new_user =0 for users only: account to move the entries too
 * @param boolean $is_user =true are we called for a user or group
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
 * The list is generated by "greping" this file for thrown exceptions.
 * Exceptions have to be in one line, to be recogniced!
 */
function list_exit_codes()
{
	error_reporting(error_reporting() & ~E_NOTICE);

	if (!function_exists('lang'))
	{
		function lang($str)
		{
			return $str;
		}
	}

	$codes = array();
	$files = array('admin-cli.php');
	foreach(scandir(__DIR__.'/inc') as $file)
	{
		if (substr($file,0,strlen('class.admin_cmd')) == 'class.admin_cmd')
		{
			$files[] = 'inc/'.$file;
		}
	}
	foreach($files as $file)
	{
		$content = file_get_contents(__DIR__.'/'.$file);

		$matches = null;
		if (preg_match_all('/throw new (Api\\\\Exception[\\\\a-z_]*)\((.*),\\s*([0-9]+)\);/mi',$content,$matches))
		{
			//echo $file.":\n"; print_r($matches);
			foreach($matches[3] as $key => $code)
			{
				$src = preg_replace('/(self::)?\$[a-z_>-]+/i', "''", $matches[2][$key]);	// gives fatal error otherwise
				@eval('$src = '.$src.';');

				if (!empty($src) && (!isset($codes[$code]) || !in_array($src, $codes[$code])))
				{
					//if (isset($codes[$code])) echo "$file redefines #$code: ".implode(', ', $codes[$code])."\n";
					$codes[$code][] = $src;
				}
			}
		}
	}
	$codes[0] = 'Ok';
	ksort($codes, SORT_NUMERIC);
	foreach($codes as $num => $msgs)
	{
		echo $num."\t".str_replace("\n","\n\t", implode(', ', (array)$msgs))."\n";
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
	unset($account_lid, $pw);
	/* ToDo: this cant work, not even in 14.x
	if (!($account_id = $GLOBALS['egw']->accounts->name2id($account_lid)))
	{
		throw new Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$account_lid),15);
	}
	$GLOBALS['egw_info']['user'] = array(
		'account_id' => $account_id,
		'account_lid' => $account_lid,
		'passwd' => $pw,
	);
	$emailadmin = new emailadmin_bo();
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

		//$rights = $icServer->getACL($mailbox);
		//echo "getACL($mailbox)\n";
		//foreach($rights as $data)
		//{
		//	echo $data['USER'].' '.$data['RIGHTS']."\n";
		//}
		echo "subscribing $mailbox for $account_lid\n";
		//$icServer->subscribeMailbox($mailbox);
		//exit;
	}*/
}
