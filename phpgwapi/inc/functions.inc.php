<?php
//$debugme = "on";
 /**************************************************************************\
 * phpGroupWare API - phpgwapi loader                                       *
 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
 * and Joseph Engo <jengo@phpgroupware.org>                                 *
 * Has a few functions, but primary role is to load the phpgwapi            *
 * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
 * -------------------------------------------------------------------------*
 * This library is part of the phpGroupWare API                             *
 * http://www.phpgroupware.org/api                                          * 
 * ------------------------------------------------------------------------ *
 * This library is free software; you can redistribute it and/or modify it  *
 * under the terms of the GNU Lesser General Public License as published by *
 * the Free Software Foundation; either version 2.1 of the License,         *
 * or any later version.                                                    *
 * This library is distributed in the hope that it will be useful, but      *
 * WITHOUT ANY WARRANTY; without even the implied warranty of               *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
 * See the GNU Lesser General Public License for more details.              *
 * You should have received a copy of the GNU Lesser General Public License *
 * along with this library; if not, write to the Free Software Foundation,  *
 * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
 \**************************************************************************/

 /* $Id$ */

 /****************************************************************************\
 * Direct functions, which are not part of the API class                      *
 * because they are require to be availble at the lowest level.               *
 \****************************************************************************/
 /*!
 @function CreateObject
 @abstract Load a class and include the class file if not done so already.
 @discussion Author: mdean, milosch (thanks to jengo and ralf)<br>
 This function is used to create an instance of a class,  
 and if the class file has not been included it will do so. <br>
 Syntax: CreateObject('app.class', 'constructor_params'); <br>
 Example1: $phpgw->acl = CreateObject('phpgwapi.acl');
 @param $classname name of class
 @param $p1-$p16 class parameters (all optional)
 */
function CreateObject($class,
	$p1='_UNDEF_',$p2='_UNDEF_',$p3='_UNDEF_',$p4='_UNDEF_',
		$p5='_UNDEF_',$p6='_UNDEF_',$p7='_UNDEF_',$p8='_UNDEF_',
		$p9='_UNDEF_',$p10='_UNDEF_',$p11='_UNDEF_',$p12='_UNDEF_',
		$p13='_UNDEF_',$p14='_UNDEF_',$p15='_UNDEF_',$p16='_UNDEF_')
{
	global $phpgw_info;

  /*		error_reporting(0);		*/
	list($appname,$classname) = explode(".", $class);
	if (!isset($GLOBALS['phpgw_info']['flags']['included_classes'][$classname]) ||
			!$GLOBALS['phpgw_info']['flags']['included_classes'][$classname])
	{
		$GLOBALS['phpgw_info']['flags']['included_classes'][$classname] = True;   
		include(PHPGW_INCLUDE_ROOT.'/'.$appname.'/inc/class.'.$classname.'.inc.php');
	}
	if ($p1 == '_UNDEF_')
	{
		eval('$obj = new '.$classname.';');
	}
	else
	{
		$input = array($p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8,$p9,$p10,$p11,$p12,$p13,$p14,$p15,$p16);
		$i = 1;
		$code = '$obj = new '.$classname.'(';
		while (list($x,$test) = each($input))
		{
			if ($test == '_UNDEF_' || $i == 17)
			{
				break;
			}
			else
			{
				$code .= '$p' . $i . ',';
			}
			$i++;
		}
		$code = substr($code,0,-1) . ');';
		eval($code);
	}
/*		error_reporting(E_ERROR | E_WARNING | E_PARSE);	*/
	return $obj;
}

 /*!
 @function ExecObject
 @abstract Execute a function, and load a class and include the class file if not done so already.
 @discussion Author: seek3r<br>
 This function is used to create an instance of a class,  
 and if the class file has not been included it will do so. <br>
 Syntax: ExecObject('app.class', 'constructor_params'); <br>
 Example1: ExecObject('phpgwapi.acl.read');
 @param $method to execute
 @param $functionparams function param should be an array
 @param $loglevel developers choice of logging level
 @param $classparams params to be sent to the contructor
 */
function ExecMethod($method, $functionparams = '_UNDEF_', $loglevel = 3, $classparams = '_UNDEF_')
{
 /* Need to make sure this is working against a single dimensional object */
	$partscount = substr_count($method, '.');
	if ($partscount == 2)
	{
		list($appname,$classname,$functionname) = explode(".", $method);
		if (!is_object($GLOBALS[$classname]))
		{
			if ($classparams != '_UNDEF_' && $classparams != True)
			{
				$GLOBALS[$classname] = CreateObject($appname.'.'.$classname, $classparams);
			}
			else
			{
				$GLOBALS[$classname] = CreateObject($appname.'.'.$classname);
			}
		}

		if ($functionparams != '_UNDEF_' && $functionparams != True)
		{
			return $GLOBALS[$classname]->$functionname($functionparams);
		}
		else
		{
			return $GLOBALS[$classname]->$functionname();
		}
	}
 /* if the $method includes a parent class (multi-dimensional) then we have to work from it */	
	elseif ($partscount >= 3)
	{
		$GLOBALS['methodparts'] = explode(".", $method);
		$classpartnum = $partscount - 1;
		$appname = $GLOBALS['methodparts'][0];		
		$classname = $GLOBALS['methodparts'][$classpartnum];
		$functionname = $GLOBALS['methodparts'][$partscount];
  /* Now I clear these out of the array so that I can do a proper */
  /* loop and build the $parentobject */
		unset ($GLOBALS['methodparts'][0]);
		unset ($GLOBALS['methodparts'][$classpartnum]);
		unset ($GLOBALS['methodparts'][$partscount]);
		reset ($GLOBALS['methodparts']);
		$firstparent = 'True';
		while (list ($key, $val) = each ($GLOBALS['methodparts'])) 
		{
			if ($firstparent == 'True')
			{
				$parentobject = '$GLOBALS["'.$val.'"]';
				$firstparent = False;
			}
			else
			{
				$parentobject .= '->'.$val;
			}
		}
		unset($GLOBALS['methodparts']);
		eval ('$isobject = is_object('.$parentobject.'->'.$classname.');');
		if (!$isobject)
		{
			if ($classparams != '_UNDEF_')
			{
				if (is_string($classparams))
				{
					eval($parentobject.'->'.$classname.' = CreateObject("'.$appname.'.'.$classname.'", "'.$classparams.'");');
				}
				else
				{
					eval($parentobject.'->'.$classname.' = CreateObject("'.$appname.'.'.$classname.'", '.$classparams.');');
				}
			}
			else
			{
				eval($parentobject.'->'.$classname.' = CreateObject("'.$appname.'.'.$classname.'");');
			}
		}

		if ($functionparams != '_UNDEF_')
		{
			eval('$returnval = '.$parentobject.'->'.$classname.'->'.$functionname.'('.$functionparams.');');
			return $returnval;
		}
		else
		{
			eval('$returnval = '.$parentobject.'->'.$classname.'->'.$functionname.'();');
			return $returnval;
		}		
	}
	else
	{
		return 'error in parts';
	}
}
   /*!
 @function lang
 @abstract function to handle multilanguage support
 */
function lang($key,$m1='',$m2='',$m3='',$m4='',$m5='',$m6='',$m7='',$m8='',$m9='',$m10='')
{
	if(gettype($m1) == 'array')
	{
		$vars = $m1;
	}
	else
	{
		$vars = array($m1,$m2,$m3,$m4,$m5,$m6,$m7,$m8,$m9,$m10);
	}
	$value = $GLOBALS['phpgw']->translation->translate("$key",$vars);
	return $value;
}

 /* Just a temp wrapper. ###DELETE_ME#### (Seek3r) */
function check_code($code)
{
	return $GLOBALS['phpgw']->common->check_code($code);
}

 /*!
 @function get_account_id()
 @abstract Return a properly formatted account_id.
 @discussion Author: skeeter <br>
 This function will return a properly formatted account_id. <br>
 This can take either a name or an account_id as paramters. <br>
 If a name is provided it will return the associated id. <br>
 Syntax: get_account_id($accountid); <br>
 Example1: $account_id = get_account_id($accountid);
 @param $account_id either a name or an id
 @param $default_id either a name or an id
 */
function get_account_id($account_id = '',$default_id = '')
{
	if (gettype($account_id) == 'integer')
	{
		return $account_id;
	}
	elseif ($account_id == '')
	{
		if ($default_id == '')
		{
			return (isset($GLOBALS['phpgw_info']['user']['account_id'])?$GLOBALS['phpgw_info']['user']['account_id']:0);
		}
		elseif (gettype($default_id) == 'string')
		{
			return $GLOBALS['phpgw']->accounts->name2id($default_id);
		}
		return intval($default_id);
	}
	elseif (gettype($account_id) == 'string')
	{
		if($GLOBALS['phpgw']->accounts->exists(intval($account_id)) == True)
		{
			return intval($account_id);
		}
		else
		{
			return $GLOBALS['phpgw']->accounts->name2id($account_id);
		}
	}
}

 /*!
 @function filesystem_separator()
 @abstract sets the file system seperator depending on OS
 @result file system separator
 */
function filesystem_separator()
{
	if (PHP_OS == 'Windows' || PHP_OS == 'OS/2')
	{
		return '\\';
	}
	else
	{
		return '/';
	}
}

function _debug_array($array)
{
	if(floor(phpversion()) == 4)
	{
		echo '<pre>'; print_r($array); echo '</pre>';
	}
	else
	{
		echo '<pre>'; var_dump($array); echo '</pre>';
	}
}

function print_debug($text='')
{
	if (isset($GLOBALS['debugme']) &&
			$GLOBALS['debugme'] == 'on')
	{
		echo 'debug: '.$text.'<br>';
	}
}

//	print_debug('core functions are done');
 /****************************************************************************\
 * Quick verification of sane environment                                     *
 \****************************************************************************/
//	error_reporting(7);
 /* Make sure the header.inc.php is current. */
if ($GLOBALS['phpgw_info']['server']['versions']['header'] < $GLOBALS['phpgw_info']['server']['versions']['current_header'])
{
	echo '<center><b>You need to port your settings to the new header.inc.php version.</b></center>';
	exit;
}

 /* Make sure the developer is following the rules. */
if (!isset($GLOBALS['phpgw_info']['flags']['currentapp']))
{
	$phpgw->log->write(array('text'=>'W-MissingFlags, currentapp flag not set'));

	echo '<b>!!! YOU DO NOT HAVE YOUR $phpgw_info["flags"]["currentapp"] SET !!!';
	echo '<br>!!! PLEASE CORRECT THIS SITUATION !!!</b>';
}

magic_quotes_runtime(false);
print_debug('sane environment');

 /****************************************************************************\
 * Multi-Domain support                                                       *
 \****************************************************************************/

 /* make them fix their header */
if (!isset($GLOBALS['phpgw_domain']))
{
	echo '<center><b>The administrator must upgrade the header.inc.php file before you can continue.</b></center>';
	exit;
}
reset($GLOBALS['phpgw_domain']);
$default_domain = each($GLOBALS['phpgw_domain']);
$GLOBALS['phpgw_info']['server']['default_domain'] = $default_domain[0];
unset ($default_domain); // we kill this for security reasons

 /* This code will handle virtdomains so that is a user logins with user@domain.com, it will switch into virtualization mode. */
if (isset($domain))
{
	$GLOBALS['phpgw_info']['user']['domain'] = $domain;
}
elseif (isset($login) && isset($logindomain))
{
	if (!ereg ("\@", $login))
	{
		$login = $login."@".$logindomain;
	}
	$GLOBALS['phpgw_info']['user']['domain'] = $logindomain;
	unset ($logindomain);
}
elseif (isset($login) && !isset($logindomain))
{
	if (ereg ("\@", $login))
	{
		$login_array = explode("@", $login);
		$GLOBALS['phpgw_info']['user']['domain'] = $login_array[1];
	}
	else
	{
		$GLOBALS['phpgw_info']['user']['domain'] = $GLOBALS['phpgw_info']['server']['default_domain'];
		$login = $login . '@' . $GLOBALS['phpgw_info']['user']['domain'];
	}
}

if (@isset($GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['user']['domain']]))
{
	$GLOBALS['phpgw_info']['server']['db_host'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['user']['domain']]['db_host'];
	$GLOBALS['phpgw_info']['server']['db_name'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['user']['domain']]['db_name'];
	$GLOBALS['phpgw_info']['server']['db_user'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['user']['domain']]['db_user'];
	$GLOBALS['phpgw_info']['server']['db_pass'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['user']['domain']]['db_pass'];
	$GLOBALS['phpgw_info']['server']['db_type'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['user']['domain']]['db_type'];
}
else
{
	$GLOBALS['phpgw_info']['server']['db_host'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['server']['default_domain']]['db_host'];
	$GLOBALS['phpgw_info']['server']['db_name'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['server']['default_domain']]['db_name'];
	$GLOBALS['phpgw_info']['server']['db_user'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['server']['default_domain']]['db_user'];
	$GLOBALS['phpgw_info']['server']['db_pass'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['server']['default_domain']]['db_pass'];
	$GLOBALS['phpgw_info']['server']['db_type'] = $GLOBALS['phpgw_domain'][$GLOBALS['phpgw_info']['server']['default_domain']]['db_type'];
}

if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'login' && ! $GLOBALS['phpgw_info']['server']['show_domain_selectbox'])
{
	unset ($GLOBALS['phpgw_domain']); // we kill this for security reasons  
}
unset ($domain); // we kill this to save memory

@print_debug('domain: '.$GLOBALS['phpgw_info']['user']['domain']);

 /****************************************************************************\
 * These lines load up the API, fill up the $phpgw_info array, etc            *
 \****************************************************************************/
 /* Load main class */
$GLOBALS['phpgw'] = CreateObject('phpgwapi.phpgw');
 /************************************************************************\
 * Load up the main instance of the db class.                             *
 \************************************************************************/
$GLOBALS['phpgw']->db           = CreateObject('phpgwapi.db');
$GLOBALS['phpgw']->db->Host     = $GLOBALS['phpgw_info']['server']['db_host'];
$GLOBALS['phpgw']->db->Type     = $GLOBALS['phpgw_info']['server']['db_type'];
$GLOBALS['phpgw']->db->Database = $GLOBALS['phpgw_info']['server']['db_name'];
$GLOBALS['phpgw']->db->User     = $GLOBALS['phpgw_info']['server']['db_user'];
$GLOBALS['phpgw']->db->Password = $GLOBALS['phpgw_info']['server']['db_pass'];
if ($GLOBALS['phpgw']->debug)
{
	$GLOBALS['phpgw']->db->Debug = 1;
}

$GLOBALS['phpgw']->db->Halt_On_Error = 'no';
@$GLOBALS['phpgw']->db->query("select count(*) from phpgw_config");
if (! @$GLOBALS['phpgw']->db->next_record())
{
	$setup_dir = ereg_replace($PHP_SELF,'index.php','setup/');
	echo '<center><b>Fatal Error:</b> It appears that you have not created the database tables for '
	.'phpGroupWare.  Click <a href="' . $setup_dir . '">here</a> to run setup.</center>';
	exit;
}
$GLOBALS['phpgw']->db->Halt_On_Error = 'yes';

 /* Fill phpgw_info["server"] array */
 // An Attempt to speed things up using cache premise
$GLOBALS['phpgw']->db->query("select config_value from phpgw_config WHERE config_app='phpgwapi' and config_name='cache_phpgw_info'",__LINE__,__FILE__);
if ($GLOBALS['phpgw']->db->num_rows())
{
	$GLOBALS['phpgw']->db->next_record();
	$GLOBALS['phpgw_info']['server']['cache_phpgw_info'] = stripslashes($GLOBALS['phpgw']->db->f('config_value'));
}

$cache_query = "select content from phpgw_app_sessions where"
	." sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'";

$GLOBALS['phpgw']->db->query($cache_query,__LINE__,__FILE__);
$server_info_cache = $GLOBALS['phpgw']->db->num_rows();

if(@$GLOBALS['phpgw_info']['server']['cache_phpgw_info'] && $server_info_cache)
{
	$GLOBALS['phpgw']->db->next_record();
	$GLOBALS['phpgw_info']['server'] = unserialize(stripslashes($GLOBALS['phpgw']->db->f('content')));
}
else
{	
	$GLOBALS['phpgw']->db->query("select * from phpgw_config WHERE config_app='phpgwapi'",__LINE__,__FILE__);
	while ($GLOBALS['phpgw']->db->next_record())
	{
		$GLOBALS['phpgw_info']['server'][$GLOBALS['phpgw']->db->f('config_name')] = stripslashes($GLOBALS['phpgw']->db->f('config_value'));
	}

	if($GLOBALS['phpgw_info']['server']['cache_phpgw_info'])
	{
		if($server_info_cache)
		{
			$cache_query = "UPDATE phpgw_app_sessions set content='".addslashes(serialize($GLOBALS['phpgw_info']['server']))."'"
				." WHERE sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'";
		}
		else
		{
			$cache_query = 'INSERT INTO phpgw_app_sessions(sessionid,loginid,app,location,content) VALUES('
				. "'0','0','phpgwapi','config','".addslashes(serialize($GLOBALS['phpgw_info']['server']))."')";
		}
		$GLOBALS['phpgw']->db->query($cache_query,__LINE__,__FILE__);
	}
}
unset($cache_query);
unset($server_info_cache);
 /************************************************************************\
 * Required classes                                                       *
 \************************************************************************/
$GLOBALS['phpgw']->common       = CreateObject('phpgwapi.common');
$GLOBALS['phpgw']->hooks        = CreateObject('phpgwapi.hooks');
$GLOBALS['phpgw']->auth         = CreateObject('phpgwapi.auth');
$GLOBALS['phpgw']->accounts     = CreateObject('phpgwapi.accounts');
$GLOBALS['phpgw']->acl          = CreateObject('phpgwapi.acl');
$GLOBALS['phpgw']->session      = CreateObject('phpgwapi.sessions');
$GLOBALS['phpgw']->preferences  = CreateObject('phpgwapi.preferences');
$GLOBALS['phpgw']->applications = CreateObject('phpgwapi.applications');
$GLOBALS['phpgw']->translation  = CreateObject('phpgwapi.translation');
$GLOBALS['phpgw']->log          = CreateObject('phpgwapi.errorlog');
//	$GLOBALS['phpgw']->datetime = CreateObject('phpgwapi.datetime');
print_debug('main class loaded');
if (! isset($phpgw_info['flags']['included_classes']['error']) ||
		! $phpgw_info['flags']['included_classes']['error'])
{
	$phpgw_info['flags']['included_classes']['error'] = True;
	include(PHPGW_INCLUDE_ROOT.'/phpgwapi/inc/class.error.inc.php');
}


 /****************************************************************************\
 * This is a global constant that should be used                              *
 * instead of / or \ in file paths                                            *
 \****************************************************************************/
define('SEP',filesystem_separator());

 /****************************************************************************\
 * Stuff to use if logging in or logging out                                  *
 \****************************************************************************/
if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'login' || $GLOBALS['phpgw_info']['flags']['currentapp'] == 'logout')
{
	if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'login')
	{
		if (@$login != '')
		{
			$login_array = explode("@",$login);
			$login_id = $GLOBALS['phpgw']->accounts->name2id($login_array[0]);
			$GLOBALS['phpgw']->accounts->accounts($login_id);
			$GLOBALS['phpgw']->preferences->preferences($login_id);
		}
	}
  /****************************************************************************\
  * Everything from this point on will ONLY happen if                          *
  * the currentapp is not login or logout                                      *
  \****************************************************************************/
}
else
{
	if (! $GLOBALS['phpgw']->session->verify())
	{
		Header('Location: ' . $GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->session->link('/login.php','cd=10')));
		exit;
	}

  /* A few hacker resistant constants that will be used throught the program */
	define('PHPGW_TEMPLATE_DIR',$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi'));
	define('PHPGW_IMAGES_DIR', $GLOBALS['phpgw']->common->get_image_path('phpgwapi'));
	define('PHPGW_IMAGES_FILEDIR', $GLOBALS['phpgw']->common->get_image_dir('phpgwapi'));
	define('PHPGW_APP_ROOT', $GLOBALS['phpgw']->common->get_app_dir());
	define('PHPGW_APP_INC', $GLOBALS['phpgw']->common->get_inc_dir());
	define('PHPGW_APP_TPL', $GLOBALS['phpgw']->common->get_tpl_dir());
	define('PHPGW_IMAGES', $GLOBALS['phpgw']->common->get_image_path());
	define('PHPGW_APP_IMAGES_DIR', $GLOBALS['phpgw']->common->get_image_dir());

	define('PHPGW_ACL_READ',1);
	define('PHPGW_ACL_ADD',2);
	define('PHPGW_ACL_EDIT',4);
	define('PHPGW_ACL_DELETE',8);
	define('PHPGW_ACL_PRIVATE',16);

  /********* This sets the user variables *********/
	$GLOBALS['phpgw_info']['user']['private_dir'] = $GLOBALS['phpgw_info']['server']['files_dir']
		. '/users/'.$GLOBALS['phpgw_info']['user']['userid'];

  /* This will make sure that a user has the basic default prefs. If not it will add them */
	$GLOBALS['phpgw']->preferences->verify_basic_settings();

  /********* Optional classes, which can be disabled for performance increases *********/
	while ($phpgw_class_name = each($GLOBALS['phpgw_info']['flags']))
	{
		if (ereg('enable_',$phpgw_class_name[0]))
		{
			$enable_class = str_replace('enable_','',$phpgw_class_name[0]);
			$enable_class = str_replace('_class','',$enable_class);
			eval('$GLOBALS["phpgw"]->' . $enable_class . ' = createobject(\'phpgwapi.' . $enable_class . '\');');
		}
	}
	unset($enable_class);
	reset($GLOBALS['phpgw_info']['flags']);

  /*************************************************************************\
  * These lines load up the templates class                                 *
  \*************************************************************************/
	if(!@$GLOBALS['phpgw_info']['flags']['disable_Template_class'])
	{
		$GLOBALS['phpgw']->template = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	}

  /*************************************************************************\
  * These lines load up the themes                                          *
  \*************************************************************************/
	if (! $GLOBALS['phpgw_info']['user']['preferences']['common']['theme'])
	{
		if ($GLOBALS['phpgw_info']['server']['template_set'] == 'user_choice')
		{
			$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] = 'default';
		}
		else
		{
			$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] = $GLOBALS['phpgw_info']['server']['template_set'];
		}
	}

	if ($GLOBALS['phpgw_info']['server']['force_theme'] == 'user_choice')
	{
		$theme_to_load = (isset($GLOBALS['phpgw_info']['user']['preferences']['common']['theme'])?$GLOBALS['phpgw_info']['user']['preferences']['common']['theme']:'default');
	}
	else
	{
		$theme_to_load = (isset($GLOBALS['phpgw_info']['server']['force_theme'])?$GLOBALS['phpgw_info']['server']['force_theme']:'default');
	}

	if(@file_exists(PHPGW_SERVER_ROOT . '/phpgwapi/themes/' . $theme_to_load . '.theme'))
	{
		include(PHPGW_SERVER_ROOT . '/phpgwapi/themes/' . $theme_to_load . '.theme');
	}
	elseif(@file_exists(PHPGW_SERVER_ROOT . '/phpgwapi/themes/default.theme'))
	{
		include(PHPGW_SERVER_ROOT . '/phpgwapi/themes/default.theme');
	}
	else
	{
   /* Hope we don't get to this point.  Better then the user seeing a */
   /* complety back screen and not know whats going on                */
		echo '<body bgcolor="FFFFFF">';
		$phpgw->log->write(array('text'=>'F-Abort, No themes found'));

		exit;
	}
	unset($theme_to_load);

  /*************************************************************************\
  * If they are using frames, we need to set some variables                 *
  \*************************************************************************/
	if (((isset($GLOBALS['phpgw_info']['user']['preferences']['common']['useframes']) &&
					$GLOBALS['phpgw_info']['user']['preferences']['common']['useframes']) && 
				$GLOBALS['phpgw_info']['server']['useframes'] == 'allowed') ||
			($GLOBALS['phpgw_info']['server']['useframes'] == 'always'))
	{
		$GLOBALS['phpgw_info']['flags']['navbar_target'] = 'phpgw_body';
	}

  /*************************************************************************\
  * Verify that the users session is still active otherwise kick them out   *
  \*************************************************************************/
	if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'home' &&
			$GLOBALS['phpgw_info']['flags']['currentapp'] != 'preferences' &&
			$GLOBALS['phpgw_info']['flags']['currentapp'] != 'about')
	{
   // This will need to use ACL in the future
		if (! $GLOBALS['phpgw_info']['user']['apps'][$GLOBALS['phpgw_info']['flags']['currentapp']] ||
				(@$GLOBALS['phpgw_info']['flags']['admin_only'] &&
					! $GLOBALS['phpgw_info']['user']['apps']['admin']))
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			if ($GLOBALS['phpgw_info']['flags']['noheader'])
			{
				echo parse_navbar();
			}

			$phpgw->log->write(array('text'=>'W-Permissions, Attempted to access %1','p1'=>$GLOBALS['phpgw_info']['flags']['currentapp']));

			echo '<p><center><b>'.lang('Access not permitted').'</b></center>';
			$GLOBALS['phpgw']->common->phpgw_exit(True);
		}
	}

  /*************************************************************************\
  * Load the header unless the developer turns it off                       *
  \*************************************************************************/
	if (!@$GLOBALS['phpgw_info']['flags']['noheader'])
	{
		$GLOBALS['phpgw']->common->phpgw_header();
	}

  /*************************************************************************\
  * Load the app include files if the exists                                *
  \*************************************************************************/
  /* Then the include file */
	if (! preg_match ("/phpgwapi/i", PHPGW_APP_INC) && file_exists(PHPGW_APP_INC . '/functions.inc.php') && !isset($menuaction))
	{
		include(PHPGW_APP_INC . '/functions.inc.php');
	}
	if (!@$GLOBALS['phpgw_info']['flags']['noheader'] && 
			!@$GLOBALS['phpgw_info']['flags']['noappheader'] &&
			file_exists(PHPGW_APP_INC . '/header.inc.php') && !isset($menuaction))
	{
		include(PHPGW_APP_INC . '/header.inc.php');
	}
}

error_reporting(E_ERROR | E_WARNING | E_PARSE);
