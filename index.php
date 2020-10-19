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
if (strpos($_SERVER['HTTP_USER_AGENT'], 'Rocket.Chat') !== false)
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
if(isset($_GET['menuaction']) && preg_match('/^[A-Za-z0-9_]+\.[A-Za-z0-9_\\\\]+\.[A-Za-z0-9_]+$/',$_GET['menuaction']))
{
	list($app,$class,$method) = explode('.',$_GET['menuaction']);

	// check if autoloadable class belongs to given app
	if (substr($class, 0, 11) == 'EGroupware\\')
	{
		list(,$app_from_class) = explode('\\', strtolower($class));
	}
	elseif(strpos($class, '_') !== false)
	{
		list($app_from_class) = explode('_', $class);
	}
	if(!$app || !$class || !$method || isset($app_from_class) &&
		isset($GLOBALS['egw_info']['apps'][$app_from_class]) && $app_from_class != $app)
	{
		$invalid_data = True;
	}
}
else
{
	$app = 'api';
	$invalid_data = True;
}
//error_log(__METHOD__."$app,$class,$method");
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

// 	Check if we are using windows or normal webpage
$windowed = false;
$tpl_info = EGW_SERVER_ROOT . '/phpgwapi/templates/' . basename($GLOBALS['egw_info']['user']['preferences']['common']['template_set']) . '/setup/setup.inc.php';
if (!file_exists($tpl_info))
{
	$tpl_info = EGW_SERVER_ROOT.'/'.basename($GLOBALS['egw_info']['user']['preferences']['common']['template_set']) . '/setup/setup.inc.php';
}
if(@file_exists($tpl_info))
{
	include_once($tpl_info);
	if($GLOBALS['egw_info']['template'][$GLOBALS['egw_info']['user']['preferences']['common']['template_set']]['windowed'])
	{
		$windowed = true;
	}
}


if($app == 'api' && !$class && !$api_requested && !($windowed && $_GET['cd'] == 'yes' && !Api\Header\UserAgent::mobile()) && $GLOBALS['egw_info']['user']['preferences']['common']['template_set'] == 'idots')
{
	if ($GLOBALS['egw_info']['server']['force_default_app'] && $GLOBALS['egw_info']['server']['force_default_app'] != 'user_choice')
	{
		$GLOBALS['egw_info']['user']['preferences']['common']['default_app'] = $GLOBALS['egw_info']['server']['force_default_app'];
	}
	if($GLOBALS['egw_info']['user']['preferences']['common']['default_app'] && !$hasupdates)
	{
		Egw::redirect(Framework::index($GLOBALS['egw_info']['user']['preferences']['common']['default_app']),$GLOBALS['egw_info']['user']['preferences']['common']['default_app']);
	}
	else
	{
		Egw::redirect_link('/home/index.php?cd=yes');
	}
}

if($windowed && $_GET['cd'] == 'yes')
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
			if(@is_object($GLOBALS['egw']->log))
			{
				$GLOBALS['egw']->log->message(array(
					'text' => 'W-BadmenuactionVariable, menuaction missing or corrupt: %1',
					'p1'   => $menuaction,
					'line' => __LINE__,
					'file' => __FILE__
				));
			}
		}

		if(!is_array($GLOBALS[$class]->public_functions) || !$GLOBALS[$class]->public_functions[$method] && $method)
		{
			if(@is_object($GLOBALS['egw']->log))
			{
				$GLOBALS['egw']->log->message(array(
					'text' => 'W-BadmenuactionVariable, attempted to access private method: %1',
					'p1'   => $method,
					'line' => __LINE__,
					'file' => __FILE__
				));
			}
		}
		if(@is_object($GLOBALS['egw']->log))
		{
			$GLOBALS['egw']->log->commit();
		}

		$GLOBALS['egw']->redirect_link('/home/index.php');
	}

	if(!isset($GLOBALS['egw_info']['nofooter']))
	{
		echo $GLOBALS['egw']->framework->footer();
	}
}
