#!/usr/bin/php -qC 
<?php
/**
 * Admin - Command line interface
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
$arg0s = explode(',',@$arguments[0]);
@list(,$_GET['domain']) = explode('@',$arg0s[0]);

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
	case '--delete-user':
		return do_delete_user($arg0s[2],$arg0s[3]);

	default:
		usage($action);
		break;
}
exit(0);

/**
 * callback if the session-check fails, redirects via xajax to login.php
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
		echo "Wrong admin-account or -password !!!\n\n";
		usage('',1);
	}
	if (!$GLOBALS['egw_info']['user']['apps']['admin'])	// will be tested by the header too, but whould give html error-message
	{
		echo "Permission denied !!!\n\n";
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
	echo "Usage: $cmd command [additional options]\n\n";
	
	echo "--delete-user admin-account[@domain],admin-password,account-to-delete[,account-to-move-data]\n";
	echo "	Deletes a user from eGroupWare. It's data can be moved to an other user or it get deleted too.\n";
	exit;	
}

/**
 * Delete a given user from eGW
 *
 * @param int/string $user
 * @param int/string $new_user=0
 * @return int 0 on success, 2-4 otherwise (see source)
 */
function do_delete_user($user,$new_user=0)
{
	//echo "do_delete_user('$user','$new_user')\n";
	if ($GLOBALS['egw']->acl->check('account_access',32,'admin'))	// user is explicitly forbidden to delete users
	{
		echo "Permission denied !!!\n";
		return 2;
	}
	if (!is_numeric($user) && !($uid = $GLOBALS['egw']->accounts->name2id($lid=$user)) ||
	     is_numeric($user) && !($lid = $GLOBALS['egw']->accounts->id2name($uid=$user)))
	{
		echo "Unknown user to delete: $user !!!\n";
		return 3;
	}
	if ($new_user && (!is_numeric($new_user) && !($new_uid = $GLOBALS['egw']->accounts->name2id($new_user)) ||
	                   is_numeric($new_user) && !$GLOBALS['egw']->accounts->id2name($new_uid=$new_user)))
	{
		echo "Unknown user to move to: $new_user !!!\n";
		return 4;
	}
	// delete the suer
	$GLOBALS['hook_values'] = array(
		'account_id'  => $uid,
		'account_lid' => $lid,
		'new_owner'   => (int)$new_uid,
		'location'    => 'deleteaccount',
	);
	// first all other apps, then preferences and admin
	foreach(array_merge(array_diff(array_keys($GLOBALS['egw_info']['apps']),array('preferences','admin')),array('preferences','admin')) as $app)
	{
		$GLOBALS['egw']->hooks->single($GLOBALS['hook_values'],$app);
	}
	echo "Account '$user' deleted.\n";
	return 0;
}
