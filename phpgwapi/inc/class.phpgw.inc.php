<?php
	/**************************************************************************\
	* phpGroupWare API - Core class and functions for phpGroupWare             *
	* This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	* and Joseph Engo <jengo@phpgroupware.org>                                 *
	* This is the central class for the phpGroupWare API                       *
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
	* Our API class starts here                                                  *
	\****************************************************************************/
	class phpgw
	{
		var $accounts;
		var $applications;
		var $acl;
		var $auth;
		var $db;
		var $debug = 0;		// This will turn on debugging information.
		var $crypto;
		var $categories;
		var $common;
		var $hooks;
		var $network;
		var $nextmatchs;
		var $preferences;
		var $session;
		var $send;
		var $template;
		var $translation;
		var $utilities;
		var $vfs;
		var $calendar;
		var $msg;
		var $addressbook;
		var $todo;

		// This is here so you can decied what the best way to handle bad sessions
		// You could redirect them to login.php with code 2 or use the default
		// I recommend using the default until all of the bugs are worked out.

		function phpgw_()
		{
			global $phpgw_info, $sessionid, $login;
			/************************************************************************\
			* Required classes                                                       *
			\************************************************************************/
			$this->db           = CreateObject("phpgwapi.db");
			$this->db->Host     = $phpgw_info["server"]["db_host"];
			$this->db->Type     = $phpgw_info["server"]["db_type"];
			$this->db->Database = $phpgw_info["server"]["db_name"];
			$this->db->User     = $phpgw_info["server"]["db_user"];
			$this->db->Password = $phpgw_info["server"]["db_pass"];
			if ($this->debug) {
				 $this->db->Debug = 1;
			}

			$this->common       = CreateObject("phpgwapi.common");
			$this->hooks        = CreateObject("phpgwapi.hooks");
			$this->auth         = createobject("phpgwapi.auth");
			$this->acl          = CreateObject("phpgwapi.acl");
			$this->accounts     = createobject("phpgwapi.accounts");
			$this->session      = CreateObject("phpgwapi.sessions");
			$this->preferences  = CreateObject("phpgwapi.preferences");
			$this->applications = CreateObject("phpgwapi.applications");
			$this->translation  = CreateObject("phpgwapi.translation");
		} 

		/**************************************************************************\
		* Core functions                                                           *
		\**************************************************************************/

		/* Wrapper to the session->link() */
		function link($url = "", $extravars = "")
		{
			global $phpgw, $phpgw_info, $usercookie, $kp3, $PHP_SELF;
			return $this->session->link($url, $extravars);
		}

		function redirect($url = "")
		{
			// This function handles redirects under iis and apache
			// it assumes that $phpgw->link() has already been called

			global $HTTP_ENV_VARS;

			$iis = strpos($HTTP_ENV_VARS["SERVER_SOFTWARE"], "IIS", 0);
			
			if ( !$url ) {
				$url = $PHP_SELF;
			}
			if ( $iis ) {
				echo "\n<HTML>\n<HEAD>\n<TITLE>Redirecting to $url</TITLE>";
				echo "\n<META HTTP-EQUIV=REFRESH CONTENT=\"0; URL=$url\">";
				echo "\n</HEAD><BODY>";
				echo "<H3>Please continue to <a href=\"$url\">this page</a></H3>";
				echo "\n</BODY></HTML>";
				exit;
			} else {
				Header("Location: $url");
				print("\n\n");
				exit;
			}
		}

		function lang($key, $m1 = "", $m2 = "", $m3 = "", $m4 = "") 
		{
			global $phpgw;
			return $this->translation->translate($key);
		}
	}	//end phpgw class
?>