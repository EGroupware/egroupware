<?php
/**
 * Setup
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Mark Peters <skeeter@phpgroupware.org>
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

error_reporting(error_reporting() & ~E_NOTICE & ~E_STRICT);

// for an old header, we need to setup the reference before including it
$GLOBALS['phpgw_info'] =& $GLOBALS['egw_info'];

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'setup',
		'noapi' => True
));
if(file_exists('../header.inc.php'))
{
	include('../header.inc.php');
}
// for an old header we need to setup a reference for the domains
if (!is_array($GLOBALS['egw_domain'])) $GLOBALS['egw_domain'] =& $GLOBALS['phpgw_domain'];

if (!function_exists('version_compare'))//version_compare() is only available in PHP4.1+
{
	echo 'eGroupWare now requires PHP 4.1 or greater.<br>';
	echo 'Please contact your System Administrator';
	exit;
}

/*  If we included the header.inc.php, but it is somehow broken, cover ourselves... */
if(!defined('EGW_SERVER_ROOT') && !defined('EGW_INCLUDE_ROOT'))
{
	if (defined('PHPGW_SERVER_ROOT') && defined('PHPGW_INCLUDE_ROOT'))	// pre 1.2 install
	{
		define('EGW_SERVER_ROOT',PHPGW_SERVER_ROOT);
		define('EGW_INCLUDE_ROOT',PHPGW_INCLUDE_ROOT);
	}
	else	// no install
	{
		define('EGW_SERVER_ROOT',realpath('..'));
		define('EGW_INCLUDE_ROOT',realpath('..'));
		define('PHPGW_SERVER_ROOT',realpath('..'));
		define('PHPGW_INCLUDE_ROOT',realpath('..'));
	}
	define('EGW_API_INC',EGW_SERVER_ROOT.'/phpgwapi/inc');
}

require_once(EGW_INCLUDE_ROOT . '/phpgwapi/inc/common_functions.inc.php');

// check if eGW's pear repository is installed and prefer it over the regular one
if (is_dir(EGW_SERVER_ROOT.'/egw-pear'))
{
	set_include_path(EGW_SERVER_ROOT.'/egw-pear'.PATH_SEPARATOR.get_include_path());
	//echo "<p align=right>include_path='".get_include_path()."'</p>\n";
}

define('SEP',filesystem_separator());

/**
 * function to handle multilanguage support
 *
 */
function lang($key,$vars=null)
{
	if(!is_array($vars))
	{
		$vars = func_get_args();
		array_shift($vars);	// remove $key
	}
	return $GLOBALS['egw_setup']->translation->translate("$key", $vars);
}

if(file_exists(EGW_SERVER_ROOT.'/phpgwapi/setup/setup.inc.php'))
{
	include(EGW_SERVER_ROOT.'/phpgwapi/setup/setup.inc.php'); /* To set the current core version */
	/* This will change to just use setup_info */
	$GLOBALS['egw_info']['server']['versions']['current_header'] = $setup_info['phpgwapi']['versions']['current_header'];
}
else
{
	$GLOBALS['egw_info']['server']['versions']['phpgwapi'] = 'Undetected';
}

$GLOBALS['egw_info']['server']['app_images'] = 'templates/default/images';

CreateObject('setup.setup',True,True);	// setup constuctor assigns itself to $GLOBALS['egw_setup'], doing it twice fails on some php4
$GLOBALS['phpgw_setup'] =& $GLOBALS['egw_setup'];
