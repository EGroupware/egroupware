<?php
  /**************************************************************************\
  * phpGroupWare API - Validator                                             *
  * This file written by Dave Hall <skwashd@phpgroupware.org>                *
  * Copyright (C) 2003 Free Software Foundation                              *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  *  This program is Free Software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

 /* $Id$ */

  //NOTE This class still needs to be documented and the stubbed methods fixed!

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

		function is_email ($address='')
		{
			list($user, $domain) = explode('@', $address);
			
			if(!($user && $domain))
			{
				return false;
			}
			
			if(!$this->has_space($user) && $this->is_host($domain))
			{
				return true;
			}
		}

		function is_url ($url='')
		{
			//echo "Checking $url<br>";
			$uris = array(
				'ftp'	=> True,
				'https'	=> True,
				'http'	=> True, 
				);
			$url_elements = parse_url($url);
			
			//echo '<pre>';
			//print_r($url_elements);
			//echo '</pre>';

			if(!is_array($url_elements))
			{
				return false;
			}

			//echo 'Scheme ' . $url_elements['scheme'];
			if(@$uris[$url_elements['scheme']])
			{
				//echo ' is valid<br>host ' . $url_elements['host'];
				if( eregi("[a-z]", $url_elements['host']) )
				{
					//echo ' is name<br>';
					return $this->is_hostname($url_elements['host']);
				}
				else
				{
					//echo ' is ip<br>';
					return $this->is_ipaddress($url_elements['host']);
				}
			}
			else
			{
				//echo ' is invalid<br>';
				return $false;
			}
			
		}

		//the url may be valid, but this method can't test all types
		function url_responds ($url='')
		{
			if(!$this->is_url($url))
			{
				return false;
			}

			$fp=@fopen($url);
			if($fp)
			{
				fclose($fp);
				return true;
			}
			else
			{
				return false;
			}
		}

		function is_phone ($Phone='')
		{
			$this->nonfree_call();
		}

		function is_hostname ($hostname='')
		{
			//echo "Checking $hostname<br>";
			$segs = explode('.', $hostname);
			if(is_array($segs))
			{
				foreach($segs as $seg)
				{
					//echo "Checking $seg<br>";
					if(eregi("[a-z0-9\-]{0,62}",$seg))
					{
						$return = True;	
					}

					if(!$return)
					{
						return False;
					}
				}
				return True;
			}
			return False;
		}

		function is_bigfour ($tld)
		{
			$this->nonfree_call();
		}

		function is_host ($hostname='', $type='ANY')
		{
			if($this->is_hostname($hostname))
			{
				return checkdnsrr($hostname, $type);
			}
			else
			{
				return false;
			}
			
		}

		function is_ipaddress ($ip='')
		{
			if(strlen($ip) <= 15)
			{
				$segs = explode('.', $ip);
				if(count($segs) != 4)
				{
					return false;
				}
				foreach($segs as $seg)
				{
					if( ($seg < 0) || ($seg >= 255) )
					{
						return false;
					}
				}
				return true;
			}
			else
			{
				return false;
			}
		}

		function ip_resolves ($ip='')
		{
			if($this->is_ipaddress($ip))
			{
				return !strcmp($hostname, gethostbyaddr($ip));
			}
			else
			{
				return false;
			}
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
