<?php
	 /**************************************************************************\
	 * eGroupWare API - phpgwapi loader                                         *
	 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	 * and Joseph Engo <jengo@phpgroupware.org>                                 *
	 * Has a few functions, but primary role is to load the phpgwapi            *
	 * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
	 * -------------------------------------------------------------------------*
	 * This library is part of the eGroupWare API                               *
	 * http://www.egroupware.org/api                                            *
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
	
	/***************************************************************************\
	* If running in PHP3, then force admin to upgrade                           *
	\***************************************************************************/

	error_reporting(error_reporting() & ~E_NOTICE);

	if (!function_exists('version_compare'))//version_compare() is only available in PHP4.1+
	{
		echo 'eGroupWare requires PHP 4.1 or greater.<br>';
		echo 'Please contact your System Administrator';
		exit;
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
		$value = $GLOBALS['egw']->translation->translate("$key",$vars);
		return $value;
	}

	/* Make sure the header.inc.php is current. */
	if ($GLOBALS['egw_info']['server']['versions']['header'] < $GLOBALS['egw_info']['server']['versions']['current_header'])
	{
		echo '<center><b>You need to port your settings to the new header.inc.php version by running <a href="setup/manageheader.php">setup/headeradmin</a>.</b></center>';
		exit;
	}

	/* Make sure the developer is following the rules. */
	if (!isset($GLOBALS['egw_info']['flags']['currentapp']))
	{
		/* This object does not exist yet. */
	/*	$GLOBALS['egw']->log->write(array('text'=>'W-MissingFlags, currentapp flag not set'));*/

		echo '<b>!!! YOU DO NOT HAVE YOUR $GLOBALS[\'phpgw_info\'][\'flags\'][\'currentapp\'] SET !!!';
		echo '<br>!!! PLEASE CORRECT THIS SITUATION !!!</b>';
	}

	magic_quotes_runtime(false);
	print_debug('sane environment','messageonly','api');

	/****************************************************************************\
	* Multi-Domain support                                                       *
	\****************************************************************************/
	
	/* make them fix their header */
	if (!isset($GLOBALS['egw_domain']))
	{
		echo '<center><b>The administrator must upgrade the header.inc.php file before you can continue.</b></center>';
		exit;
	}
	if (!isset($GLOBALS['egw_info']['server']['default_domain']) ||	// allow to overwrite the default domain
		!isset($GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]))
	{
		reset($GLOBALS['egw_domain']);
		list($GLOBALS['egw_info']['server']['default_domain']) = each($GLOBALS['egw_domain']);
	}
	if (isset($_POST['login']))	// on login
	{
		$GLOBALS['login'] = $_POST['login'];
		if (strstr($GLOBALS['login'],'@') === False || count($GLOBALS['egw_domain']) == 1)
		{
			$GLOBALS['login'] .= '@' . get_var('logindomain',array('POST'),$GLOBALS['egw_info']['server']['default_domain']);
		}
		$parts = explode('@',$GLOBALS['login']);
		$GLOBALS['egw_info']['user']['domain'] = array_pop($parts);
	}
	else	// on "normal" pageview
	{
		$GLOBALS['egw_info']['user']['domain'] = get_var('domain', array('GET', 'COOKIE'), FALSE);
	}

	if (@isset($GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]))
	{
		$GLOBALS['egw_info']['server']['db_host'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_host'];
		$GLOBALS['egw_info']['server']['db_port'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_port'];
		$GLOBALS['egw_info']['server']['db_name'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_name'];
		$GLOBALS['egw_info']['server']['db_user'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_user'];
		$GLOBALS['egw_info']['server']['db_pass'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_pass'];
		$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_type'];
	}
	else
	{
		$GLOBALS['egw_info']['server']['db_host'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_host'];
		$GLOBALS['egw_info']['server']['db_port'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_port'];
		$GLOBALS['egw_info']['server']['db_name'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_name'];
		$GLOBALS['egw_info']['server']['db_user'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_user'];
		$GLOBALS['egw_info']['server']['db_pass'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_pass'];
		$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_type'];
	}

	$domain_names = array_keys($GLOBALS['egw_domain']);
	if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login' && ! $GLOBALS['egw_info']['server']['show_domain_selectbox'])
	{
		unset ($GLOBALS['egw_domain']); // we kill this for security reasons
	}

	print_debug('domain',@$GLOBALS['egw_info']['user']['domain'],'api');

	 /****************************************************************************\
	 * These lines load up the API, fill up the $phpgw_info array, etc            *
	 \****************************************************************************/
	 /* Load main class */
	$GLOBALS['egw'] = CreateObject('phpgwapi.egw');
	// for the migration
	$GLOBALS['phpgw'] =& $GLOBALS['egw'];
	 /************************************************************************\
	 * Load up the main instance of the db class.                             *
	 \************************************************************************/
	$GLOBALS['egw']->db           = CreateObject('phpgwapi.db');
	if ($GLOBALS['egw']->debug)
	{
		$GLOBALS['egw']->db->Debug = 1;
	}
	$GLOBALS['egw']->db->Halt_On_Error = 'no';
	$GLOBALS['egw']->db->connect(
		$GLOBALS['egw_info']['server']['db_name'],
		$GLOBALS['egw_info']['server']['db_host'],
		$GLOBALS['egw_info']['server']['db_port'],
		$GLOBALS['egw_info']['server']['db_user'],
		$GLOBALS['egw_info']['server']['db_pass'],
		$GLOBALS['egw_info']['server']['db_type']
	);
	@$GLOBALS['egw']->db->query("SELECT COUNT(config_name) FROM phpgw_config");
	if(!@$GLOBALS['egw']->db->next_record())
	{
		$setup_dir = str_replace($_SERVER['PHP_SELF'],'index.php','setup/');
		echo '<center><b>Fatal Error:</b> It appears that you have not created the database tables for '
			.'eGroupWare.  Click <a href="' . $setup_dir . '">here</a> to run setup.</center>';
		exit;
	}
	$GLOBALS['egw']->db->Halt_On_Error = 'yes';

	// Set the DB's client charset if a system-charset is set
	$GLOBALS['egw']->db->query("select config_value from phpgw_config WHERE config_app='phpgwapi' and config_name='system_charset'",__LINE__,__FILE__);
	if ($GLOBALS['egw']->db->next_record() && $GLOBALS['egw']->db->f(0))
	{
		$GLOBALS['egw']->db->Link_ID->SetCharSet($GLOBALS['egw']->db->f(0));
	}	
	/* Fill phpgw_info["server"] array */
	// An Attempt to speed things up using cache premise
	$GLOBALS['egw']->db->query("select config_value from phpgw_config WHERE config_app='phpgwapi' and config_name='cache_phpgw_info'",__LINE__,__FILE__);
	if ($GLOBALS['egw']->db->num_rows())
	{
		$GLOBALS['egw']->db->next_record();
		$GLOBALS['egw_info']['server']['cache_phpgw_info'] = stripslashes($GLOBALS['egw']->db->f('config_value'));
	}

	$cache_query = "select content from phpgw_app_sessions where"
		." sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'";

	$GLOBALS['egw']->db->query($cache_query,__LINE__,__FILE__);
	$server_info_cache = $GLOBALS['egw']->db->num_rows();

	if(@$GLOBALS['egw_info']['server']['cache_phpgw_info'] && $server_info_cache)
	{
		$GLOBALS['egw']->db->next_record();
		$GLOBALS['egw_info']['server'] = unserialize(stripslashes($GLOBALS['egw']->db->f('content')));
	}
	else
	{
		$GLOBALS['egw']->db->query("select * from phpgw_config WHERE config_app='phpgwapi'",__LINE__,__FILE__);
		while ($GLOBALS['egw']->db->next_record())
		{
			$GLOBALS['egw_info']['server'][$GLOBALS['egw']->db->f('config_name')] = stripslashes($GLOBALS['egw']->db->f('config_value'));
		}

		if(@isset($GLOBALS['egw_info']['server']['cache_phpgw_info']))
		{
			if($server_info_cache)
			{
				$cache_query = "DELETE FROM phpgw_app_sessions WHERE sessionid='0' and loginid='0' and app='phpgwapi' and location='config'";
				$GLOBALS['egw']->db->query($cache_query,__LINE__,__FILE__);
			}
			$cache_query = 'INSERT INTO phpgw_app_sessions(sessionid,loginid,app,location,content) VALUES('
				. "'0','0','phpgwapi','config','".addslashes(serialize($GLOBALS['egw_info']['server']))."')";
			$GLOBALS['egw']->db->query($cache_query,__LINE__,__FILE__);
		}
	}
	unset($cache_query);
	unset($server_info_cache);
	if(@isset($GLOBALS['egw_info']['server']['enforce_ssl']) && !$_SERVER['HTTPS'])
	{
		Header('Location: https://' . $GLOBALS['egw_info']['server']['hostname'] . $GLOBALS['egw_info']['server']['webserver_url'] . $_SERVER['REQUEST_URI']);
		exit;
	}

	/****************************************************************************\
	* This is a global constant that should be used                              *
	* instead of / or \ in file paths                                            *
	\****************************************************************************/
	define('SEP',filesystem_separator());

	/************************************************************************\
	* Required classes                                                       *
	\************************************************************************/
	$GLOBALS['egw']->log			= CreateObject('phpgwapi.errorlog');
	$GLOBALS['egw']->translation  	= CreateObject('phpgwapi.translation');
	$GLOBALS['egw']->common       	= CreateObject('phpgwapi.common');
	$GLOBALS['egw']->hooks        	= CreateObject('phpgwapi.hooks');
	$GLOBALS['egw']->auth         	= CreateObject('phpgwapi.auth');
	$GLOBALS['egw']->accounts     	= CreateObject('phpgwapi.accounts');
	$GLOBALS['egw']->acl          	= CreateObject('phpgwapi.acl');
	$GLOBALS['egw']->session      	= CreateObject('phpgwapi.sessions',$domain_names);
	$GLOBALS['egw']->preferences  	= CreateObject('phpgwapi.preferences');
	$GLOBALS['egw']->applications 	= CreateObject('phpgwapi.applications');
	$GLOBALS['egw']->contenthistory	= CreateObject('phpgwapi.contenthistory');
	print_debug('main class loaded', 'messageonly','api');
	if (! isset($GLOBALS['egw_info']['flags']['included_classes']['error']) ||
		! $GLOBALS['egw_info']['flags']['included_classes']['error'])
	{
		include(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.error.inc.php');
		$GLOBALS['egw_info']['flags']['included_classes']['error'] = True;
	}

	/*****************************************************************************\
	* ACL defines - moved here to work for xml-rpc/soap, also                     *
	\*****************************************************************************/
	define('EGW_ACL_READ',1);
	define('EGW_ACL_ADD',2);
	define('EGW_ACL_EDIT',4);
	define('EGW_ACL_DELETE',8);
	define('EGW_ACL_PRIVATE',16);
	define('EGW_ACL_GROUP_MANAGERS',32);
	define('EGW_ACL_CUSTOM_1',64);
	define('EGW_ACL_CUSTOM_2',128);
	define('EGW_ACL_CUSTOM_3',256);
	// and the old ones
	define('PHPGW_ACL_READ',1);
	define('PHPGW_ACL_ADD',2);
	define('PHPGW_ACL_EDIT',4);
	define('PHPGW_ACL_DELETE',8);
	define('PHPGW_ACL_PRIVATE',16);
	define('PHPGW_ACL_GROUP_MANAGERS',32);
	define('PHPGW_ACL_CUSTOM_1',64);
	define('PHPGW_ACL_CUSTOM_2',128);
	define('PHPGW_ACL_CUSTOM_3',256);

	/****************************************************************************\
	* Forcing the footer to run when the rest of the script is done.             *
	\****************************************************************************/
	register_shutdown_function(array($GLOBALS['egw']->common, 'egw_final'));

	/****************************************************************************\
	* Stuff to use if logging in or logging out                                  *
	\****************************************************************************/
	if ($GLOBALS['egw_info']['flags']['currentapp'] == 'login' || $GLOBALS['egw_info']['flags']['currentapp'] == 'logout')
	{
		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'login')
		{
			if (@$_POST['login'] != '')
			{
				if (count($GLOBALS['egw_domain']) > 1)
				{
					list($login) = explode('@',$_POST['login']);
				}
				else
				{
					$login = $_POST['login'];
				}
				print_debug('LID',$login,'app');
				$login_id = $GLOBALS['egw']->accounts->name2id($login);
				print_debug('User ID',$login_id,'app');
				$GLOBALS['egw']->accounts->accounts($login_id);
				$GLOBALS['egw']->preferences->preferences($login_id);
				$GLOBALS['egw']->datetime = CreateObject('phpgwapi.datetime');
			}
		}
	/**************************************************************************\
	* Everything from this point on will ONLY happen if                        *
	* the currentapp is not login or logout                                    *
	\**************************************************************************/
	}
	else
	{
		if (! $GLOBALS['egw']->session->verify())
		{
			// we forward to the same place after the re-login
			if ($GLOBALS['egw_info']['server']['webserver_url'] && $GLOBALS['egw_info']['server']['webserver_url'] != '/')
			{
				list(,$relpath) = explode($GLOBALS['egw_info']['server']['webserver_url'],$_SERVER['PHP_SELF'],2);
			}
			else	// the webserver-url is empty or just a slash '/' (eGW is installed in the docroot and no domain given)
			{
				if (preg_match('/^https?:\/\/[^\/]*\/(.*)$/',$relpath=$_SERVER['PHP_SELF'],$matches))
				{
					$relpath = $matches[1];
				}
			}
			// this removes the sessiondata if its saved in the URL
			$query = preg_replace('/[&]?sessionid(=|%3D)[^&]+&kp3(=|%3D)[^&]+&domain=.*$/','',$_SERVER['QUERY_STRING']);
			Header('Location: '.$GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=10&phpgw_forward='.urlencode($relpath.(!empty($query) ? '?'.$query : '')));
			exit;
		}

		$GLOBALS['egw']->datetime = CreateObject('phpgwapi.datetime');

		/* A few hacker resistant constants that will be used throught the program */
		define('EGW_TEMPLATE_DIR', $GLOBALS['egw']->common->get_tpl_dir('phpgwapi'));
		define('EGW_IMAGES_DIR', $GLOBALS['egw']->common->get_image_path('phpgwapi'));
		define('EGW_IMAGES_FILEDIR', $GLOBALS['egw']->common->get_image_dir('phpgwapi'));
		define('EGW_APP_ROOT', $GLOBALS['egw']->common->get_app_dir());
		define('EGW_APP_INC', $GLOBALS['egw']->common->get_inc_dir());
		define('EGW_APP_TPL', $GLOBALS['egw']->common->get_tpl_dir());
		define('EGW_IMAGES', $GLOBALS['egw']->common->get_image_path());
		define('EGW_APP_IMAGES_DIR', $GLOBALS['egw']->common->get_image_dir());
		// and the old ones
		define('PHPGW_TEMPLATE_DIR', $GLOBALS['egw']->common->get_tpl_dir('phpgwapi'));
		define('PHPGW_IMAGES_DIR', $GLOBALS['egw']->common->get_image_path('phpgwapi'));
		define('PHPGW_IMAGES_FILEDIR', $GLOBALS['egw']->common->get_image_dir('phpgwapi'));
		define('PHPGW_APP_ROOT', $GLOBALS['egw']->common->get_app_dir());
		define('PHPGW_APP_INC', $GLOBALS['egw']->common->get_inc_dir());
		define('PHPGW_APP_TPL', $GLOBALS['egw']->common->get_tpl_dir());
		define('PHPGW_IMAGES', $GLOBALS['egw']->common->get_image_path());
		define('PHPGW_APP_IMAGES_DIR', $GLOBALS['egw']->common->get_image_dir());

		/********* This sets the user variables *********/
		$GLOBALS['egw_info']['user']['private_dir'] = $GLOBALS['egw_info']['server']['files_dir']
			. '/users/'.$GLOBALS['egw_info']['user']['userid'];

		/* This will make sure that a user has the basic default prefs. If not it will add them */
		$GLOBALS['egw']->preferences->verify_basic_settings();

		/********* Optional classes, which can be disabled for performance increases *********/
		while ($phpgw_class_name = each($GLOBALS['egw_info']['flags']))
		{
			if (ereg('enable_',$phpgw_class_name[0]))
			{
				$enable_class = str_replace('enable_','',$phpgw_class_name[0]);
				$enable_class = str_replace('_class','',$enable_class);
				eval('$GLOBALS["phpgw"]->' . $enable_class . ' = createobject(\'phpgwapi.' . $enable_class . '\');');
			}
		}
		unset($enable_class);
		reset($GLOBALS['egw_info']['flags']);

		/*************************************************************************\
		* These lines load up the templates class                                 *
		\*************************************************************************/
		if(!@$GLOBALS['egw_info']['flags']['disable_Template_class'])
		{
			$GLOBALS['egw']->template = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
		}

		/*************************************************************************\
		* These lines load up the themes                                          *
		\*************************************************************************/
		if (! $GLOBALS['egw_info']['user']['preferences']['common']['theme'])
		{
			if (@$GLOBALS['egw_info']['server']['template_set'] == 'user_choice')
			{
				$GLOBALS['egw_info']['user']['preferences']['common']['theme'] = 'default';
			}
			else
			{
				$GLOBALS['egw_info']['user']['preferences']['common']['theme'] = $GLOBALS['egw_info']['server']['template_set'];
			}
		}
		if (@$GLOBALS['egw_info']['server']['force_theme'] == 'user_choice')
		{
			if (!isset($GLOBALS['egw_info']['user']['preferences']['common']['theme']))
			{
				$GLOBALS['egw_info']['user']['preferences']['common']['theme'] = 'default';
			}
		}
		else
		{
			if (isset($GLOBALS['egw_info']['server']['force_theme']))
			{
				$GLOBALS['egw_info']['user']['preferences']['common']['theme'] = $GLOBALS['egw_info']['server']['force_theme'];
			}
		}

		if(@file_exists(EGW_SERVER_ROOT . '/phpgwapi/themes/' . $GLOBALS['egw_info']['user']['preferences']['common']['theme'] . '.theme'))
		{
			include(EGW_SERVER_ROOT . '/phpgwapi/themes/' . $GLOBALS['egw_info']['user']['preferences']['common']['theme'] . '.theme');
		}
		elseif(@file_exists(EGW_SERVER_ROOT . '/phpgwapi/themes/default.theme'))
		{
			include(EGW_SERVER_ROOT . '/phpgwapi/themes/default.theme');
		}
		else
		{
			/* Hope we don't get to this point.  Better then the user seeing a */
			/* complety back screen and not know whats going on                */
			echo '<body bgcolor="FFFFFF">';
			$GLOBALS['egw']->log->write(array('text'=>'F-Abort, No themes found'));

			exit;
		}
		unset($theme_to_load);

		/*************************************************************************\
		* If they are using frames, we need to set some variables                 *
		\*************************************************************************/
		if (((isset($GLOBALS['egw_info']['user']['preferences']['common']['useframes']) &&
			$GLOBALS['egw_info']['user']['preferences']['common']['useframes']) &&
			$GLOBALS['egw_info']['server']['useframes'] == 'allowed') ||
			($GLOBALS['egw_info']['server']['useframes'] == 'always'))
		{
			$GLOBALS['egw_info']['flags']['navbar_target'] = 'phpgw_body';
		}

		/*************************************************************************\
		* Verify that the users session is still active otherwise kick them out   *
		\*************************************************************************/
		if ($GLOBALS['egw_info']['flags']['currentapp'] != 'home' &&
			$GLOBALS['egw_info']['flags']['currentapp'] != 'about')
		{
			// This will need to use ACL in the future
			if (! $GLOBALS['egw_info']['user']['apps'][$GLOBALS['egw_info']['flags']['currentapp']] ||
				(@$GLOBALS['egw_info']['flags']['admin_only'] &&
				! $GLOBALS['egw_info']['user']['apps']['admin']))
			{
				$GLOBALS['egw']->common->phpgw_header();
				if ($GLOBALS['egw_info']['flags']['noheader'])
				{
					echo parse_navbar();
				}

				$GLOBALS['egw']->log->write(array('text'=>'W-Permissions, Attempted to access %1','p1'=>$GLOBALS['egw_info']['flags']['currentapp']));

				echo '<p><center><b>'.lang('Access not permitted').'</b></center>';
				$GLOBALS['egw']->common->phpgw_exit(True);
			}
		}

		if(!is_object($GLOBALS['egw']->datetime))
		{
			$GLOBALS['egw']->datetime = CreateObject('phpgwapi.datetime');
		}
		$GLOBALS['egw']->applications->read_installed_apps();	// to get translated app-titles
		
		/*************************************************************************\
		* Load the header unless the developer turns it off                       *
		\*************************************************************************/
		if (!@$GLOBALS['egw_info']['flags']['noheader'])
		{
			$GLOBALS['egw']->common->phpgw_header();
		}

		/*************************************************************************\
		* Load the app include files if the exists                                *
		\*************************************************************************/
		/* Then the include file */
		if (PHPGW_APP_INC != "" &&
                   ! preg_match ("/phpgwapi/i", PHPGW_APP_INC) &&
                   file_exists(PHPGW_APP_INC . '/functions.inc.php') &&
                   !isset($_GET['menuaction']))
		{
			include(PHPGW_APP_INC . '/functions.inc.php');
		}
		if (!@$GLOBALS['egw_info']['flags']['noheader'] &&
			!@$GLOBALS['egw_info']['flags']['noappheader'] &&
			file_exists(PHPGW_APP_INC . '/header.inc.php') && !isset($_GET['menuaction']))
		{
			include(PHPGW_APP_INC . '/header.inc.php');
		}
	}
