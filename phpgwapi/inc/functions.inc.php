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
		$msgstring =  '<b>!!! YOU DO NOT HAVE YOUR $GLOBALS[\'phpgw_info\'][\'flags\'][\'currentapp\'] SET !!!<br>!!! PLEASE CORRECT THIS SITUATION !!!</b>';
		$GLOBALS['phpgw_info']['flags']['msgbox_data'][$msgstring]=False;
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
			if(@isset($GLOBALS['phpgw_info']['server']['enforce_ssl']) && $GLOBALS['phpgw_info']['server']['enforce_ssl'] && !$GLOBALS['HTTP_SERVER_VARS']['HTTPS'])
			{
				Header('Location: https://'.$GLOBALS['phpgw_info']['server']['hostname'].$GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']);
				exit;
			}
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
			Header('Location: ' . $GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->session->link('/login.php','code=10')));
			exit;
		}

		$GLOBALS['phpgw']->datetime = CreateObject('phpgwapi.datetime');

		/* A few hacker resistant constants that will be used throught the program */

$GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'] = 'default';
		define('PHPGW_TEMPLATE_DIR', ExecMethod('phpgwapi.phpgw.common.get_tpl_dir', 'phpgwapi'));
		define('PHPGW_IMAGES_DIR', ExecMethod('phpgwapi.phpgw.common.get_image_path', 'phpgwapi'));
		define('PHPGW_IMAGES_FILEDIR', ExecMethod('phpgwapi.phpgw.common.get_image_dir', 'phpgwapi'));
		define('PHPGW_APP_ROOT', ExecMethod('phpgwapi.phpgw.common.get_app_dir'));
		define('PHPGW_APP_INC', ExecMethod('phpgwapi.phpgw.common.get_inc_dir'));
		define('PHPGW_APP_TPL', ExecMethod('phpgwapi.phpgw.common.get_tpl_dir'));
		define('PHPGW_IMAGES', ExecMethod('phpgwapi.phpgw.common.get_image_path'));
		define('PHPGW_APP_IMAGES_DIR', ExecMethod('phpgwapi.phpgw.common.get_image_dir'));

		/*************************************************************************\
		* These lines load up the templates class and set some default values     *
		\*************************************************************************/
		$GLOBALS['phpgw']->template = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
		$GLOBALS['phpgw']->template->set_file('common', 'common.tpl');
		$GLOBALS['phpgw']->template->set_file('phpgw', 'phpgw.tpl');
		$GLOBALS['phpgw']->template->set_file('msgbox', 'msgbox.tpl');
		
		/* This will bring in the template sets parts definitions */
		if (file_exists(PHPGW_TEMPLATE_DIR . '/parts.inc.php'))
		{
			include(PHPGW_TEMPLATE_DIR . '/parts.inc.php');
		}
		$val = $GLOBALS['phpgw']->template->get_var('phpgw_top_height');
		if (empty($val))
		{
			$GLOBALS['phpgw']->template->set_var('phpgw_top_height','10');
		}
		$val = $GLOBALS['phpgw']->template->get_var('phpgw_left_width');
		if (empty($val))
		{
			$GLOBALS['phpgw']->template->set_var('phpgw_left_width','10');
		}
		$val = $GLOBALS['phpgw']->template->get_var('phpgw_right_width');
		if (empty($val))
		{
			$GLOBALS['phpgw']->template->set_var('phpgw_right_width','10');
		}
		$val = $GLOBALS['phpgw']->template->get_var('phpgw_bottom_height');
		if (empty($val))
		{
			$GLOBALS['phpgw']->template->set_var('phpgw_bottom_height','10');
		}

		$GLOBALS['phpgw']->template->set_var('phpgw_head_charset',lang('charset'));
		$GLOBALS['phpgw']->template->set_var('phpgw_head_description','phpGroupWare');
		$GLOBALS['phpgw']->template->set_var('phpgw_head_keywords','phpGroupWare');

		if(@isset($GLOBALS['phpgw_info']['server']['enforce_ssl']) && $GLOBALS['phpgw_info']['server']['enforce_ssl'] && !$GLOBALS['HTTP_SERVER_VARS']['HTTPS'])
		{
			$GLOBALS['phpgw']->template->set_var('phpgw_head_base','https://'.$GLOBALS['phpgw_info']['server']['hostname'].$GLOBALS['phpgw_info']['server']['webserver_url'].'/');
		}
		else
		{
			$GLOBALS['phpgw']->template->set_var('phpgw_head_base',$GLOBALS['phpgw_info']['server']['webserver_url'].'/');
		}
		$GLOBALS['phpgw']->template->set_var('phpgw_head_browser_ico','favicon.ico');
		$GLOBALS['phpgw']->template->set_var('phpgw_head_website_title', $GLOBALS['phpgw_info']['server']['site_title']);

		/*************************************************************************\
		* If they are using frames, we need to set some variables                 *
		\*************************************************************************/
		if(@isset($GLOBALS['HTTP_GET_VARS']['framepart']) && 
			(	$GLOBALS['HTTP_GET_VARS']['framepart'] == 'unsupported' ||
				$GLOBALS['HTTP_GET_VARS']['framepart'] == 'top' ||
				$GLOBALS['HTTP_GET_VARS']['framepart'] == 'left' ||
				$GLOBALS['HTTP_GET_VARS']['framepart'] == 'body' ||
				$GLOBALS['HTTP_GET_VARS']['framepart'] == 'right' ||
				$GLOBALS['HTTP_GET_VARS']['framepart'] == 'bottom'
			))
		{
			define('PHPGW_FRAME_PART',$GLOBALS['HTTP_GET_VARS']['framepart']);
		}
		else
		{
			define('PHPGW_FRAME_PART','start');
		}

//$GLOBALS['phpgw_info']['server']['useframes'] = 'always';		
		if(((isset($GLOBALS['phpgw_info']['user']['preferences']['common']['useframes']) &&	
			$GLOBALS['phpgw_info']['user']['preferences']['common']['useframes'] && 
			$GLOBALS['phpgw_info']['server']['useframes'] == 'allowed') || 
			$GLOBALS['phpgw_info']['server']['useframes'] == 'always') &&	
			PHPGW_FRAME_PART != 'unsupported')
		{
			define('PHPGW_USE_FRAMES',True);
			define('PHPGW_NAVBAR_TARGET','body');
			if (PHPGW_FRAME_PART == 'start')
			{
				$GLOBALS['phpgw']->template->set_var('phpgw_top_link',$GLOBALS['phpgw']->session->link('home.php','framepart=top'));
				$GLOBALS['phpgw']->template->set_var('phpgw_right_link',$GLOBALS['phpgw']->session->link('home.php','framepart=right'));
				$GLOBALS['phpgw']->template->set_var('phpgw_body_link',$GLOBALS['phpgw']->session->link('home.php','framepart=body'));
				$GLOBALS['phpgw']->template->set_var('phpgw_left_link',$GLOBALS['phpgw']->session->link('home.php','framepart=left'));
				$GLOBALS['phpgw']->template->set_var('phpgw_bottom_link',$GLOBALS['phpgw']->session->link('home.php','framepart=bottom'));
				$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_frames','phpgw_main');
			}
			else
			{
				$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_basic','phpgw_main');
			}
		}
		else
		{
			define('PHPGW_USE_FRAMES',False);
			define('PHPGW_NAVBAR_TARGET','_self');
			$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_tables','phpgw_main');
		}
		$GLOBALS['phpgw']->template->set_var('phpgw_head_target',PHPGW_NAVBAR_TARGET);

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

		/* This covers setting the theme values so that each app doesnt have to */
		$theme_data = $GLOBALS['phpgw_info']['theme'];
		unset($theme_data['css']);
		$GLOBALS['phpgw']->template->set_var($theme_data);
		unset($theme_data);
		$GLOBALS['phpgw']->template->update_css();

//		if(!PHPGW_USE_FRAMES || (PHPGW_USE_FRAMES && PHPGW_NAVBAR_TARGET != 'body'))
//		{
			$GLOBALS['phpgw']->common->navbar();
//		}

		/*************************************************************************\
		* load up top part if appropriate                                         *
		\*************************************************************************/
		if(!PHPGW_USE_FRAMES || PHPGW_FRAME_PART == 'top')
		{
			if(!PHPGW_USE_FRAMES)
			{
				$output = 'phpgw_top';
			}
			else
			{
				$output = 'phpgw_body';
			}
			if(function_exists('parse_toppart'))
			{
				parse_toppart($output);
			}
			if(PHPGW_USE_FRAMES)
			{
				$GLOBALS['phpgw']->common->phpgw_footer();
			}
		}
		/*************************************************************************\
		* load up left part if appropriate                                         *
		\*************************************************************************/
		if(!PHPGW_USE_FRAMES || PHPGW_FRAME_PART == 'left')
		{
			if(!PHPGW_USE_FRAMES)
			{
				$output = 'phpgw_left';
			}
			else
			{
				$output = 'phpgw_body';
			}
			if(function_exists('parse_leftpart'))
			{
				parse_leftpart($output);
			}
			if(PHPGW_USE_FRAMES)
			{
				$GLOBALS['phpgw']->common->phpgw_footer();
			}
		}
		/*************************************************************************\
		* load up right part if appropriate                                         *
		\*************************************************************************/
		if(!PHPGW_USE_FRAMES || PHPGW_FRAME_PART == 'right')
		{
			if(!PHPGW_USE_FRAMES)
			{
				$output = 'phpgw_right';
			}
			else
			{
				$output = 'phpgw_body';
			}
			if(function_exists('parse_rightpart'))
			{
				parse_rightpart($output);
			}
			if(PHPGW_USE_FRAMES)
			{
				$GLOBALS['phpgw']->common->phpgw_footer();
			}
		}
		/*************************************************************************\
		* load up bottom part if appropriate                                         *
		\*************************************************************************/
		if(!PHPGW_USE_FRAMES || PHPGW_FRAME_PART == 'bottom')
		{
			if(!PHPGW_USE_FRAMES)
			{
				$output = 'phpgw_bottom';
			}
			else
			{
				$output = 'phpgw_body';
			}
			if(function_exists('parse_bottompart'))
			{
				parse_bottompart($output);
			}
			if(PHPGW_USE_FRAMES)
			{
//				$GLOBALS['phpgw']->common->phpgw_footer();
			}
		}
		
		/*************************************************************************\
		* load up body/appspace if appropriate                                         *
		\*************************************************************************/
		if(!PHPGW_USE_FRAMES || PHPGW_FRAME_PART == 'body')
		{
			/* Verify that user has rights to the currentapp */
			$continue_app_data = True;
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
					$continue_app_data = False;
					$GLOBALS['phpgw']->template->set_var('phpgw_body',"user has no rights to this app!!!<br>\n");
					//$GLOBALS['phpgw']->common->phpgw_display();
					//$GLOBALS['phpgw']->common->phpgw_exit(True);
				}
			}
			if($continue_app_data)
			{
				$GLOBALS['phpgw']->template->set_root(PHPGW_APP_TPL);
				if (! preg_match ("/phpgwapi/i", PHPGW_APP_INC) && file_exists(PHPGW_APP_INC . '/functions.inc.php') && !MENUACTION)
				{
					include(PHPGW_APP_INC . '/functions.inc.php');
				}
			}
		}
	}

	error_reporting(E_ERROR | E_WARNING | E_PARSE);
