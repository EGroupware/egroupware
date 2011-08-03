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

// install an own exception handler to forward exceptions back to the remote side
function remote_exception_handler(Exception $e) 
{
	$msg = $e->getMessage();
	if (is_object($GLOBALS['egw']->translation))
	{
		$msg = $GLOBALS['egw']->translation->convert($msg,$GLOBALS['egw']->translation->charset(),'utf-8');
	}
	header('HTTP/1.1 200 '.$msg);
	echo $e->getCode().' '.$msg;
	$GLOBALS['egw']->common->egw_exit();
}
set_exception_handler('remote_exception_handler');

$GLOBALS['egw']->applications->read_installed_apps();	// set $GLOBALS['egw_info']['apps'] (not set for login)

$instance = isset($_GET['domain']) ? $_GET['domain'] : $_REQUEST['domain'];	// use GET before the rest
if (!isset($GLOBALS['egw_domain'][$instance]))
{
	$instance = $GLOBALS['egw_info']['server']['default_domain'];
}
$config_passwd = $GLOBALS['egw_domain'][$instance]['config_passwd'];
unset($GLOBALS['egw_domain']);

require_once(EGW_INCLUDE_ROOT.'/admin/inc/class.admin_cmd.inc.php');

// check if uid belongs to an existing command --> return it's status
// this is also a security meassure, as a captured uid+secret can not be used to send new commands
$cmd = admin_cmd::read($_REQUEST['uid']);
if (is_object($cmd))
{
	$cmd->check_remote_access($_REQUEST['secret'],$config_passwd);

	$success_msg = 'Successful';
	// if the comand object has a rerun method, call it
	if (method_exists($cmd,'rerun'))
	{
		$success_msg = $cmd->rerun();
	}
	exit_with_status($cmd,$success_msg);
}

// check if requests contains a reasonable looking admin command to be queued
if (!$_REQUEST['uid'] ||	// no uid
	!$_REQUEST['type'] ||	// no command class name
	!preg_match('/^[a-z0-9_]+$/i', $_REQUEST['type']) ||	// type is a (autoloadable) class name, prevent inclusion of arbitrary files
	!$_REQUEST['creator_email'])	// no creator email
{
	header("HTTP/1.1 200 Bad format!");
	echo '0 Bad format!';
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

if (get_magic_quotes_gpc())
{
	$data = array_stripslashes($data);
}

$cmd = admin_cmd::instanciate($data);

$cmd->check_remote_access($_REQUEST['secret'],$config_passwd);

//_debug_array($cmd); exit;
$success_msg = $cmd->run();

$GLOBALS['egw']->translation->convert($success_msg,$GLOBALS['egw']->translation->charset(),'utf-8');

if (!is_string($success_msg))
{
	$success_msg = serialize($success_msg);
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
		case admin_cmd::pending:
		case admin_cmd::successful:
			header('HTTP/1.1 200 '.$cmd->stati[$cmd->status]);
			header('Content-type: text/plain; charset=utf-8');
			echo $success_msg;
	}
	$GLOBALS['egw']->common->egw_exit();
}
