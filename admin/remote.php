<?php
/**
 * eGgroupWare admin - remote admin comand execution
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'login',
		'noheader' => true,
	)
);

include('../header.inc.php');

$GLOBALS['egw']->applications->read_installed_apps();	// set $GLOBALS['egw_info']['apps'] (not set for login)

$instance = $_REQUEST['domain'];
if (!isset($GLOBALS['egw_domain'][$instance]))
{
	$instance = $GLOBALS['egw_info']['server']['default_domain'];
}
$domain_data = $GLOBALS['egw_domain'][$instance];

if (!$domain_data || is_numeric($_REQUEST['uid']) ||
	$_REQUEST['secret'] != ($md5=md5($_REQUEST['uid'].$GLOBALS['egw_info']['server']['install_id'].$domain_data['config_password'])))
{
	header("HTTP/1.1 401 Unauthorized");
	//die("secret != '$md5'");
	echo lang('Permission denied!');
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
	header("HTTP/1.1 400 Bad format!");
	echo lang('Bad format!');
	$GLOBALS['egw']->common->egw_exit();
}

// create command from request data
$data = isset($_POST['uid']) ? $_POST : $_GET;
unset($data['secret']);
unset($data['id']);		// we are remote
$data['creator'] = 0;	// remote
if (isset($data['modifier'])) $data['modifier'] = 0;
if (isset($data['requested'])) $data['requested'] = 0;

// instanciate comand and run it
try {
	$cmd = admin_cmd::instanciate($data);
	//_debug_array($cmd); exit;
	$success_msg = $cmd->run($data['sheduled']);
}
catch (Exception $e) {
	header('HTTP/1.1 400 '.$e->getMessage());
	echo $e->getCode().' '.$e->getMessage();
	$GLOBALS['egw']->common->egw_exit();
}
exit_with_status($cmd,$success_msg);

function exit_with_status($cmd,$success_msg='Successful')
{
	switch($cmd->status)
	{
		case admin_cmd::failed:	// errors are returned as 400 HTTP status
			header('HTTP/1.1 400 '.$cmd->error);
			echo $cmd->errno.' '.$cmd->error;
			break;
			
		default:				// everything else is returned as 200 HTTP status
			$success_msg = $cmd->stati[$cmd->status];
			// fall through
		case admin_cmd::successful:
			header('HTTP/1.1 200 '.$cmd->stati[$cmd->status]);
			echo $success_msg;
	}
	$GLOBALS['egw']->common->egw_exit();
}
