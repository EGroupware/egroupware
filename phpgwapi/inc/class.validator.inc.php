<?php
  /**************************************************************************\
  * phpGroupWare API - Validator                                             *
  * This file written by Dave Hall <skwashd@phpgroupware.org>                *
  * Copyright (C) 2003 Free Software Foundation                              *
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

	/*
	The code that used to be here was non-free code from www.thewebmasters.net
	This file has been stubbed and will soon be removed from phpGW 
	*/

	class validator
	{
	 var $error;

		function clear_error ()
		{
			$this->nonfree_call();
		}

		/* check if string contains any whitespace */
		function has_space ($text)
		{
			return ereg('( |\n|\t|\r)+', $text);
		}

		function chconvert ($fragment)
		{
			$this->nonfree_call();
		}

		function get_perms ($fileName)
		{
			$this->nonfree_call();
		}

		function is_sane ($filename)
		{
			$this->nonfree_call();
		}

		/* strips all whitespace from a string */
		function strip_space ($text)
		{
			return ereg('( |\n|\t|\r)+', '', $text);
		}

		function is_allnumbers ($text)
		{
			$this->nonfree_call();
		}

		function strip_numbers ($text)
		{
			$this->nonfree_call();
		}

		function is_allletters ($text)
		{
			$this->nonfree_call();
		}

		function strip_letters ($text)
		{
			$this->nonfree_call();
		}

		function has_html ($text='')
		{
			return ($text != $this->strip_html($text));
		}

		function strip_html ($text='')
		{
			return strip_tags($text);
		}

		function has_metas ($text='')
		{
			return ($text != $this->strip_metas($text));
		}

		function strip_metas ($text = "")
		{
			$metas = array('$','^','*','(',')','+','[',']','.','?');
			return str_replace($metas, '', stripslashes($text));
		}

		function custom_strip ($Chars, $text = "")
		{
			$this->nonfree_call();
		}

		function array_echo ($array, $name='Array')
		{
			echo '<pre>';
			print_r($array);
			echo '<pre>';
		}

		function is_email ($Address='')
		{
			$this->nonfree_call();
		}

		function is_url ($Url='')
		{
			$this->nonfree_call();
		}

		function url_responds ($Url='')
		{
			$this->nonfree_call();
		}

		function is_phone ($Phone='')
		{
			$this->nonfree_call();
		}

		function is_hostname ($hostname='')
		{
			$this->nonfree_call();
		}

		function is_bigfour ($tld)
		{
			$this->nonfree_call();
		}

		function is_host ($hostname='', $type='ANY')
		{
			$this->nonfree_call();
		}

		function is_ipaddress ($IP='')
		{
			$this->nonfree_call();
		}

		function ip_resolves ($IP='')
		{
			$this->nonfree_call();
		}

		function browser_gen ()
		{
			$this->nonfree_call();
		}

		function is_state ($State = "")
		{
			$this->nonfree_call();
		}

		function is_zip ($zipcode = "")
		{
			$this->nonfree_call();
		}

		function is_country ($countrycode='')
		{
			$this->nonfree_call();
		}
		
		function nonfree_call()
		{
			echo 'class.validator.inc.php used to contain code that was not Free ';
			echo 'Software (<a href="(http://www.gnu.org/philosophy/free-sw.html">see ';
			echo 'definition</a> , therefore it has been removed. <br><br>';
			echo 'If you are a application maintainer, please update your app. ';
			echo 'If you are a user, please file a bug report on ';
			echo '<a href="https://savannah.gnu.org/bugs/?group=phpgroupware">';
			echo 'our project page at savannah.gnu.org</a>. Please copy and paste ';
			echo 'the following information into the bug report:<br>';
			echo '<b>Summary<b>: ' . $GLOBALS['phpgw_info']['flags']['currentapp'];
			echo 'calls class.validator.inc.php';
			echo 'Information:<br> The call was found when calling: ' . $_SERVER['QUERY_STRING'];
			echo '<br><br>This application will now halt!<br><br>';
			echo '<a href="'. $GLOBALS['phpgw']->link('/home.php') .'">Return to Home Screen</a>';
			exit;
		}
	}
?>
