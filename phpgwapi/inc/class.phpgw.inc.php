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


	/**
	* Base class
	* Dan Kuykendall dan@kuykendall.org\n
	* Base class. Has a few functions but is more importantly used as a parent class for everything else.\n
	* Written by: Seek3r\n
	* Order: short description - detailed description - doc tags.
	* @package phpgwapi
	* @param  string  A string which identifies the desired class - app.class
	* Syntax: CreateObject('phpgwapi.phpgw'); <br>
	* Example1: $phpgw = CreateObject('phpgwapi.acl'); 
	*/

	class phpgw
	{
		var $accounts;
		var $applications;
		var $acl;
		var $auth;
		 /*! @var db */
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

		/*!
		@function strip_html
		@abstract strips out html chars
		@discussion Author: Seek3r. <br>
		Description: Used as a shortcut for stripping out html special chars. 
		<br>  Example1: $reg_string = strip_html($urlencode_string);
		@param urlencode_string string-the string to be stripped of html special chars.
		@result Object - the string with html special characters removed
		*/
    function strip_html($s)
    {
       return htmlspecialchars(stripslashes($s));
    }

		/*!
		@function link
		@abstract wrapper to session->link()
		@discussion Used for backwards compatibility and as a shortcut. If not url is passed, it will use PHP_SELF <br>
		*/
		function link($url = "", $extravars = "")
		{
			global $phpgw, $phpgw_info, $usercookie, $kp3, $PHP_SELF;
			return $this->session->link($url, $extravars);
		}

		/*!
		@function redirect
		@abstract Handles redirects under iis and apache
		@discussion Author: Seek3r <br>
		Title: Handles redirects under iis and apache <br>
		Description: This function handles redirects under iis and apache 
		it assumes that $phpgw->link() has already been called <br>
		Syntax: string redirect(url as string) <br>
		Example1: None yet
		@param url 
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

		/*!
		 @function lang
		 @abstract Shortcut to tranlation class
		 @discussion Author: Jengo <br>
		 Title: Shortcut to translation class <br>
		 Description: This function is a basic wrapper to translation->translate() <br>
		 Syntax: string redirect(key as string) <br>
		 Example1: None yet
		*/
		function lang($key, $m1 = "", $m2 = "", $m3 = "", $m4 = "") 
		{
			global $phpgw;
			return $this->translation->translate($key);
		}
	/* end of class */
	}
?>
