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
	* Parent class for the phpgwAPI
	* Parent class. Has a few functions but is more importantly used as a parent class for everything else.
	* @author	Dan Kuykendall <dan@kuykendall.org>
	* @copyright LGPL
	* @package phpgwapi
	* @access	public
	*/

	class phpgw
	{
		var $accounts;
		var $applications;
		var $acl;
		var $auth;
		var $db; 
		/**
		 * Turn on debug mode. Will output additional data for debugging purposes.
		 * @var	string	$debug
		 * @access	public
		 */	
		var $debug = 0;		// This will turn on debugging information.
		var $crypto;
		var $categories;
		var $common;
		var $datetime;
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

		/**
		 * Strips out html chars
		 *
		 * Used as a shortcut for stripping out html special chars. 
		 *
		 * @access	public
		 *	@param $s string  The string to have its html special chars stripped out.
		 * @return string  The string with html special characters removed
		 * @syntax strip_html($string)
		 * @example $reg_string = strip_html($urlencode_string);
		 */
		function strip_html($s)
		{
			return htmlspecialchars(stripslashes($s));
		}

		/**
		 * Link url generator
		 *
		 * Used for backwards compatibility and as a shortcut. If no url is passed, it will use PHP_SELF. Wrapper to session->link()
		 *
		 * @access	public
		 *	@param	string	$string	The url the link is for
		 *	@param  string	$extravars	Extra params to be passed to the url
		 * @return string	The full url after processing
		 * @see	session->link()
		 * @syntax link($string, $extravars)
		 * @example None yet
		 */
		function link($url = '', $extravars = '')
		{
			/* global $phpgw, $phpgw_info, $usercookie, $kp3, $PHP_SELF; */
			return $this->session->link($url, $extravars);
		}

		/**
		 * Handles redirects under iis and apache
		 *
		 * This function handles redirects under iis and apache it assumes that $phpgw->link() has already been called
		 *
		 * @access	public
		 *	@param  string The url ro redirect to
		 * @syntax redirect(key as string)
		 * @example None yet
		 */
		function redirect($url = '')
		{
			/* global $HTTP_ENV_VARS; */

			$iis = @strpos($GLOBALS['HTTP_ENV_VARS']['SERVER_SOFTWARE'], 'IIS', 0);
			
			if ( !$url )
			{
				$url = $GLOBALS['PHP_SELF'];
			}
			if ( $iis )
			{
				echo "\n<HTML>\n<HEAD>\n<TITLE>Redirecting to $url</TITLE>";
				echo "\n<META HTTP-EQUIV=REFRESH CONTENT=\"0; URL=$url\">";
				echo "\n</HEAD><BODY>";
				echo "<H3>Please continue to <a href=\"$url\">this page</a></H3>";
				echo "\n</BODY></HTML>";
				exit;
			}
			else
			{
				Header("Location: $url");
				print("\n\n");
				exit;
			}
		}

		/**
		 * Shortcut to translation class
		 *
		 * This function is a basic wrapper to translation->translate()
		 *
		 * @access	public
		 *	@param  string	The key for the phrase
		 *	@param  string	the first additional param
		 *	@param  string	the second additional param
		 *	@param  string	the thrid additional param
		 *	@param  string	the fourth additional param
		 * @see	translation->translate()
		 */
		function lang($key, $m1 = '', $m2 = '', $m3 = '', $m4 = '') 
		{
			/* global $phpgw; */
			return $this->translation->translate($key);
		}
	} /* end of class */
?>
