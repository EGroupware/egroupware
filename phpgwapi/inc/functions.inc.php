<?php
	 /**************************************************************************\
	 * phpGroupWare API - phpgwapi loader                                       *
	 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	 * and Joseph Engo <jengo@phpgroupware.org>                                 *
	 * Has a few functions, but primary role is to load the phpgwapi            *
	 * Copyright (C) 2000 - 2002 Dan Kuykendall                                 *
	 * ------------------------------------------------------------------------ *
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
	* If running in PHP3, then force admin to upgrade			     *
	\****************************************************************************/

	if (floor(phpversion()) == 3)
	{
		echo 'phpGroupWare now requires PHP 4.1 or greater.<br>';
		echo 'Please contact your System Administrator';
		exit;
	}

	include(PHPGW_API_INC.'/common_functions.inc.php');

	function parse_navbar() {}	// just for compatibility with apps, which should run under 0.9.14 too

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
	function lang_char()
	{
		return $GLOBALS['phpgw']->translation->translator_helper;
	}

	/* Just a temp wrapper. ###DELETE_ME#### (Seek3r) */
	function check_code($code)
	{
		return $GLOBALS['phpgw']->common->check_code($code);
	}

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
	list($GLOBALS['phpgw_info']['server']['default_domain']) = each($GLOBALS['phpgw_domain']);

	if (isset($_POST['login']))	// on login
	{
		$GLOBALS['login'] = $_POST['login'];
		if (strstr($GLOBALS['login'],'@') === False)
		{
			$GLOBALS['login'] .= '@' . get_var('logindomain',array('POST'),$GLOBALS['phpgw_info']['server']['default_domain']);
		}
		list(,$GLOBALS['phpgw_info']['user']['domain']) = explode('@',$GLOBALS['login']);
	}
	else	// on "normal" pageview
	{
		$GLOBALS['phpgw_info']['user']['domain'] = get_var('domain', array('GET', 'COOKIE'), FALSE);
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

	@print_debug('domain',$GLOBALS['phpgw_info']['user']['domain'],'api');

	/****************************************************************************\
	* These lines load up the API, fill up the $GLOBALS["phpgw_info"] array, etc *
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
	@$GLOBALS['phpgw']->db->query("SELECT COUNT(config_name) FROM phpgw_config");
	if(!@$GLOBALS['phpgw']->db->next_record())
	{
		$setup_dir = @ereg_replace($_SERVER['PHP_SELF'],'index.php','setup/');
		echo '<center><b>Fatal Error:</b> It appears that you have not created the database tables for '
			.'phpGroupWare.  Click <a href="' . $setup_dir . '">here</a> to run setup.</center>';
		exit;
	}
	$GLOBALS['phpgw']->db->Halt_On_Error = 'yes';

	/****************************************************************************\
	* These lines fill up the $GLOBALS["phpgw_info"]["server"] array             *
	\****************************************************************************/
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
		Header('Location: https://' . $GLOBALS['phpgw_info']['server']['hostname'] . $GLOBALS['phpgw_info']['server']['webserver_url'] . $_SERVER['REQUEST_URI']);
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

	if ($GLOBALS['phpgw_info']['server']['support_old_style_apps'])
	{
		/****************************************************************************\
		* Forcing all non-cooperating apps to send there output through the xslt-tpl *
		\****************************************************************************/
		$GLOBALS['phpgw']->common->start_xslt_capture();
	}

	/****************************************************************************\
	* Forcing the footer to run when the rest of the script is done.             *
	\****************************************************************************/
	register_shutdown_function(array($GLOBALS['phpgw']->common, 'phpgw_final'));

	/****************************************************************************\
	* Stuff to use if logging in or logging out                                  *
	\****************************************************************************/
	if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'login' || $GLOBALS['phpgw_info']['flags']['currentapp'] == 'logout')
	{
		if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'login')
		{
			if (@$_POST['login'] != '')
			{
				list($login) = explode("@",$_POST['login']);
				print_debug('LID',$login,'app');
				$login_id = $GLOBALS['phpgw']->accounts->name2id($login);
				print_debug('User ID',$login_id,'app');
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
		/**************************************************************************\
		* If users session is not valid, send them to login page                   *
		\**************************************************************************/
		if (! $GLOBALS['phpgw']->session->verify())
		{
			$GLOBALS['phpgw']->redirect_link('/login.php','code=10');
		}

		/***************************************************************************\
		* Now that we know we have a good session we can load up the datatime class *
		\***************************************************************************/
		$GLOBALS['phpgw']->datetime = CreateObject('phpgwapi.datetime');

		/*************************************************************************\
		* A few hacker resistant constants that will be used throught the program *
		\*************************************************************************/
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

		$GLOBALS['phpgw']->xslttpl = CreateObject('phpgwapi.xslttemplates',PHPGW_TEMPLATE_DIR);

		/******* Define the GLOBALS['MENUACTION'] *******/
		define('MENUACTION',get_var('menuaction',Array('GET')));

		/********* This sets the user variables (this should be moved to somewhere else [Seek3r])*********/
		$GLOBALS['phpgw_info']['user']['private_dir'] = $GLOBALS['phpgw_info']['server']['files_dir']
			. '/users/'.$GLOBALS['phpgw_info']['user']['userid'];

		/* This will make sure that a user has the basic default prefs. If not it will add them */
		$GLOBALS['phpgw']->preferences->verify_basic_settings();
		$GLOBALS['phpgw']->applications->read_installed_apps();	// to get translated app-titles

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

		/* Verify that user has rights to the currentapp */

		$continue_app_data = True;
		if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'home' &&
			$GLOBALS['phpgw_info']['flags']['currentapp'] != 'about' &&
			$GLOBALS['phpgw_info']['flags']['currentapp'] != 'help')
		{
			// This will need to use ACL in the future
			if (! $GLOBALS['phpgw_info']['user']['apps'][$GLOBALS['phpgw_info']['flags']['currentapp']] ||
				(@$GLOBALS['phpgw_info']['flags']['admin_only'] &&
				! $GLOBALS['phpgw_info']['user']['apps']['admin']))
			{
				$GLOBALS['phpgw']->log->write(array('text'=>'W-Permissions, Attempted to access %1','p1'=>$GLOBALS['phpgw_info']['flags']['currentapp']));
				$GLOBALS['phpgw_info']['flags']['msgbox_data']['Access not permitted']=False;
				$continue_app_data = False;
				//$GLOBALS['phpgw']->template->set_var('phpgw_body',"user has no rights to this app!!!<br>\n");
				exit;
			}
		}

		if($continue_app_data)
		{
			/* Make sure user is keeping his password in order */
			/* Maybe we should create a common function in the phpgw_accounts_shared.inc.php file */
			/* to get rid of duplicate code. */
			if (isset($GLOBALS['phpgw_info']['user']['lastpasswd_change']) && $GLOBALS['phpgw_info']['user']['lastpasswd_change'] == 0)
			{
				$message = lang('You are required to change your password during your first login')
						. '<br> Click this image on the navbar: <img src="'
						. $GLOBALS['phpgw']->common->image('preferences','navbar').'">';
				$GLOBALS['phpgw_info']['flags']['msgbox_data'][$message]=False;
			}
			elseif (isset($GLOBALS['phpgw_info']['user']['lastpasswd_change']) && $GLOBALS['phpgw_info']['user']['lastpasswd_change'] < time() - (86400*30))
			{
				$message = lang('it has been more then %1 days since you changed your password',30);
				$GLOBALS['phpgw_info']['flags']['msgbox_data'][$message]=False;
			}

			$GLOBALS['phpgw']->template->set_root(PHPGW_APP_TPL);

			$GLOBALS['phpgw']->xslttpl->set_root(PHPGW_APP_TPL);

			$GLOBALS['phpgw']->template->halt_on_error = 'report';
			if (! preg_match ("/phpgwapi/i", PHPGW_APP_INC) && file_exists(PHPGW_APP_INC . '/functions.inc.php') && !MENUACTION)
			{
				include(PHPGW_APP_INC . '/functions.inc.php');
			}
		}
	}

	error_reporting(E_ERROR | E_WARNING | E_PARSE);

/*
		These lines load up the templates class and set some default values

		$GLOBALS['phpgw']->template = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);

		$GLOBALS['phpgw']->xslttpl = CreateObject('phpgwapi.xslttemplates',PHPGW_TEMPLATE_DIR);

		 load required tpl files
		$GLOBALS['phpgw']->template->set_file('common', 'common.tpl');
		$GLOBALS['phpgw']->template->set_file('phpgw', 'phpgw.tpl');
		$GLOBALS['phpgw']->template->set_file('msgbox', 'msgbox.tpl');
		
		These default values will be overridden and appended to as needed by template sets
		$GLOBALS['phpgw']->template->set_var('phpgw_top_table_height','0');
		$GLOBALS['phpgw']->template->set_var('phpgw_top_frame_height','0');
		$GLOBALS['phpgw']->template->set_var('phpgw_top_scrolling','AUTO');

		$GLOBALS['phpgw']->template->set_var('phpgw_left_table_width','0');
		$GLOBALS['phpgw']->template->set_var('phpgw_left_frame_width','0');
		$GLOBALS['phpgw']->template->set_var('phpgw_left_scrolling','AUTO');

		$GLOBALS['phpgw']->template->set_var('phpgw_body_table_height','100%');
		$GLOBALS['phpgw']->template->set_var('phpgw_body_table_width','100%');
		$GLOBALS['phpgw']->template->set_var('phpgw_body_frame_height','*');
		$GLOBALS['phpgw']->template->set_var('phpgw_body_frame_width','*');
		$GLOBALS['phpgw']->template->set_var('phpgw_body_scrolling','AUTO');

		$GLOBALS['phpgw']->template->set_var('phpgw_right_table_width','0');
		$GLOBALS['phpgw']->template->set_var('phpgw_right_frame_width','0');
		$GLOBALS['phpgw']->template->set_var('phpgw_right_scrolling','AUTO');

		$GLOBALS['phpgw']->template->set_var('phpgw_bottom_table_height','0');
		$GLOBALS['phpgw']->template->set_var('phpgw_bottom_frame_height','0');
		$GLOBALS['phpgw']->template->set_var('phpgw_bottom_scrolling','AUTO');

		$GLOBALS['phpgw']->template->set_var('phpgw_head_charset',lang('charset'));
		$GLOBALS['phpgw']->template->set_var('phpgw_head_description','phpGroupWare');
		$GLOBALS['phpgw']->template->set_var('phpgw_head_keywords','phpGroupWare');
		$GLOBALS['phpgw']->template->set_var('phpgw_head_base',$GLOBALS['phpgw']->session->link('/'));
		$GLOBALS['phpgw']->template->set_var('phpgw_head_browser_ico','favicon.ico');
		$GLOBALS['phpgw']->template->set_var('phpgw_head_website_title', $GLOBALS['phpgw_info']['server']['site_title']);

		This will bring in the template sets parts definitions
		We do this so early to allow the template to overwrite
		and append to the previous defaults as needed for frames support to work
		if (file_exists(PHPGW_TEMPLATE_DIR . '/parts.inc.php'))
		{
			include(PHPGW_TEMPLATE_DIR . '/parts.inc.php');
		}


		* If they are using frames, we need to set the PHPGW_FRAME_PART safely    *

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
				if just starting up, then we intialize the frameset with the appropriate block 
				$GLOBALS['phpgw']->template->set_var('phpgw_top_link',$GLOBALS['phpgw']->session->link('home.php','framepart=top'));
				$GLOBALS['phpgw']->template->set_var('phpgw_right_link',$GLOBALS['phpgw']->session->link('home.php','framepart=right'));
				$GLOBALS['phpgw']->template->set_var('phpgw_body_link',$GLOBALS['phpgw']->session->link('home.php','framepart=body'));
				$GLOBALS['phpgw']->template->set_var('phpgw_left_link',$GLOBALS['phpgw']->session->link('home.php','framepart=left'));
				$GLOBALS['phpgw']->template->set_var('phpgw_bottom_link',$GLOBALS['phpgw']->session->link('home.php','framepart=bottom'));
				$GLOBALS['phpgw']->template->set_var('phpgw_unupported_link',$GLOBALS['phpgw']->session->link($GLOBALS['HTTP_SERVER_VARS']['SCRIPT_NAME'],'framepart=unsupported'));
				$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_frames_start','phpgw_main_start');
				$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_frames_end','phpgw_main_end');
			}
			else
			{
				if we are using frames and not starting then we use the basic block to keep each part in a nice clean html format
				$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_basic_start','phpgw_main_start');
				$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_basic_end','phpgw_main_end');
			}
		}
		else
		{
			Not using frames, so we default to tables
			define('PHPGW_USE_FRAMES',False);
			define('PHPGW_NAVBAR_TARGET','_self');
			$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_tables_start','phpgw_main_start');
			$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_main_tables_end','phpgw_main_end');
		}
		$GLOBALS['phpgw']->template->set_var('phpgw_head_target',PHPGW_NAVBAR_TARGET);

		Define the GLOBALS['MENUACTION']
		define('MENUACTION',get_var('menuaction',Array('GET')));

		This sets the user variables (this should be moved to somewhere else [Seek3r])
		$GLOBALS['phpgw_info']['user']['private_dir'] = $GLOBALS['phpgw_info']['server']['files_dir']
			. '/users/'.$GLOBALS['phpgw_info']['user']['userid'];

		This will make sure that a user has the basic default prefs. If not it will add them 
		$GLOBALS['phpgw']->preferences->verify_basic_settings();

		Optional classes, which can be disabled for performance increases
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

		* These lines load up the themes data and put them into the templates class *

		//$GLOBALS['phpgw']->common->load_theme_data();
		
		if(!PHPGW_USE_FRAMES || (PHPGW_USE_FRAMES && PHPGW_FRAME_PART != 'body'))
		{
			$GLOBALS['phpgw']->common->navbar();
		}


		* load up top part if appropriate                                         *

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
				exit;
			}
		}

		* load up left part if appropriate                                         *

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
				exit;
			}
		}

		* load up right part if appropriate                                         *

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
				exit;
			}
		}

		* load up bottom part if appropriate                                         *

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
				exit;
			}
		}
		

		* load up body/appspace if appropriate                                         *

		if(!PHPGW_USE_FRAMES || PHPGW_FRAME_PART == 'body')
		{
			// parse_bodypart() should not output anything. This is here for them to set body tags and such
			if(function_exists('parse_bodypart'))
			{
				parse_bodypart();
			}
*/

?>
