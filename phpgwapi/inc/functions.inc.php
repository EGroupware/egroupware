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
	function CreateObject($classname, $constructor_param = "")
	{
		global $phpgw, $phpgw_info, $phpgw_domain;
		$classpart = explode (".", $classname);
		$appname = $classpart[0];
		$classname = $classpart[1];
		if (!$phpgw_info["flags"]["included_classes"][$classname]){
			$phpgw_info["flags"]["included_classes"][$classname] = True;   
			include(PHPGW_INCLUDE_ROOT."/".$appname."/inc/class.".$classname.".inc.php");
		}
		if ($constructor_param == ""){
			$obj = new $classname;
		} else {
			$obj = new $classname($constructor_param);
		}
		return $obj;
	}

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

	function filesystem_separator()
	{
		if (PHP_OS == 'Windows' || PHP_OS == 'OS/2') {
			return '\\';
		} else {
			return '/';
		}
	}

	function print_debug($text)
	{
		global $debugme;
		if ($debugme == "on") { echo 'debug: '.$text.'<br>'; }
	}

	print_debug('core functions are done');
	/****************************************************************************\
	* Quick verification of sane environment                                     *
	\****************************************************************************/
	error_reporting(7);
	/* Make sure the header.inc.php is current. */
	if ($phpgw_info["server"]["versions"]["header"] != $phpgw_info["server"]["versions"]["current_header"]){
		echo "<center><b>You need to port your settings to the new header.inc.php version.</b></center>";
		exit;
	}

	/* Make sure the developer is following the rules. */
	if (!isset($phpgw_info["flags"]["currentapp"])) {
		echo "<b>!!! YOU DO NOT HAVE YOUR \$phpgw_info[\"flags\"][\"currentapp\"] SET !!!";
		echo "<br>!!! PLEASE CORRECT THIS SITUATION !!!</b>";
	}

	magic_quotes_runtime(false);
	print_debug('sane environment');

	/****************************************************************************\
	* Multi-Domain support                                                       *
	\****************************************************************************/

	/* make them fix their header */
	if (!isset($phpgw_domain)) {
		 echo "<center><b>The administration is required to upgrade the header.inc.php file before you can continue.</b></center>";
		 exit;
	}
	reset($phpgw_domain);
	$default_domain = each($phpgw_domain);
	$phpgw_info["server"]["default_domain"] = $default_domain[0];
	unset ($default_domain); // we kill this for security reasons

	/* This code will handle virtdomains so that is a user logins with user@domain.com, it will switch into virtualization mode. */
	if (isset($domain)){
		$phpgw_info["user"]["domain"] = $domain;
	} elseif (isset($login) && isset($logindomain)) {
		if (!ereg ("\@", $login)){
			$login = $login."@".$logindomain;
		}
		$phpgw_info["user"]["domain"] = $logindomain;
		unset ($logindomain);
	} elseif (isset($login) && !isset($logindomain)) {
		if (ereg ("\@", $login)) {
			$login_array = explode("@", $login);
			$phpgw_info["user"]["domain"] = $login_array[1];
		} else {
			$phpgw_info["user"]["domain"] = $phpgw_info["server"]["default_domain"];
			$login = $login."@".$phpgw_info["user"]["domain"];
		}
	}

	if (isset($phpgw_domain[$phpgw_info["user"]["domain"]])){
		$phpgw_info["server"]["db_host"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_host"];
		$phpgw_info["server"]["db_name"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_name"];
		$phpgw_info["server"]["db_user"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_user"];
		$phpgw_info["server"]["db_pass"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_pass"];
		$phpgw_info["server"]["db_type"] = $phpgw_domain[$phpgw_info["user"]["domain"]]["db_type"];
	} else {
		$phpgw_info["server"]["db_host"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_host"];
		$phpgw_info["server"]["db_name"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_name"];
		$phpgw_info["server"]["db_user"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_user"];
		$phpgw_info["server"]["db_pass"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_pass"];
		$phpgw_info["server"]["db_type"] = $phpgw_domain[$phpgw_info["server"]["default_domain"]]["db_type"];
	}

	if ($phpgw_info["flags"]["currentapp"] != "login" && ! $phpgw_info["server"]["show_domain_selectbox"]) {
		unset ($phpgw_domain); // we kill this for security reasons  
	}
	unset ($domain); // we kill this to save memory

	print_debug('domain: '.$phpgw_info["user"]["domain"]);

	// Dont know where to put this (seek3r)
	// This is where it belongs (jengo)
	/* Since LDAP will return system accounts, there are a few we don't want to login. */
	$phpgw_info["server"]["global_denied_users"] = array('root'     => True,
																												'bin'      => True,
																												'daemon'   => True,
																												'adm'      => True,
																												'lp'       => True,
																												'sync'     => True,
																												'shutdown' => True,
																												'halt'     => True,
																												'mail'     => True,
																												'news'     => True,
																												'uucp'     => True,
																												'operator' => True,
																												'games'    => True,
																												'gopher'   => True,
																												'nobody'   => True,
																												'xfs'      => True,
																												'pgsql'    => True,
																												'mysql'    => True,
																												'postgres' => True,
																												'ftp'      => True,
																												'gdm'      => True,
																												'named'    => True
																											);

	/****************************************************************************\
	* These lines load up the API, fill up the $phpgw_info array, etc            *
	\****************************************************************************/
	/* Load main class */
	$phpgw = CreateObject("phpgwapi.phpgw");

	/* Fill phpgw_info["server"] array */
	$phpgw->db->query("select * from phpgw_config",__LINE__,__FILE__);
	while ($phpgw->db->next_record()) {
		$phpgw_info["server"][$phpgw->db->f("config_name")] = stripslashes($phpgw->db->f("config_value"));
	}
	
	$phpgw->load_core_objects();	
	print_debug('main class loaded');

	/****************************************************************************\
	* This is a global constant that should be used                              *
	* instead of / or \ in file paths                                            *
	\****************************************************************************/
	define("SEP",filesystem_separator());
	/* Legacy vars that can be delete after 0.9.11 is release (Seek3r) */
	$sep = SEP;
	$phpgw_info["server"]["dir_separator"] = SEP;

	/****************************************************************************\
	* Stuff to use if logging in or logging out                                  *
	\****************************************************************************/
	if ($phpgw_info["flags"]["currentapp"] == "login" || $phpgw_info["flags"]["currentapp"] == "logout") {
			if ($phpgw_info["flags"]["currentapp"] == "login") {
					if ($login != ""){
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
	} else {
		if (! $phpgw->session->verify()) {
				Header("Location: " . $phpgw->redirect($phpgw->session->link($phpgw_info["server"]["webserver_url"]."/login.php","cd=10")));
				exit;
		}

		/* A few hacker resistant constants that will be used throught the program */

		define("PHPGW_TEMPLATE_DIR",$phpgw->common->get_tpl_dir("phpgwapi"));
		define("PHPGW_IMAGES_DIR", $phpgw->common->get_image_path("phpgwapi"));
		define("PHPGW_IMAGES_FILEDIR", $phpgw->common->get_image_dir("phpgwapi"));
		define("PHPGW_APP_ROOT", $phpgw->common->get_app_dir());
		define("PHPGW_APP_INC", $phpgw->common->get_inc_dir());
		define("PHPGW_APP_TPL", $phpgw->common->get_tpl_dir());
		define("PHPGW_IMAGES", $phpgw->common->get_image_path());
		define("PHPGW_IMAGES_DIR", $phpgw->common->get_image_dir());
		define("PHPGW_ACL_READ",1);
		define("PHPGW_ACL_ADD",2);
		define("PHPGW_ACL_EDIT",4);
		define("PHPGW_ACL_DELETE",8);

		/********* Load up additional phpgw_info["server"] values *********/
		/* LEGACY SUPPORT!!! WILL BE DELETED AFTER 0.9.11 IS RELEASED !!! */
		$phpgw_info["server"]["template_dir"]     = PHPGW_TEMPLATE_DIR;
		$phpgw_info["server"]["images_dir"]       = PHPGW_IMAGES_DIR;
		$phpgw_info["server"]["images_filedir"]   = PHPGW_IMAGES_FILEDIR;
		$phpgw_info["server"]["app_root"]         = PHPGW_APP_ROOT;
		$phpgw_info["server"]["app_inc"]          = PHPGW_APP_INC;
		$phpgw_info["server"]["app_tpl"]          = PHPGW_APP_TPL;
		$phpgw_info["server"]["app_images"]       = PHPGW_IMAGES;
		$phpgw_info["server"]["app_images_dir"]   = PHPGW_IMAGES_DIR;
		/* END LEGACY SUPPORT!!!*/

		/********* This sets the user variables *********/
		$phpgw_info["user"]["private_dir"] = $phpgw_info["server"]["files_dir"]
																			. "/users/".$phpgw_info["user"]["userid"];

		/* This will make sure that a user has the basic default prefs. If not it will add them */
		$phpgw->preferences->verify_basic_settings();
	
		/********* Optional classes, which can be disabled for performance increases *********/
		if ($phpgw_info["flags"]["enable_categories_class"]) {
			$phpgw->categories = CreateObject("phpgwapi.categories");
		}
	
		if ($phpgw_info["flags"]["enable_network_class"]) {
			$phpgw->network = CreateObject("phpgwapi.network");
		}
		
		if ($phpgw_info["flags"]["enable_send_class"]) {
			$phpgw->send = CreateObject("phpgwapi.send");
		}
 
		if ($phpgw_info["flags"]["enable_nextmatchs_class"]) {
			$phpgw->nextmatchs = CreateObject("phpgwapi.nextmatchs");
		}
		
		if ($phpgw_info["flags"]["enable_utilities_class"]) {
			$phpgw->utilities = CreateObject("phpgwapi.utilities");
			$phpgw->utilities->utilities_();
		}
 
		if ($phpgw_info["flags"]["enable_vfs_class"]) {
			$phpgw->vfs = CreateObject("phpgwapi.vfs");
		}

		/*************************************************************************\
		* These lines load up the templates class                                 *
		\*************************************************************************/
		$phpgw->template = CreateObject("phpgwapi.Template", PHPGW_TEMPLATE_DIR);

		/*************************************************************************\
		* These lines load up the themes                                          *
		\*************************************************************************/
		include(PHPGW_SERVER_ROOT . "/phpgwapi/themes/" .
		 $phpgw_info["user"]["preferences"]["common"]["theme"] . ".theme");

		if ($phpgw_info["theme"]["bg_color"] == "") {
			/* Looks like there was a problem finding that theme. Try the default */
			echo "Warning: error locating selected theme";
			include (PHPGW_SERVER_ROOT . "/phpgwapi/themes/default.theme");
			if ($phpgw_info["theme"]["bg_color"] == "") {
				/* Hope we don't get to this point.  Better then the user seeing a */
				/* complety back screen and not know whats going on                */
				echo "<body bgcolor=FFFFFF>Fatal error: no themes found";
				exit;
			}
		}

		/*************************************************************************\
		* If they are using frames, we need to set some variables                 *
		\*************************************************************************/
		if (($phpgw_info["user"]["preferences"]["common"]["useframes"] && $phpgw_info["server"]["useframes"] == "allowed")
		|| ($phpgw_info["server"]["useframes"] == "always")) {
			$phpgw_info["flags"]["navbar_target"] = "phpgw_body";
		}

		/*************************************************************************\
		* Verify that the users session is still active otherwise kick them out   *
		\*************************************************************************/
		if ($phpgw_info["flags"]["currentapp"] != "home" &&
		$phpgw_info["flags"]["currentapp"] != "preferences" &&
		$phpgw_info["flags"]["currentapp"] != "about") {

			if (! $phpgw_info["user"]["apps"][$phpgw_info["flags"]["currentapp"]]) {
				$phpgw->common->phpgw_header();
				echo "<p><center><b>".lang("Access not permitted")."</b></center>";
				$phpgw->common->phpgw_exit(True);
			}
		}

		/*************************************************************************\
		* Load the header unless the developer turns it off                       *
		\*************************************************************************/
		if (! $phpgw_info["flags"]["noheader"]) {
			$phpgw->common->phpgw_header();
		}

		/*************************************************************************\
		* Load the app include files if the exists                                *
		\*************************************************************************/
		/* Then the include file */
		if (! preg_match ("/phpgwapi/i", PHPGW_APP_INC) && file_exists(PHPGW_APP_INC."/functions.inc.php")){
			include(PHPGW_APP_INC . "/functions.inc.php");
		}
		if (!$phpgw_info["flags"]["noheader"] && ! $phpgw_info["flags"]["noappheader"] &&
		file_exists(PHPGW_APP_INC . "/header.inc.php")) {
			include(PHPGW_APP_INC . "/header.inc.php");
		}
	}
	error_reporting(7);
