<?php
	 /**************************************************************************\
	 * phpGroupWare API - phpgwapi loader                                       *
	 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	 * and Joseph Engo <jengo@phpgroupware.org>                                 *
	 * Has a few functions, but primary role is to load the phpgwapi            *
	 * Copyright (C) 2000, 2001, 2002 Dan Kuykendall                                  *
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
	* If running in PHP3, then load up the support functions file for            *
	* transparent support.                                                       *
	\****************************************************************************/

	if (floor(phpversion()) == 3)
	{
		include(PHPGW_API_INC.'/php3_support_functions.inc.php');
	}

	include(PHPGW_API_INC.'/common_functions.inc.php');
	
	/*!
	 @function lang
	 @abstract function to handle multilanguage support
	*/
	function lang($key,$m1='',$m2='',$m3='',$m4='',$m5='',$m6='',$m7='',$m8='',$m9='',$m10='')
	{
		if(is_array($m1))
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
	 @collection_end direct functions
	*/

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
		/* This object does not exist yet. */
	/*	$GLOBALS['phpgw']->log->write(array('text'=>'W-MissingFlags, currentapp flag not set'));*/

		echo '<b>!!! YOU DO NOT HAVE YOUR $GLOBALS[\'phpgw_info\'][\'flags\'][\'currentapp\'] SET !!!';
		echo '<br>!!! PLEASE CORRECT THIS SITUATION !!!</b>';
	}

	magic_quotes_runtime(false);
	@print_debug('sane environment','messageonly','api');

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

	$GLOBALS['login'] = get_var('login',Array('POST'));
	$GLOBALS['logindomain'] = get_var('logindomain',Array('POST'));

	/* This code will handle virtdomains so that is a user logins with user@domain.com, it will switch into virtualization mode. */
	if (isset($domain) && $domain)
	{
		$GLOBALS['phpgw_info']['user']['domain'] = $domain;
	}
	elseif (isset($GLOBALS['login']) && isset($GLOBALS['logindomain']))
	{
		if (!ereg ("\@", $GLOBALS['login']))
		{
			$GLOBALS['login'] = $GLOBALS['login'] . '@' . $GLOBALS['logindomain'];
		}
		$GLOBALS['phpgw_info']['user']['domain'] = $GLOBALS['logindomain'];
		unset ($GLOBALS['logindomain']);
	}
	elseif (isset($GLOBALS['login']) && !isset($GLOBALS['logindomain']))
	{
		if (ereg ("\@", $GLOBALS['login']))
		{
			$login_array = explode('@', $GLOBALS['login']);
			$GLOBALS['phpgw_info']['user']['domain'] = $login_array[1];
		}
		else
		{
			$GLOBALS['phpgw_info']['user']['domain'] = $GLOBALS['phpgw_info']['server']['default_domain'];
			$GLOBALS['login'] = $GLOBALS['login'] . '@' . $GLOBALS['phpgw_info']['user']['domain'];
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

	@print_debug('domain',$GLOBALS['phpgw_info']['user']['domain'],'api');

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
	@$GLOBALS['phpgw']->db->query("select count(config_name) from phpgw_config");
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

		if(@isset($GLOBALS['phpgw_info']['server']['cache_phpgw_info']))
		{
			if($server_info_cache)
			{
				$cache_query = "DELETE FROM phpgw_app_sessions WHERE sessionid='0' and loginid='0' and app='phpgwapi' and location='config'";
				$GLOBALS['phpgw']->db->query($cache_query,__LINE__,__FILE__);				
			}
			$cache_query = 'INSERT INTO phpgw_app_sessions(sessionid,loginid,app,location,content) VALUES('
				. "'0','0','phpgwapi','config','".addslashes(serialize($GLOBALS['phpgw_info']['server']))."')";
			$GLOBALS['phpgw']->db->query($cache_query,__LINE__,__FILE__);
		}
	}
	unset($cache_query);
	unset($server_info_cache);
	if(@isset($GLOBALS['phpgw_info']['server']['enforce_ssl']) && !$HTTPS)
	{
		Header('Location: https://' . $GLOBALS['phpgw_info']['server']['hostname'] . $GLOBALS['phpgw_info']['server']['webserver_url'] . $REQUEST_URI);
		exit;
	}

	/************************************************************************\
	* Required classes                                                       *
	\************************************************************************/
	$GLOBALS['phpgw']->log          = CreateObject('phpgwapi.errorlog');
	$GLOBALS['phpgw']->translation  = CreateObject('phpgwapi.translation');
	$GLOBALS['phpgw']->common       = CreateObject('phpgwapi.common');
	$GLOBALS['phpgw']->hooks        = CreateObject('phpgwapi.hooks');
	$GLOBALS['phpgw']->auth         = CreateObject('phpgwapi.auth');
	$GLOBALS['phpgw']->accounts     = CreateObject('phpgwapi.accounts');
	$GLOBALS['phpgw']->acl          = CreateObject('phpgwapi.acl');
	$GLOBALS['phpgw']->session      = CreateObject('phpgwapi.sessions');
	$GLOBALS['phpgw']->preferences  = CreateObject('phpgwapi.preferences');
	$GLOBALS['phpgw']->applications = CreateObject('phpgwapi.applications');
	print_debug('main class loaded', 'messageonly','api');
	if (! isset($GLOBALS['phpgw_info']['flags']['included_classes']['error']) ||
		! $GLOBALS['phpgw_info']['flags']['included_classes']['error'])
	{
		include(PHPGW_INCLUDE_ROOT.'/phpgwapi/inc/class.error.inc.php');
		$GLOBALS['phpgw_info']['flags']['included_classes']['error'] = True;
	}

	/****************************************************************************\
	* This is a global constant that should be used                              *
	* instead of / or \ in file paths                                            *
	\****************************************************************************/
	define('SEP',filesystem_separator());

	/*****************************************************************************\
	* ACL defines - moved here to work for xml-rpc/soap, also                     *
	\*****************************************************************************/
	define('PHPGW_ACL_READ',1);
	define('PHPGW_ACL_ADD',2);
	define('PHPGW_ACL_EDIT',4);
	define('PHPGW_ACL_DELETE',8);
	define('PHPGW_ACL_PRIVATE',16);
	define('PHPGW_ACL_GROUP_MANAGERS',32);

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
				print_debug('LID : '.$login_array[0], 'messageonly','api');
				$login_id = $GLOBALS['phpgw']->accounts->name2id($login_array[0]);
				print_debug('User ID : '.$login_id, 'messageonly','api');
				$GLOBALS['phpgw']->accounts->accounts($login_id);
				$GLOBALS['phpgw']->preferences->preferences($login_id);
			}
		}
	/**************************************************************************\
	* Everything from this point on will ONLY happen if                        *
	* the currentapp is not login or logout                                    *
	\**************************************************************************/
	}
	else
	{
		if (! $GLOBALS['phpgw']->session->verify())
		{
			Header('Location: ' . $GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->session->link('/login.php','cd=10')));
			exit;
		}

		$GLOBALS['phpgw']->datetime = CreateObject('phpgwapi.datetime');

		/* A few hacker resistant constants that will be used throught the program */
		define('PHPGW_TEMPLATE_DIR', ExecMethod('phpgwapi.phpgw.common.get_tpl_dir', 'phpgwapi'));
		define('PHPGW_IMAGES_DIR', ExecMethod('phpgwapi.phpgw.common.get_image_path', 'phpgwapi'));
		define('PHPGW_IMAGES_FILEDIR', ExecMethod('phpgwapi.phpgw.common.get_image_dir', 'phpgwapi'));
		define('PHPGW_APP_ROOT', ExecMethod('phpgwapi.phpgw.common.get_app_dir'));
		define('PHPGW_APP_INC', ExecMethod('phpgwapi.phpgw.common.get_inc_dir'));
		define('PHPGW_APP_TPL', ExecMethod('phpgwapi.phpgw.common.get_tpl_dir'));
		define('PHPGW_IMAGES', ExecMethod('phpgwapi.phpgw.common.get_image_path'));
		define('PHPGW_APP_IMAGES_DIR', ExecMethod('phpgwapi.phpgw.common.get_image_dir'));

		/*	define('PHPGW_APP_IMAGES_DIR', $GLOBALS['phpgw']->common->get_image_dir()); */

		/* Moved outside of this logic
		define('PHPGW_ACL_READ',1);
		define('PHPGW_ACL_ADD',2);
		define('PHPGW_ACL_EDIT',4);
		define('PHPGW_ACL_DELETE',8);
		define('PHPGW_ACL_PRIVATE',16);
		*/

		/******* Define the GLOBALS['MENUACTION'] *******/
		define('MENUACTION',get_var('menuaction',Array('GET')));

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
		* These lines load up the themes and CSS data                             *
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
			if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['theme']))
			{
				$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] = 'default';
			}
		}
		else
		{
			if (isset($GLOBALS['phpgw_info']['server']['force_theme']))
			{
				$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] = $GLOBALS['phpgw_info']['server']['force_theme'];
			}
		}

		if(@file_exists(PHPGW_SERVER_ROOT . '/phpgwapi/themes/' . $GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] . '.theme'))
		{
			include(PHPGW_SERVER_ROOT . '/phpgwapi/themes/' . $GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] . '.theme');
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
			$GLOBALS['phpgw']->log->write(array('text'=>'F-Abort, No themes found'));

			exit;
		}

		if (isset($GLOBALS['phpgw_info']['theme']['hovlink'])
			 && ($GLOBALS['phpgw_info']['theme']['hovlink'] != ''))
		{
			$phpgw_info['theme']['css']['A:hover'] = 'text-decoration:none; color: '.$GLOBALS['phpgw_info']['theme']['hovlink'].';';
		}

		$phpgw_info['theme']['css']['A'] = 'text-decoration:none;';
		$phpgw_info['theme']['css']['A:link'] = 'text-decoration:none; color: '.$GLOBALS['phpgw_info']['theme']['link'].';';
		$phpgw_info['theme']['css']['A:visited'] = 'text-decoration:none; color: '.$GLOBALS['phpgw_info']['theme']['vlink'].';';
		$phpgw_info['theme']['css']['A:active'] = 'text-decoration:none; color: '.$GLOBALS['phpgw_info']['theme']['alink'].';';

		if(@file_exists(PHPGW_TEMPLATE_DIR . '/css.inc.php'))
		{
			include(PHPGW_TEMPLATE_DIR . '/css.inc.php');
		}
		if(@file_exists(PHPGW_APP_TPL . '/css.inc.php'))
		{
			include(PHPGW_APP_TPL . '/css.inc.php');
		}
		
		unset($theme_to_load);

		/*************************************************************************\
		* These lines load up the templates class                                 *
		\*************************************************************************/
		if(!@$GLOBALS['phpgw_info']['flags']['disable_Template_class'])
		{
			$GLOBALS['phpgw']->template = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
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
				$GLOBALS['phpgw']->log->write(array('text'=>'W-Permissions, Attempted to access %1','p1'=>$GLOBALS['phpgw_info']['flags']['currentapp']));

				$GLOBALS['phpgw_info']['flags']['msgbox_data']['Access not permitted']=False;
				$GLOBALS['phpgw']->common->phpgw_header();
				$GLOBALS['phpgw']->common->phpgw_exit(True);
			}
		}

		/*************************************************************************\
		* Load the header unless the developer turns it off                       *
		\*************************************************************************/
		$GLOBALS['phpgw']->common->phpgw_header(False, False);

		/*************************************************************************\
		* Load the app include files if the exists                                *
		\*************************************************************************/
		/* Then the include file */
		if (! preg_match ("/phpgwapi/i", PHPGW_APP_INC) && file_exists(PHPGW_APP_INC . '/functions.inc.php') && !MENUACTION)
		{
			include(PHPGW_APP_INC . '/functions.inc.php');
		}

		if (!@$GLOBALS['phpgw_info']['flags']['noappheader'])
		{
			$GLOBALS['phpgw']->common->phpgw_appheader();
		}
	}
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
