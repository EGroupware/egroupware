<?php
/**
 * eGgroupWare admin - remote admin command execution
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

/**
 * @var array
 */
$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'login',
		'noheader' => true,
	)
);

include('../header.inc.php');

$GLOBALS['egw']->applications->read_installed_apps();	// set $GLOBALS['egw_info']['apps'] (not set for login)

$instance = isset($_GET['domain']) ? $_GET['domain'] : $_REQUEST['domain'];	// use GET before the rest
if (!isset($GLOBALS['egw_domain'][$instance]))
{
	$instance = $GLOBALS['egw_info']['server']['default_domain'];
}
$domain_data = $GLOBALS['egw_domain'][$instance];
//echo $instance; _debug_array($domain_data);

// as a security measure remote administration need to be enabled under Admin > Site configuration
list(,$remote_admin_install_id) = explode('-',$_REQUEST['uid']);
$allowed_remote_admin_ids = $GLOBALS['egw_info']['server']['allow_remote_admin'] ? explode(',',$GLOBALS['egw_info']['server']['allow_remote_admin']) : array();
// to authenticate with the installation we use a secret, which is a md5 hash build from the uid 
// of the command (to not allow to send new commands with an earsdroped secret) and the md5 hash 
// of the md5 hash of the config password and the install_id (egw_admin_remote.remote_hash)
if (!$domain_data || is_numeric($_REQUEST['uid']) || !in_array($remote_admin_install_id,$allowed_remote_admin_ids) ||
	$_REQUEST['secret'] != ($md5=md5($_REQUEST['uid'].admin_cmd::remote_hash($GLOBALS['egw_info']['server']['install_id'],$domain_data['config_passwd']))))
{
	header("HTTP/1.1 200 Unauthorized");
	//die("0 secret != '$md5'");
	echo lang('0 Permission denied!');
	if (!in_array($remote_admin_install_id,$allowed_remote_admin_ids))
	{
		echo "\n".lang('Remote administration need to be enabled in the remote instance under Admin > Site configuration!');
	}
	$GLOBALS['egw']->common->egw_exit();
}

require_once(EGW_INCLUDE_ROOT.'/admin/inc/class.admin_cmd.inc.php');

// check if uid belongs to an existing command --> return it's status
// this is also a security meassure, as a captured uid+secret can not be used to send new commands
$cmd = admin_cmd::read($_REQUEST['uid']);
if (is_object($cmd))
{
	exit_with_status($cmd);
}

// check if requests contains a reasonable looking admin command to be queued
if (!$_REQUEST['uid'] ||	// no uid
	!$_REQUEST['type'] ||	// no command class name
	!$_REQUEST['creator_email'])	// no creator email
{
	header("HTTP/1.1 200 Bad format!");
	echo lang('0 Bad format!');
	$GLOBALS['egw']->common->egw_exit();
}

// create command from request data
$data = isset($_POST['uid']) ? $_POST : $_GET;
unset($data['secret']);
unset($data['id']);			// we are remote
unset($data['remote_id']);
$data['creator'] = 0;	// remote
if (isset($data['modifier'])) $data['modifier'] = 0;
if (isset($data['requested'])) $data['requested'] = 0;

// instanciate comand and run it
try {
	$cmd = admin_cmd::instanciate($data);
	//_debug_array($cmd); exit;
	$success_msg = $cmd->run();
	
	$GLOBALS['egw']->translation->convert($success_msg,$GLOBALS['egw']->translation->charset(),'utf-8');

	if (!is_string($success_msg))
	{
		$success_msg = serialize($success_msg);
	}
}
catch (Exception $e) {
	header('HTTP/1.1 200 '.$e->getMessage());
	echo $e->getCode().' '.$e->getMessage();
	$GLOBALS['egw']->common->egw_exit();
}
exit_with_status($cmd,$success_msg);

function exit_with_status($cmd,$success_msg='Successful')
{
	switch($cmd->status)
	{
		case admin_cmd::failed:	// errors are returned as 400 HTTP status
			header('HTTP/1.1 200 '.$cmd->error);
			echo $cmd->errno.' '.$cmd->error;
			break;
			
		default:				// everything else is returned as 200 HTTP status
			$success_msg = $cmd->stati[$cmd->status];
			// fall through
		case admin_cmd::successful:
			header('HTTP/1.1 200 '.$cmd->stati[$cmd->status]);
			header('Content-type: text/plain; charset=utf-8');
			echo $success_msg;
	}
	$GLOBALS['egw']->common->egw_exit();
}
