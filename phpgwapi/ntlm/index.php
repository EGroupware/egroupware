<?php
/**
 * eGroupWare - NTLM or other http auth access without login page
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage authentication
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-2011 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * Check if given domain is either whitelisted, the current one or the EGroupware one
 *
 * Used to NOT redirect to arbitrary urls.
 *
 * @param string $url full url or just path, later is always allowed, as it stays within the domain
 * @return boolean
 */
function check_domain($url)
{
	$whitelisted = array(
		$_SERVER['HTTP_HOST'],	// can contain :port
		// add additional domains-names (just full qualified hostnames) here

	);
	if ($GLOBALS['egw_info']['server']['webserver_url'][0] === 'h')
	{
		$whitelisted[] = parse_url($GLOBALS['egw_info']['server']['webserver_url'], PHP_URL_HOST);
	}
	$parts = parse_url($url);
	$host = $parts['host'].($parts['port'] ? ':'.$parts['port'] : '');

	return $url[0] == '/' || in_array($host, $whitelisted);
}

/**
 * check if the given user has access
 *
 * Create a session or if the user has no account return authenticate header and 401 Unauthorized
 *
 * @param array &$account
 * @return int session-id
 */
function check_access(&$account)
{
	//error_log("AUTH_TYPE={$_SERVER['AUTH_TYPE']}, REMOTE_USER={$_SERVER['REMOTE_USER']}, HTTP_USER_AGENT={$_SERVER['HTTP_USER_AGENT']}, http_auth_types={$GLOBALS['egw_info']['server']['http_auth_types']}");

	if (isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] && isset($_SERVER['AUTH_TYPE']) &&
		isset($GLOBALS['egw_info']['server']['http_auth_types']) && $GLOBALS['egw_info']['server']['http_auth_types'] &&
		in_array(strtoupper($_SERVER['AUTH_TYPE']),explode(',',strtoupper($GLOBALS['egw_info']['server']['http_auth_types']))))
	{
		if (strpos($account=$_SERVER['REMOTE_USER'],'\\') !== false)
		{
			list(,$account) = explode('\\',$account,2);
		}
		$sessionid = $GLOBALS['egw']->session->create($account,null,'ntlm',false,false);	// false=no auth check
		//error_log("create('$account',null,'ntlm',false,false)=$sessionid ({$GLOBALS['egw']->session->reason})");
	}
	if (!$sessionid)
	{
		if (isset($_GET['forward']) && check_domain($_GET['forward']))
		{
			header('Location: '.$_GET['forward']);
		}
		else
		{
			header('Location: ../../login.php'.(isset($_REQUEST['phpgw_forward']) ? '?phpgw_forward='.$_REQUEST['phpgw_forward'] : ''));
		}
		exit;
	}
	return $sessionid;
}

$GLOBALS['egw_info']['flags'] = array(
	'noheader'  => True,
	'currentapp' => 'home',
	'autocreate_session_callback' => 'check_access',
);
// if you move this file somewhere else, you need to adapt the path to the header!
include(dirname(__FILE__).'/../../header.inc.php');

if (isset($_GET['forward']) && check_domain($_GET['forward']))
{
	$forward = $_GET['forward'];
	$GLOBALS['egw']->session->appsession('referer', 'login', $forward);
error_log('stored login-referer='.$forward);
}
elseif ($_REQUEST['phpgw_forward'])
{
	$forward = '../..'.(isset($_GET['phpgw_forward']) ? urldecode($_GET['phpgw_forward']) : @$_POST['phpgw_forward']);
}
else
{
	$forward = '../../index.php';
}
// commiting the session, before redirecting might fix racecondition in session creation
$GLOBALS['egw']->session->commit_session();
header('Location: '.$forward);
