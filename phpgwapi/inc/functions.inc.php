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
	@discussion Author: mdean <br>
	This function is used to create an instance of a class,  
	and if the class file has not been included it will do so. <br>
	Syntax: CreateObject('app.class', 'constructor_params'); <br>
	Example1: $phpgw->acl = CreateObject('phpgwapi.acl');
	@param $classname name of class
	@param $p1-$p16 class parameters (all optional)
	*/
	function CreateObject($classname, $p1='',$p2='',$p3='',$p4='',$p5='',$p6='',$p7='',$p8='',$p9='',$p10='',$p11='',$p12='',$p13='',$p14='',$p15='',$p16='')
	{
		global $phpgw, $phpgw_info, $phpgw_domain;
		$classpart = explode (".", $classname);
		$appname = $classpart[0];
		$classname = $classpart[1];
		if (!isset($phpgw_info['flags']['included_classes'][$classname])
		|| !$phpgw_info['flags']['included_classes'][$classname])
		{
			$phpgw_info['flags']['included_classes'][$classname] = True;   
			include(PHPGW_INCLUDE_ROOT.'/'.$appname.'/inc/class.'.$classname.'.inc.php');
		}
		if ($p1 == '')
		{
			$obj = new $classname;
		}
		else
		{
			$obj = new $classname($p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8,$p9,$p10,$p11,$p12,$p13,$p14,$p15,$p16);
		}
		return $obj;
	}
	/*!
	@function lang
	@abstract function to deal with multilanguage support
	*/
	function lang($key, $m1="", $m2="", $m3="", $m4="", $m5="", $m6="", $m7="", $m8="", $m9="", $m10=""  ) 
	{
		global $phpgw;
		// # TODO: check if $m1 is of type array.
		// If so, use it instead of $m2-$mN (Stephan)
		$vars = array( $m1, $m2, $m3, $m4, $m5, $m6, $m7, $m8, $m9, $m10 );
		$value = $phpgw->translation->translate("$key", $vars );
		return $value;
	}

	/* Just a temp wrapper. ###DELETE_ME#### (Seek3r) */
	function check_code($code)
	{
		 global $phpgw;
		 return $phpgw->common->check_code($code);
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
		global $phpgw, $phpgw_info;

		if (gettype($account_id) == 'integer')
		{
			return $account_id;
		}
		elseif ($account_id == '')
		{
			if ($default_id == '')
			{
				return (isset($phpgw_info['user']['account_id'])?$phpgw_info['user']['account_id']:0);
			}
			elseif (gettype($default_id) == 'string')
			{
				return $phpgw->accounts->name2id($default_id);
			}
			return intval($default_id);
		}
		elseif (gettype($account_id) == 'string')
		{
			if($phpgw->accounts->exists(intval($account_id)) == True)
			{
				return intval($account_id);
			}
			else
			{
				return $phpgw->accounts->name2id($account_id);
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
		echo '<pre>'; print_r($array); echo '</pre>';
	}

	function print_debug($text='')
	{
		global $debugme;
		if (isset($debugme) && $debugme == 'on') { echo 'debug: '.$text.'<br>'; }
	}

//	print_debug('core functions are done');
	/****************************************************************************\
	* Quick verification of sane environment                                     *
	\****************************************************************************/
//	error_reporting(7);
	/* Make sure the header.inc.php is current. */
	if ($phpgw_info['server']['versions']['header'] < $phpgw_info['server']['versions']['current_header'])
	{
		echo '<center><b>You need to port your settings to the new header.inc.php version.</b></center>';
		exit;
	}

	/* Make sure the developer is following the rules. */
	if (!isset($phpgw_info['flags']['currentapp']))
	{
		echo "<b>!!! YOU DO NOT HAVE YOUR \$phpgw_info[\"flags\"][\"currentapp\"] SET !!!";
		echo "<br>!!! PLEASE CORRECT THIS SITUATION !!!</b>";
	}

	magic_quotes_runtime(false);
	print_debug('sane environment');

	/****************************************************************************\
	* Multi-Domain support                                                       *
	\****************************************************************************/

	/* make them fix their header */
	if (!isset($phpgw_domain))
	{
		 echo '<center><b>The administrator must upgrade the header.inc.php file before you can continue.</b></center>';
		 exit;
	}
	reset($phpgw_domain);
	$default_domain = each($phpgw_domain);
	$phpgw_info['server']['default_domain'] = $default_domain[0];
	unset ($default_domain); // we kill this for security reasons

	/* This code will handle virtdomains so that is a user logins with user@domain.com, it will switch into virtualization mode. */
	if (isset($domain))
	{
		$phpgw_info['user']['domain'] = $domain;
	}
	elseif (isset($login) && isset($logindomain))
	{
		if (!ereg ("\@", $login))
		{
			$login = $login."@".$logindomain;
		}
		$phpgw_info['user']['domain'] = $logindomain;
		unset ($logindomain);
	}
	elseif (isset($login) && !isset($logindomain))
	{
		if (ereg ("\@", $login))
		{
			$login_array = explode("@", $login);
			$phpgw_info['user']['domain'] = $login_array[1];
		}
		else
		{
			$phpgw_info['user']['domain'] = $phpgw_info['server']['default_domain'];
			$login = $login . '@' . $phpgw_info['user']['domain'];
		}
	}

	if (@isset($phpgw_domain[$phpgw_info['user']['domain']]))
	{
		$phpgw_info['server']['db_host'] = $phpgw_domain[$phpgw_info['user']['domain']]['db_host'];
		$phpgw_info['server']['db_name'] = $phpgw_domain[$phpgw_info['user']['domain']]['db_name'];
		$phpgw_info['server']['db_user'] = $phpgw_domain[$phpgw_info['user']['domain']]['db_user'];
		$phpgw_info['server']['db_pass'] = $phpgw_domain[$phpgw_info['user']['domain']]['db_pass'];
		$phpgw_info['server']['db_type'] = $phpgw_domain[$phpgw_info['user']['domain']]['db_type'];
	}
	else
	{
		$phpgw_info['server']['db_host'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_host'];
		$phpgw_info['server']['db_name'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_name'];
		$phpgw_info['server']['db_user'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_user'];
		$phpgw_info['server']['db_pass'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_pass'];
		$phpgw_info['server']['db_type'] = $phpgw_domain[$phpgw_info['server']['default_domain']]['db_type'];
	}

	if ($phpgw_info['flags']['currentapp'] != 'login' && ! $phpgw_info['server']['show_domain_selectbox'])
	{
		unset ($phpgw_domain); // we kill this for security reasons  
	}
	unset ($domain); // we kill this to save memory

	@print_debug('domain: '.$phpgw_info['user']['domain']);

	/****************************************************************************\
	* These lines load up the API, fill up the $phpgw_info array, etc            *
	\****************************************************************************/
	/* Load main class */
	$phpgw = CreateObject('phpgwapi.phpgw');
	/************************************************************************\
	* Load up the main instance of the db class.                             *
	\************************************************************************/
	$phpgw->db           = CreateObject('phpgwapi.db');
	$phpgw->db->Host     = $phpgw_info['server']['db_host'];
	$phpgw->db->Type     = $phpgw_info['server']['db_type'];
	$phpgw->db->Database = $phpgw_info['server']['db_name'];
	$phpgw->db->User     = $phpgw_info['server']['db_user'];
	$phpgw->db->Password = $phpgw_info['server']['db_pass'];
	if ($phpgw->debug)
	{
		 $phpgw->db->Debug = 1;
	}

	$phpgw->db->Halt_On_Error = 'no';
	@$phpgw->db->query("select count(*) from phpgw_config");
	if (! @$phpgw->db->next_record())
	{
		$setup_dir = ereg_replace($PHP_SELF,'index.php','setup/');
		echo '<center><b>Fatal Error:</b> It appears that you have not created the database tables for '
			.'phpGroupWare.  Click <a href="' . $setup_dir . '">here</a> to run setup.</center>';
		exit;
	}
	$phpgw->db->Halt_On_Error = 'yes';

	/* Fill phpgw_info["server"] array */
// An Attempt to speed things up using cache premise
	$phpgw->db->query("select config_value from phpgw_config WHERE config_app='phpgwapi' and config_name='cache_phpgw_info'",__LINE__,__FILE__);
	if ($phpgw->db->num_rows())
	{
		$phpgw->db->next_record();
		$phpgw_info['server']['cache_phpgw_info'] = stripslashes($phpgw->db->f('config_value'));
	}

	$cache_query = "select content from phpgw_app_sessions where"
		." sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'";
		
	$phpgw->db->query($cache_query,__LINE__,__FILE__);
	$server_info_cache = $phpgw->db->num_rows();
	
	if(@$phpgw_info['server']['cache_phpgw_info'] && $server_info_cache)
	{
		$phpgw->db->next_record();
		$phpgw_info['server'] = unserialize(stripslashes($phpgw->db->f('content')));
	}
	else
	{	
		$phpgw->db->query("select * from phpgw_config WHERE config_app='phpgwapi'",__LINE__,__FILE__);
		while ($phpgw->db->next_record())
		{
			$phpgw_info['server'][$phpgw->db->f('config_name')] = stripslashes($phpgw->db->f('config_value'));
		}

		if($phpgw_info['server']['cache_phpgw_info'])
		{
			if($server_info_cache)
			{
				$cache_query = "UPDATE phpgw_app_sessions set content='".addslashes(serialize($phpgw_info['server']))."'"
					." WHERE sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'";
			}
			else
			{
				$cache_query = 'INSERT INTO phpgw_app_sessions(sessionid,loginid,app,location,content) VALUES('
					. "'0','0','phpgwapi','config','".addslashes(serialize($phpgw_info['server']))."')";
			}
		}
		$phpgw->db->query($cache_query,__LINE__,__FILE__);
	}
	unset($cache_query);
	unset($server_info_cache);
	/************************************************************************\
	* Required classes                                                       *
	\************************************************************************/
	$phpgw->common       = CreateObject('phpgwapi.common');
	$phpgw->hooks        = CreateObject('phpgwapi.hooks');
	$phpgw->auth         = CreateObject('phpgwapi.auth');
	$phpgw->accounts     = CreateObject('phpgwapi.accounts');
	$phpgw->acl          = CreateObject('phpgwapi.acl');
	$phpgw->session      = CreateObject('phpgwapi.sessions');
	$phpgw->preferences  = CreateObject('phpgwapi.preferences');
	$phpgw->applications = CreateObject('phpgwapi.applications');
	$phpgw->translation  = CreateObject('phpgwapi.translation');
//	$phpgw->datetime = CreateObject('phpgwapi.datetime');
	print_debug('main class loaded');

	/****************************************************************************\
	* This is a global constant that should be used                              *
	* instead of / or \ in file paths                                            *
	\****************************************************************************/
	define('SEP',filesystem_separator());

	/****************************************************************************\
	* Stuff to use if logging in or logging out                                  *
	\****************************************************************************/
	if ($phpgw_info['flags']['currentapp'] == 'login' || $phpgw_info['flags']['currentapp'] == 'logout')
	{
		if ($phpgw_info['flags']['currentapp'] == 'login')
		{
			if (@$login != '')
			{
				$login_array = explode("@",$login);
				$login_id = $phpgw->accounts->name2id($login_array[0]);
				$phpgw->accounts->accounts($login_id);
				$phpgw->preferences->preferences($login_id);
			}
		}
		/****************************************************************************\
		* Everything from this point on will ONLY happen if                          *
		* the currentapp is not login or logout                                      *
		\****************************************************************************/
	}
	else
	{
		if (! $phpgw->session->verify())
		{
			Header('Location: ' . $phpgw->redirect($phpgw->session->link('/login.php','cd=10')));
			exit;
		}

		/* A few hacker resistant constants that will be used throught the program */
		define('PHPGW_TEMPLATE_DIR',$phpgw->common->get_tpl_dir('phpgwapi'));
		define('PHPGW_IMAGES_DIR', $phpgw->common->get_image_path('phpgwapi'));
		define('PHPGW_IMAGES_FILEDIR', $phpgw->common->get_image_dir('phpgwapi'));
		define('PHPGW_APP_ROOT', $phpgw->common->get_app_dir());
		define('PHPGW_APP_INC', $phpgw->common->get_inc_dir());
		define('PHPGW_APP_TPL', $phpgw->common->get_tpl_dir());
		define('PHPGW_IMAGES', $phpgw->common->get_image_path());
		define('PHPGW_APP_IMAGES_DIR', $phpgw->common->get_image_dir());

		define('PHPGW_ACL_READ',1);
		define('PHPGW_ACL_ADD',2);
		define('PHPGW_ACL_EDIT',4);
		define('PHPGW_ACL_DELETE',8);
		define('PHPGW_ACL_PRIVATE',16);

		/********* This sets the user variables *********/
		$phpgw_info['user']['private_dir'] = $phpgw_info['server']['files_dir']
											. '/users/'.$phpgw_info['user']['userid'];

		/* This will make sure that a user has the basic default prefs. If not it will add them */
		$phpgw->preferences->verify_basic_settings();

		/********* Optional classes, which can be disabled for performance increases *********/
		while ($phpgw_class_name = each($phpgw_info['flags']))
		{
			if (ereg('enable_',$phpgw_class_name[0]))
			{
				$enable_class = str_replace('enable_','',$phpgw_class_name[0]);
				$enable_class = str_replace('_class','',$enable_class);
				eval('$phpgw->' . $enable_class . ' = createobject(\'phpgwapi.' . $enable_class . '\');');
			}
		}
		unset($enable_class);
		reset($phpgw_info['flags']);

		/*************************************************************************\
		* These lines load up the templates class                                 *
		\*************************************************************************/
		$phpgw->template = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);

		/*************************************************************************\
		* These lines load up the themes                                          *
		\*************************************************************************/
		if (! $phpgw_info['user']['preferences']['common']['theme'])
		{
			if ($phpgw_info['server']['template_set'] == 'user_choice')
			{
				$phpgw_info['user']['preferences']['common']['theme'] = 'default';
			}
			else
			{
				$phpgw_info['user']['preferences']['common']['theme'] = $phpgw_info['server']['template_set'];
			}
		}

		if ($phpgw_info['server']['force_theme'] == 'user_choice')
		{
			$theme_to_load = (isset($phpgw_info['user']['preferences']['common']['theme'])?$phpgw_info['user']['preferences']['common']['theme']:'default');
		}
		else
		{
			$theme_to_load = (isset($phpgw_info['server']['force_theme'])?$phpgw_info['server']['force_theme']:'default');
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
			echo '<body bgcolor="FFFFFF"><b>Fatal error: no themes found</b>';
			exit;
		}
		unset($theme_to_load);

		/*************************************************************************\
		* If they are using frames, we need to set some variables                 *
		\*************************************************************************/
		if (((isset($phpgw_info['user']['preferences']['common']['useframes']) &&
			$phpgw_info['user']['preferences']['common']['useframes']) && 
			$phpgw_info['server']['useframes'] == 'allowed') ||
			($phpgw_info['server']['useframes'] == 'always'))
		{
			$phpgw_info['flags']['navbar_target'] = 'phpgw_body';
		}

		/*************************************************************************\
		* Verify that the users session is still active otherwise kick them out   *
		\*************************************************************************/
		if ($phpgw_info['flags']['currentapp'] != 'home' &&
			$phpgw_info['flags']['currentapp'] != 'preferences' &&
			$phpgw_info['flags']['currentapp'] != 'about')
		{
			// This will need to use ACL in the future
			if (! $phpgw_info['user']['apps'][$phpgw_info['flags']['currentapp']] || (@$phpgw_info['flags']['admin_only'] && ! $phpgw_info['user']['apps']['admin']))
			{
				$phpgw->common->phpgw_header();
				if ($phpgw_info['flags']['noheader'])
				{
					echo parse_navbar();
				}

				echo '<p><center><b>'.lang('Access not permitted').'</b></center>';
				$phpgw->common->phpgw_exit(True);
			}
		}

		/*************************************************************************\
		* Load the header unless the developer turns it off                       *
		\*************************************************************************/
		if (!isset($phpgw_info['flags']['noheader']) ||
			!$phpgw_info['flags']['noheader'])
		{
			$phpgw->common->phpgw_header();
		}

		/*************************************************************************\
		* Load the app include files if the exists                                *
		\*************************************************************************/
		/* Then the include file */
		if (! preg_match ("/phpgwapi/i", PHPGW_APP_INC) && file_exists(PHPGW_APP_INC . '/functions.inc.php'))
		{
			include(PHPGW_APP_INC . '/functions.inc.php');
		}
		if ((!isset($phpgw_info['flags']['noheader']) || 
		     !$phpgw_info['flags']['noheader']) && 
		    (!isset($phpgw_info['flags']['noappheader']) ||
		     !$phpgw_info['flags']['noappheader']) &&
		    file_exists(PHPGW_APP_INC . '/header.inc.php'))
		{
			include(PHPGW_APP_INC . '/header.inc.php');
		}
	}

	error_reporting(E_ERROR | E_WARNING | E_PARSE);

