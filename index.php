<?php
/**
 * EGroupware index page
 *
 * Starts all Egw\Applications using $_GET[menuaction]
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;

// Rocket.Chat desktop clients ignore /rocketchat/ path in URL and use just /
// --> redirect them back to /rocketchat/
if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Rocket.Chat') !== false)
{
	header('Location: /rocketchat/');
	exit;
}

// support of Mac or iPhone trying to autodetect CalDAV or CardDAV support
// if EGroupware is not installed in the docroot, you need either this code in the index.php there,
// or an uncoditional redirect to this file or copy groupdav.htaccess to your docroot as .htaccess
if ($_SERVER['REQUEST_METHOD'] == 'PROPFIND' || $_SERVER['REQUEST_METHOD'] == 'OPTIONS')
{
        header('Location: groupdav.php/');
        exit;
}

// forward for not existing or empty header to setup
if(!file_exists('header.inc.php') || !filesize('header.inc.php'))
{
	Header('Location: setup/index.php');
	exit;
}

if(isset($_GET['hasupdates']) && $_GET['hasupdates'] == 'yes')
{
	$hasupdates = True;
}

/*
	This is the menuaction driver for the multi-layered design
*/
$invalid_data = false;
if(isset($_GET['menuaction']) && !preg_match('/^[A-Za-z0-9_]+\.[A-Za-z0-9_\\\\]+\.[A-Za-z0-9_]+$/', $_GET['menuaction']))
{
	http_response_code(400);
	exit;
}
// no menuaction at all, eg. a bare /index.php: default to 'api' with no class - there's nothing to
// dispatch, the actual app gets resolved further down from the user's preferences, once
// header.inc.php gave us those
list($app, $class, $method) = explode('.', $_GET['menuaction'] ?? 'api..');

if($app == 'phpgwapi')
{
	$app = 'api';
	$api_requested = True;
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => $app
	)
);
include('./header.inc.php');

// $app only drove which rights get checked above, it need not be the app $class actually belongs
// to - that decoupling is the bug this closes. Verify it now with the same purely string-based
// check json.php/ajax_exec already apply; left uncaught, the exception handler header.inc.php just
// installed renders it as a generic error page, same as any NoPermission thrown below. Nothing to
// verify without a menuaction - $class is empty, so nothing gets dispatched below either.
if (isset($_GET['menuaction']))
{
	Api\Json\Request::checkMenuAction($_GET['menuaction']);
}

// user changed timezone
if (isset($_GET['tz']))
{
	Api\DateTime::setUserPrefs($_GET['tz']);	// throws exception, if tz is invalid

	$GLOBALS['egw']->preferences->add('common','tz',$_GET['tz']);
	$GLOBALS['egw']->preferences->save_repository();

	if (($referer = Api\Header\Referer::get()))
	{
		Egw::redirect_link($referer);
	}
}
// Always open no_popup flagged URLs "normally" on mobile devices
if(!empty($_GET['no_popup']) && $_GET['no_popup'] == '1' && EGroupware\Api\Header\UserAgent::mobile() && $app)
{
	unset($_GET['no_popup']);
	$_GET['cd'] = 'yes';
}

if($app == 'api' && !$class && !$api_requested && !($_GET['cd'] === 'yes' && !Api\Header\UserAgent::mobile()) && $GLOBALS['egw_info']['user']['preferences']['common']['template_set'] == 'idots')
{
	if ($GLOBALS['egw_info']['server']['force_default_app'] && $GLOBALS['egw_info']['server']['force_default_app'] != 'user_choice')
	{
		$GLOBALS['egw_info']['user']['preferences']['common']['default_app'] = $GLOBALS['egw_info']['server']['force_default_app'];
	}
	$default_app = $GLOBALS['egw_info']['user']['preferences']['common']['default_app'];
	// default_app is a stored preference (or the site's forced default) - the user's rights can have
	// changed since it was set, so it must still be checked, same as any other app we dispatch to
	if($default_app && !$hasupdates && isset($GLOBALS['egw_info']['user']['apps'][$default_app]))
	{
		Egw::redirect(Framework::index($default_app),$default_app);
	}
	// 'home' is not guaranteed to be available to every user either
	elseif (isset($GLOBALS['egw_info']['user']['apps']['home']))
	{
		Egw::redirect_link('/home/index.php?cd=yes');
	}
	// else fall through to the plain shell below, which needs no app-specific rights
}

if ($_GET['cd'] == 'yes' || empty($class))
{
	$GLOBALS['egw_info']['flags'] = array(
		'noheader'   => False,
		'nonavbar'   => False,
		'currentapp' => 'eGroupWare'
	);
	echo $GLOBALS['egw']->framework->header();
	echo $GLOBALS['egw']->framework->footer();
}
else
{
	if($api_requested)
	{
		$app = 'phpgwapi';
	}

	if (class_exists($class))
	{
		$obj = new $class;
	}
	else
	{
		$obj = CreateObject($app.'.'.$class);
	}
	if((is_array($obj->public_functions) && $obj->public_functions[$method]) && !$invalid_data)
	{
		$obj->$method();
		unset($app);
		unset($class);
		unset($method);
		unset($invalid_data);
		unset($api_requested);
	}
	else
	{
		if(!$app || !$class || !$method || $invalid_data)
		{
			error_log(__FILE__.": missing or bad menuaction '$_GET[menuaction]'!");
		}

		if(!is_array($GLOBALS[$class]->public_functions) || !$GLOBALS[$class]->public_functions[$method] && $method)
		{
			error_log(__FILE__.": invalid menuaction '$_GET[menuaction]', not in public_functions!");
		}

		// 'home' is not guaranteed to be available to every user; '/index.php?cd=yes' (the plain
		// shell) always is
		$GLOBALS['egw']->redirect_link(isset($GLOBALS['egw_info']['user']['apps']['home']) ?
			'/home/index.php' : '/index.php?cd=yes');
	}

	if(!isset($GLOBALS['egw_info']['nofooter']))
	{
		echo $GLOBALS['egw']->framework->footer();
	}
}