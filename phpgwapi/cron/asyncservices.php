#!/usr/bin/php -q
<?php
/**
 * API - Timed Asynchron Services for eGroupWare
 * 
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * 
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @access public
 * @version $Id$ 
 */

	$_GET['domain'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'default';
	$path_to_egroupware = realpath(dirname(__FILE__).'/../..');	//  need to be adapted if this script is moved somewhere else

	// remove the comment from one of the following lines to enable loging
	// define('ASYNC_LOG','C:\\async.log');		// Windows
	// define('ASYNC_LOG','/tmp/async.log');	// Linux, Unix, ...
	if (defined('ASYNC_LOG'))
	{
		$msg = date('Y/m/d H:i:s ').$_GET['domain'].": asyncservice started\n";
		$f = fopen(ASYNC_LOG,'a+');
		fwrite($f,$msg);
		fclose($f);
	}

	$GLOBALS['egw_info']['flags'] = array(
		'currentapp' => 'login',
		'noapi'      => True		// this stops header.inc.php to include phpgwapi/inc/function.inc.php
	);
	if (!is_readable($path_to_egroupware.'/header.inc.php'))
	{
		echo $msg = "asyncservice.php: Could not find '$path_to_egroupware/header.inc.php', exiting !!!\n";
		if (defined('ASYNC_LOG'))
		{
			$f = fopen(ASYNC_LOG,'a+');
			fwrite($f,$msg);
			fclose($f);
		}
		exit(1);
	}
	include($path_to_egroupware.'/header.inc.php');
	unset($GLOBALS['egw_info']['flags']['noapi']);

	$db_type = $GLOBALS['egw_domain'][$_GET['domain']]['db_type'];
	if (!isset($GLOBALS['egw_domain'][$_GET['domain']]) || empty($db_type))
	{
		echo $msg = "asyncservice.php: Domain '$_GET[domain]' is not configured or renamed, exiting !!!\n";
		if (defined('ASYNC_LOG'))
		{
			$f = fopen(ASYNC_LOG,'a+');
			fwrite($f,$msg);
			fclose($f);
		}
		exit(1);
	}

	include(EGW_API_INC.'/functions.inc.php');

	$num = ExecMethod('phpgwapi.asyncservice.check_run','crontab');

	$msg = date('Y/m/d H:i:s ').$_GET['domain'].': '.($num === false ? 'An error occured: can not obtain semaphore!' : 
		($num ? "$num job(s) executed" : 'Nothing to execute'))."\n\n";
	// if the following comment got removed, you will get an email from cron for every check performed (*nix only)
	//echo $msg;

	if (defined('ASYNC_LOG'))
	{
		$f = fopen(ASYNC_LOG,'a+');
		fwrite($f,$msg);
		fclose($f);
	}
	$GLOBALS['egw']->common->egw_exit();
