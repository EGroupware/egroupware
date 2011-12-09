<?php
/**
 * eGroupWare - Logout
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => True,
		'currentapp'             => 'logout',
		'noheader'               => True,
		'nofooter'               => True,
		'nonavbar'               => True
	)
);
include('./header.inc.php');

$GLOBALS['sessionid'] = egw_session::get_sessionid();
$GLOBALS['kp3']       = egw_session::get_request('kp3');

$verified = $GLOBALS['egw']->session->verify();

if(!$redirectTarget = $GLOBALS['egw']->session->appsession('referer', 'login'))
{
	$redirectTarget = $GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=1&domain='.$GLOBALS['egw_info']['user']['domain'];
}

if($verified)
{
	$GLOBALS['egw']->hooks->process('logout');
	$GLOBALS['egw']->session->destroy($GLOBALS['sessionid'],$GLOBALS['kp3']);
}
else
{
	if(is_object($GLOBALS['egw']->log))
	{
		$GLOBALS['egw']->log->write(array(
			'text' => 'W-VerifySession, could not verify session during logout',
			'line' => __LINE__,
			'file' => __FILE__
		));
	}
}
$GLOBALS['egw']->session->egw_setcookie('eGW_remember','',0,'/');
$GLOBALS['egw']->session->egw_setcookie('sessionid');
$GLOBALS['egw']->session->egw_setcookie('kp3');
$GLOBALS['egw']->session->egw_setcookie('domain');

if($GLOBALS['egw_info']['server']['auth_type'] == 'cas')
{
	require_once('CAS/CAS.php');

	phpCAS::client(CAS_VERSION_2_0,
								$GLOBALS['egw_info']['server']['cas_server_host_name'],
								(int) $GLOBALS['egw_info']['server']['cas_server_port'],
								$GLOBALS['egw_info']['server']['cas_server_uri'] );
	phpCAS::logout(array('url'=>$GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=1&domain='.$GLOBALS['egw_info']['user']['domain']));
}

// $GLOBALS['egw']->redirect($redirectTarget);
?>
<head>
<script language="javascript">
function clearAuthenticationCache(page)
{
	// Default to a non-existing page (give error 500).
	// An empty page is better, here.
	if (!page) page = '.force_logout';

	try
	{
		var agt=navigator.userAgent.toLowerCase();
		if (agt.indexOf("msie") != -1)
		{
			// IE clear HTTP Authentication
			document.execCommand("ClearAuthenticationCache");
		}
		else
		{
			var xmlhttp;
			if (window.XMLHttpRequest)
			{
				xmlhttp = new XMLHttpRequest();
			}
			else if (window.ActiveXObject)
			{
				xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
			}
			else
			{
				return;
			}

			// Let's prepare invalid credentials
			xmlhttp.open("GET", page, true, "logout", "logout");
			// Let's send the request to the server
			xmlhttp.send("");
			// Let's abort the request
			xmlhttp.abort();
		}
	}
	catch(e)
	{
		alert("An exception occurred in the script. Error name: " + e.name + ". Error message: " + e.message);
		// There was an error
		return;
	}
}
</script>
<meta http-equiv="refresh" content="1;url=<?php echo $redirectTarget ?>">
</head>
<body onload="clearAuthenticationCache()">
<a href="<?php echo $redirectTarget ?>">Logout in progress...</a>
</body>

