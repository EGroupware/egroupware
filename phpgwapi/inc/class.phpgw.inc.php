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
	
	/*!!
	* @Type: class
	* @Name: phpgw
	* @Author: Seek3r
	* @Title: Main class.
	* @Description: Main class. Has a few functions but is more importantly used as a parent class for everything else.
	* @Syntax: CreateObject('phpgwapi.phpgw');
	* @Example1: $phpgw = CreateObject('phpgwapi.acl');
	*/
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

		/**************************************************************************\
		* Core functions                                                           *
		\**************************************************************************/

		/*!!
		* @Type: function
		* @Name: strip_html
		* @Author: Seek3r
		* @Title: Strips out html chars. 
		* @Description: Used as a shortcut for stripping out html special chars.
		* @Syntax: string strip_html(string as string)
		* @Example1: $reg_string = strip_html($urlencode_string);
		*/
    function strip_html($s)
    {
       return htmlspecialchars(stripslashes($s));
    }

		/*!!
		* @Type: function
		* @Name: link
		* @Author: Seek3r
		* @Title: Wrapper to session->link() 
		* @Description: Used for backward compatibility and as a shortcut.
		* If no url is passed, it will use PHP_SELF
		* @Syntax: string link(url as string, extra_vars as string)
		* @Example1: <a href="<?php echo $phpgw->link('otherpage.php');?>">click here</a>
		*/
		function link($url = "", $extravars = "")
		{
			global $phpgw, $phpgw_info, $usercookie, $kp3, $PHP_SELF;
			return $this->session->link($url, $extravars);
		}

		/*!!
		* @Type: function
		* @Name: link
		* @Author: Seek3r
		* @Title: Handles redirects under iis and apache
		* @Description: This function handles redirects under iis and apache
		* it assumes that $phpgw->link() has already been called
		* @Syntax: string redirect(url as string)
		* @Example1: None yet
		*/
		function redirect($url = "")
		{

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

		/*!!
		* @Type: function
		* @Name: lang
		* @Author: Jengo
		* @Title: Shortcut to translation class
		* @Description: This function is a basic wrapper to translation->translate()
		* @Syntax: string redirect(key as string)
		* @Example1: None yet
		*/
		function lang($key, $m1 = "", $m2 = "", $m3 = "", $m4 = "") 
		{
			global $phpgw;
			return $this->translation->translate($key);
		}
	/* end of class */
	}
?>
