<?php
  /**************************************************************************\
  * phpGroupWare API - Session management                                    *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
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

	/*
	Validator 1.2  1999/03/05 CDI

	A class for validating common data from forms
	Copyright (c) 1999 CDI, cdi@thewebmasters.net All Rights Reserved
	*/

	class validator
	{
		var $ERROR = '';
		var $CLEAR = false;

		function validator ()
		{
			return;
		}

		function clear_error ()
		{
			$this->ERROR = '';
		}

		/* Checks a string for whitespace. True or false */
		function has_space ($text)
		{
			if( ereg("[ 	]",$text) )
			{
				return true;
			}

			return false;
		}

		function chconvert ($fragment)
		{
			switch ($fragment)
			{
				case 7:
					$result = 'rwx';
					break;
				case 6:
					$result = 'rw-';
					break;
				case 5:
					$result = 'r-x';
					break;
				case 4:
					$result = 'r--';
					break;
				case 3:
					$result = '-wx';
					break;
				case 2:
					$result = '-w-';
					break;
				case 1:
					$result = '--x';
					break;
				case 0:
					$result = '---';
					break;
				default:
					$result = 'unk';
					break;
			}

			return($result);
		}

		function get_perms ($fileName )
		{
			if($this->CLEAR) { $this->clear_error(); }

			$atrib = array();

			$perms = fileperms($fileName);

			if(!$perms)
			{
				$this->ERROR = "get_perms: Unable to obtain file perms on [$fileName]";
				return false;
			}

			$octal = sprintf('%lo', ($perms & 07777) );

			$one = substr($octal,0,1);
			$two = substr($octal,1,1);
			$three = substr($octal,2,1);

			$user  = $this->chconvert($one);
			$group = $this->chconvert($two);
			$other = $this->chconvert($three);

			if(is_dir($fileName))
			{
				$user = "d$user";
			}

			$atrib = array(
				'octal'	=>	$octal,
				'user'	=>	$user,
				'group'	=>	$group,
				'other'	=>	$other
			);

			return $atrib;
		}

		function is_sane ($filename)
		{
			if($this->CLEAR) { $this->clear_error(); }

			if (!file_exists($filename))
			{
				$this->ERROR = 'File does not exist';
				return false;
			}
			if (!is_readable($filename))
			{
				$this->ERROR = 'File is not readable';
				return false;
			}
			if(!is_writeable($filename))
			{
				$this->ERROR = 'File is not writeable';
				return false;
			}
			if(is_dir($filename))
			{
				$this->ERROR = 'File is a directory';
				return false;
			}
			if(is_link($filename))
			{
				$this->ERROR = 'File is a symlink';
				return false;
			}

			return true;
		}

		//	************************************************************
		//	Strips whitespace (tab or space) from a string
		function strip_space ($text)
		{
			$Return = ereg_replace("([ 	]+)",'',$text);
			return ($Return);
		}

		//	************************************************************
		//	Returns true if string contains only numbers
		function is_allnumbers ($text)
		{
			if(is_int($text))
			{
				return true;
			}

			$Bad = $this->strip_numbers($text);

			if(empty($Bad))
			{
				return true;
			}
			return false;
		}

		//	************************************************************
		//	Strip numbers from a string
		function strip_numbers ($text)
		{
			$Stripped = eregi_replace("([0-9]+)",'',$text);
			return ($Stripped);
		}

		//	************************************************************
		//	Returns true if string contains only letters
		function is_allletters ($text)
		{
			$Bad = $this->strip_letters($text);
			if(empty($Bad))
			{
				return true;
			}

			return false;
		}

		//	************************************************************
		//	Strips letters from a string
		function strip_letters ($text)
		{
			$Stripped = eregi_replace("([A-Z]+)",'',$text);
			return $Stripped;
		}

		//	************************************************************
		//	Checks for HTML entities in submitted text.
		//	If found returns true, otherwise false. HTML specials are:
		//
		//		"	=>	&quot;
		//		<	=>	&lt;
		//		>	=>	&gt;
		//		&	=>	&amp;
		//
		//	The presence of ",<,>,&  will force this method to return true.
		//
		function has_html ($text='')
		{
			if(empty($text))
			{
				return false;
			}
			$New = htmlspecialchars($text);
			if($New == $text)
			{
				return false;
			}
			return true;
		}

		//	************************************************************
		//	strip_html()
		//
		//	Strips all html entities, attributes, elements and tags from
		//	the submitted string data and returns the results.
		//
		//	Can't use a regex here because there's no way to know
		//	how the data is laid out. We have to examine every character
		//	that's been submitted. Consequently, this is not a very
		//	efficient method. It works, it's very good at removing
		//	all html from the data, but don't send gobs of data
		//	at it or your program will slow to a crawl.

		//	If you're stripping HTML from a file, use PHP's fgetss()
		//	and NOT this method, as fgetss() does the same thing
		//	about 100x faster.
		function strip_html ($text='')
		{
			if( (!$text) or (empty($text)) )
			{
				return '';
			}
			$outside = true;
			$rawText = '';
			$length = strlen($text);
			$count = 0;

			for($count=0; $count < $length; $count++)
			{
				$digit = substr($text,$count,1);
				if(!empty($digit))
				{
					if( ($outside) && ($digit != '<') && ($digit != '>') )
					{
						$rawText .= $digit;
					}
					if($digit == '<')
					{
						$outside = false;
					}
					if($digit == '>')
					{
						$outside = true;
					}
				}
			}
			return $rawText;
		}

		//	************************************************************
		//	Returns true of the submitted text has meta characters in it
		//	. \\ + * ? [ ^ ] ( $ )
		//
		// 
		function has_metas ($text='')
		{
			if(empty($text))
			{
				return false;
			}

			$New = quotemeta($text);

			if($New == $text)
			{
				return false;
			}

			return true;
		}

		//	************************************************************
		//	Strips "  . \\ + * ? [ ^ ] ( $ )  " from submitted string
		//
		//	Metas are a virtual MINE FIELD for regular expressions,
		//  see custom_strip() for how they are removed
		function strip_metas ($text = "")
		{
			if(empty($text))
			{
				return false;
			}

			$Metas = array( '.','+','*','?','[','^',']','(','$',')' );
			$text = stripslashes($text);
			$New = $this->custom_strip($Metas,$text);
			return $New;
		}

		//	************************************************************
		//	$Chars must be an array of characters to remove.
		//	This method is meta-character safe.
		function custom_strip ($Chars, $text = "")
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(empty($text))
			{
				return false;
			}

			if(!is_array($Chars))
			{
				$this->ERROR = "custom_strip: [$Chars] is not an array";
				return false;
			}

			while ( list ( $key,$val) = each ($Chars) )
			{
				if(!empty($val))
				{
					// str_replace is meta-safe, ereg_replace is not
					$text = str_replace($val,"",$text);
				}
			}

			return $text;
		}

		//	************************************************************
		//	Array_Echo will walk through an array,
		//	continuously printing out key value pairs.
		//
		//	Multi dimensional arrays are handled recursively.
		function array_echo ($MyArray, $Name='Array')
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(!is_array($MyArray))
			{
				return;
			}

			$count = 0;

			while ( list ($key,$val) = each ($MyArray) )
			{
				if($count == 0)
				{
					echo "\n\n<P><TABLE BORDER=1 CELLPADDING=0 CELLSPACING=0 COLS=8\n";
					echo "><TR><TD VALIGN=TOP COLSPAN=4><B>$Name Contents:</B></TD\n";
					echo "><TD COLSPAN=2><B>KEY</B></TD><TD COLSPAN=2><B>VAL</B></TD></TR\n>";
				}
				if(is_array($val))
				{
					$NewName = "$key [$Name $count]";
					$NewArray = $MyArray[$key];
					echo "</TD></TR></TABLE\n\n>";
					$this->array_echo($NewArray,$NewName);
					echo "\n\n<P><TABLE BORDER=1 CELLPADDING=0 CELLSPACING=0 COLS=8\n";
					echo "><TR><TD VALIGN=TOP COLSPAN=4><B>$Name Continued:</B></TD\n";
					echo "><TD COLSPAN=2><B>KEY</B></TD><TD COLSPAN=2><B>VAL</B></TD></TR\n>";
				}
				else
				{
					echo "<TR>";
					$Col1 = sprintf("[%s][%0d]",$Name,$count);
					$Col2 = $key;
					if(empty($val))	{ $val = '&nbsp;'; }
					$Col3 = $val;
					echo "<TD COLSPAN=4>$Col1</TD>";
					echo "<TD COLSPAN=2>$Col2</TD\n>";
					echo "<TD COLSPAN=2>$Col3</TD></TR\n\n>";
				}
				$count++;
			}
			echo "<TR><TD COLSPAN=8><B>Array [$Name] complete.</B></TD></TR\n>";
			echo "</TD></TR></TABLE\n\n>";
			return;
		}

		//	************************************************************
		//	Valid email format? true or false
		//	This checks the raw address, not RFC 822 addresses.

		//	Looks for [something]@[valid hostname with DNS record]
		function is_email ($Address='')
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(empty($Address))
			{
				$this->ERROR = 'is_email: No email address submitted';
				return false;
			}

			if(!ereg('@',$Address))
			{
				$this->ERROR = 'is_email: Invalid, no @ symbol in string';
				return false;
			}

			list($User,$Host) = split('@',$Address);

			if ( (empty($User)) || (empty($Address)) )
			{
				$this->ERROR = "is_email: missing data [$User]@[$Host]";
				return false;
			}
			if( ($this->has_space($User)) || ($this->has_space($Host)) )
			{
				$this->ERROR = "is_email: Whitespace in [$User]@[$Host]";
				return false;
			}

			// Can't look for an MX only record as that precludes
			// CNAME only records. Thanks to everyone that slapped
			// me upside the head for this glaring oversite. :)

			if(!$this->is_host($Host,'ANY'))
			{
				return false;
			}

			return true;
		}

		//	************************************************************
		//	Valid URL format? true or false

		//	Checks format of a URL - does NOT handle query strings or
		//	urlencoded data.
		function is_url ($Url='')
		{
			if($this->CLEAR) { $this->clear_error(); }

			if (empty($Url))
			{
				$this->ERROR = 'is_url: No URL submitted';
				return false;
			}

			// Wow, the magic of parse_url!

			$UrlElements = parse_url($Url);
			if( (empty($UrlElements)) or (!$UrlElements) )
			{
				$this->ERROR = "is_url: Parse error reading [$Url]";
				return false;
			}

			$scheme		= $UrlElements['scheme'];
			$HostName	= $UrlElements['host'];

			if(empty($scheme))
			{
				$this->ERROR = 'is_url: Missing protocol declaration';
				return false;
			}

			if(empty($HostName))
			{
				$this->ERROR = 'is_url: No hostname in URL';
				return false;
			}

			if (!eregi("^(ht|f)tp",$scheme))
			{
				$this->ERROR = 'is_url: No http:// or ftp://';
				return false;
			}

			## padraic renaghan change for bookmarker ver 1.4 bug 69
			## if hostname is an ip address, check the validity of
			## the ip address, otherwise check as if host name
			## is specified.
			if (ereg("[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+",$HostName)) {
				if(!$this->is_ipaddress($HostName))	{ return false; }
			} else {
				if(!$this->is_hostname($HostName))  { return false; }
			}

			return true;
		}

		//	************************************************************
		//	URL responds to requests? true or false
		//	This will obviously fail if you're not connected to
		//	the internet, or if there are connection problems. (firewall etc)
		function url_responds ($Url='')
		{
			global $php_errormsg;

			if($this->CLEAR) { $this->clear_error(); }

			if(empty($Url))
			{
				$this->ERROR = 'url_responds: No URL submitted';
				return false;
			}

			if(!$this->is_url($Url))
			{
				return false;
			}

			$fd = @fopen($Url,'r');
			if(!$fd)
			{
				$this->ERROR = "url_responds: Failed : $php_errormsg";
				return false;
			}
			else
			{
				@fclose($fd);
				return true;
			}
		}

		//	************************************************************
		//	Valid phone number? true or false
		//	Tries to validate a phone number

		//	Strips (,),-,+ from number prior to checking
		//	Less than 7 digits = fail
		//	More than 13 digits = fail
		//	Anything other than numbers after the stripping = fail
		function is_phone ($Phone='')
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(empty($Phone))
			{
				$this->ERROR = 'is_phone: No Phone number submitted';
				return false;
			}

			$Num = $Phone;
			$Num = $this->strip_space($Num);
			$Num = eregi_replace("(\(|\)|\-|\+)",'',$Num);
			if(!$this->is_allnumbers($Num))
			{
				$this->ERROR = 'is_phone: bad data in phone number';
				return false;
			}

			if ( (strlen($Num)) < 7)
			{
				$this->ERROR = "is_phone: number is too short [$Num][$Phone]";
				return false;
			}

			// 000 000 000 0000
			// CC  AC  PRE SUFX = max 13 digits

			if( (strlen($Num)) > 13)
			{
				$this->ERROR = "is_phone: number is too long [$Num][$Phone]";
				return false;
			}

			return true;
		}

		//	************************************************************
		//	Valid, fully qualified hostname? true or false
		//	Checks the -syntax- of the hostname, not it's actual
		//	validity as a reachable internet host
		function is_hostname ($hostname='')
		{
			if($this->CLEAR) { $this->clear_error(); }

			$web = false;

			if(empty($hostname))
			{
				$this->ERROR = 'is_hostname: No hostname submitted';
				return false;
			}

			// Only a-z, 0-9, and "-" or "." are permitted in a hostname

			// Patch for POSIX regex lib by Sascha Schumann sas@schell.de
			$Bad = eregi_replace("[-A-Z0-9\.]","",$hostname);

			if(!empty($Bad))
			{
				$this->ERROR = "is_hostname: invalid chars [$Bad]";
				return false;
			}

			// See if we're doing www.hostname.tld or hostname.tld
			if(eregi("^www\.",$hostname))
			{
				$web = true;
			}

			// double "." is a not permitted
			if(ereg("\.\.",$hostname))
			{
				$this->ERROR = "is_hostname: Double dot in [$hostname]";
				return false;
			}
			if(ereg("^\.",$hostname))
			{
				$this->ERROR = "is_hostname: leading dot in [$hostname]";
				return false;
			}

			$chunks = explode(".",$hostname);

			if(!is_array($chunks))
			{
				$this->ERROR = "is_hostname: Invalid hostname, no dot seperator [$hostname]";
				return false;
			}

			$count = ( (count($chunks)) - 1);

			if($count < 1)
			{
				$this->ERROR = "is_hostname: Invalid hostname [$count] [$hostname]\n";
				return false;
			}

			// Bug that can't be killed without doing an is_host,
			// something.something will return TRUE, even if it's something
			// stupid like NS.SOMETHING (with no tld), because SOMETHING is
			// construed to BE the tld.  The is_bigfour and is_country
			// checks should help eliminate this inconsistancy. To really
			// be sure you've got a valid hostname, do an is_host() on it.

			if( ($web) and ($count < 2) )
			{
				$this->ERROR = "is_hostname: Invalid hostname [$count] [$hostname]\n";
				return false;
			}

			$tld = $chunks[$count];

			if(empty($tld))
			{
				$this->ERROR = "is_hostname: No TLD found in [$hostname]";
				return false;
			}

			if(!$this->is_bigfour($tld))
			{
				if(!$this->is_country($tld))
				{
					$this->ERROR = "is_hostname: Unrecognized TLD [$tld]";
					return false;
				}
			}

			return true;
		}

		function is_bigfour ($tld)
		{
			if(empty($tld))
			{
				return false;
			}
			if(eregi("^\.",$tld))
			{
				$tld = eregi_replace("^\.","",$tld);
			}
			$BigFour = array(
				'com' => 'com',
				'edu' => 'edu',
				'net' => 'net',
				'org' => 'org',
				'gov' => 'gov',
				'mil' => 'mil',
				'int' => 'int'
			);
			$tld = strtolower($tld);

			if(isset($BigFour[$tld]))
			{
				return true;
			}

			return false;
		}

		//	************************************************************
		//	Hostname is a reachable internet host? true or false
		function is_host ($hostname='', $type='ANY')
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(empty($hostname))
			{
				$this->ERROR = 'is_host: No hostname submitted';
				return false;
			}

			if(!$this->is_hostname($hostname))
			{
				return false;
			}

			if(!checkdnsrr($hostname,$type))
			{
				$this->ERROR = "is_host: no DNS records for [$hostname].";
				return false;
			}

			return true;
		}

		//	************************************************************
		//	Dotted quad IPAddress within valid range? true or false
		//	Checks format, leading zeros, and values > 255
		//	Does not check for reserved or unroutable IPs.
		function is_ipaddress ($IP='')
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(empty($IP))
			{
				$this->ERROR = 'is_ipaddress: No IP address submitted';
				return false;
			}
			//	123456789012345
			//	xxx.xxx.xxx.xxx

			$len = strlen($IP);
			if( $len > 15 )
			{
				$this->ERROR = "is_ipaddress: too long [$IP][$len]";
				return false;
			}

			$Bad = eregi_replace("([0-9\.]+)",'',$IP);

			if(!empty($Bad))
			{
				$this->ERROR = "is_ipaddress: Bad data in IP address [$Bad]";
				return false;
			}
			$chunks = explode('.',$IP);
			$count = count($chunks);

			if ($count != 4)
			{
				$this->ERROR = "is_ipaddress: not a dotted quad [$IP]";
				return false;
			}

			while ( list ($key,$val) = each ($chunks) )
			{
				if(ereg("^0",$val))
				{
					$this->ERROR = "is_ipaddress: Invalid IP segment [$val]";
					return false;
				}
				$Num = $val;
				settype($Num,'integer');
				if($Num > 255)
				{
					$this->ERROR = "is_ipaddress: Segment out of range [$Num]";
					return false;
				}

			}

			return true;
		}	// end is_ipaddress

		//	************************************************************
		//	IP address is valid, and resolves to a hostname? true or false
		function ip_resolves ($IP='')
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(empty($IP))
			{
				$this->ERROR = 'ip_resolves: No IP address submitted';
				return false;
			}

			if(!$this->is_ipaddress($IP))
			{
				return false;
			}

			$Hostname = gethostbyaddr($IP);

			if($Hostname == $IP)
			{
				$this->ERROR = 'ip_resolves: IP does not resolve.';
				return false;
			}

			if($Hostname)
			{
				if(!checkdnsrr($Hostname))
				{
					$this->ERROR = "is_ipaddress: no DNS records for resolved hostname [$Hostname]";
					return false;
				}
				if( (gethostbyname($Hostname)) != $IP )
				{
					$this->ERROR = 'is_ipaddress: forward:reverse mismatch, possible forgery';
					//	Non-fatal, but it should be noted.
				}
			}
			else
			{
				$this->ERROR = 'ip_resolves: IP address does not resolve';
				return false;
			}

			return true;
		}

		function browser_gen ()
		{
			if($this->CLEAR) { $this->clear_error(); }
			$generation = 'UNKNOWN';

			$client = getenv('HTTP_USER_AGENT');
			if(empty($client))
			{
				$this->ERROR = 'browser_gen: No User Agent for Client';
				return $generation;
			}

			$client = $this->strip_metas($client);

			$agents = array(
				'Anonymizer'		=>	'ANONYMIZER',
				'Ahoy'				=>	'SPIDER',
				'Altavista'			=>	'SPIDER',
				'Anzwers'			=>	'SPIDER',
				'Arachnoidea'		=>	'SPIDER',
				'Arachnophilia'		=>	'SPIDER',
				'ArchitextSpider'	=>	'SPIDER',
				'Backrub'			=>	'SPIDER',
				'CherryPicker'		=>	'SPAMMER',
				'Crescent'			=>	'SPAMMER',
				'Duppies'			=>	'SPIDER',
				'EmailCollector'	=>	'SPAMMER',
				'EmailSiphon'		=>	'SPAMMER',
				'EmailWolf'			=>	'SPAMMER',
				'Extractor'			=>	'SPAMMER',
				'Fido'				=>	'SPIDER',
				'Fish'				=>	'SPIDER',
				'GAIS'				=>	'SPIDER',
				'Googlebot'			=>	'SPIDER',
				'Gulliver'			=>	'SPIDER',
				'HipCrime'			=>	'SPAMMER',
				'Hamahakki'			=>	'SPIDER',
				'ia_archive'		=>	'SPIDER',
				'IBrowse'			=>	'THIRD',
				'Incy'				=>	'SPIDER',
				'InfoSeek'			=>	'SPIDER',
				'KIT-Fireball'		=>	'SPIDER',
				'Konqueror'			=>	'THIRD',
				'libwww'			=>	'SECOND',
				'LocalEyes'			=>	'SECOND',
				'Lycos'				=>	'SPIDER',
				'Lynx'				=>	'SECOND',
				'Microsoft.URL'		=>	'SPAMMER',
				'MOMspider'			=>	'SPIDER',
				'Mozilla/1'			=>	'FIRST',
				'Mozilla/2'			=>	'SECOND',
				'Mozilla/3'			=>	'THIRD',
				'Mozilla/4'			=>	'FOURTH',
				'Mozilla/5'			=>	'FIFTH',
				'Namecrawler'		=>	'SPIDER',
				'NICErsPRO'			=>	'SPAMMER',
				'Scooter'			=>	'SPIDER',
				'sexsearch'			=>	'SPIDER',
				'Sidewinder'		=>	'SPIDER',
				'Slurp'				=>	'SPIDER',
				'SwissSearch'		=>	'SPIDER',
				'Ultraseek'			=>	'SPIDER',
				'WebBandit'			=>	'SPAMMER',
				'WebCrawler'		=>	'SPIDER',
				'WiseWire'			=>	'SPIDER',
				'Mozilla/3.0 (compatible; Opera/3'	=>	'THIRD'
			);

			while ( list ($key,$val) = each ($agents) )
			{
				$key = $this->strip_metas($key);
				if(eregi("^$key",$client))
				{
					unset($agents);
					return $val;
				}
			}

			unset($agents);
			return $generation;
		}

		//	************************************************************
		//	United States valid state code? true or false
		function is_state ($State = "")
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(empty($State))
			{
				$this->ERROR = 'is_state: No state submitted';
				return false;
			}
			if( (strlen($State)) != 2)
			{
				$this->ERROR = 'is_state: Too many digits in state code';
				return false;
			}

			$State = strtoupper($State);

			// 50 states, Washington DC, Puerto Rico and the US Virgin Islands

			$SCodes = array (
				'AK' => 1,
				'AL' => 1,
				'AR' => 1,
				'AZ' => 1,
				'CA' => 1,
				'CO' => 1,
				'CT' => 1,
				'DC' => 1,
				'DE' => 1,
				'FL' => 1,
				'GA' => 1,
				'HI' => 1,
				'IA' => 1,
				'ID' => 1,
				'IL' => 1,
				'IN' => 1,
				'KS' => 1,
				'KY' => 1,
				'LA' => 1,
				'MA' => 1,
				'MD' => 1,
				'ME' => 1,
				'MI' => 1,
				'MN' => 1,
				'MO' => 1,
				'MS' => 1,
				'MT' => 1,
				'NC' => 1,
				'ND' => 1,
				'NE' => 1,
				'NH' => 1,
				'NJ' => 1,
				'NM' => 1,
				'NV' => 1,
				'NY' => 1,
				'OH' => 1,
				'OK' => 1,
				'OR' => 1,
				'PA' => 1,
				'PR' => 1,
				'RI' => 1,
				'SC' => 1,
				'SD' => 1,
				'TN' => 1,
				'TX' => 1,
				'UT' => 1,
				'VA' => 1,
				'VI' => 1,
				'VT' => 1,
				'WA' => 1,
				'WI' => 1,
				'WV' => 1,
				'WY' => 1
			);

			if(!isset($SCodes[$State]))
			{
				$this->ERROR = "is_state: Unrecognized state code [$State]";
				return false;
			}

			// Lets not have this big monster camping in memory eh?
			unset($SCodes);

			return true;
		}

		//	************************************************************
		//	Valid postal zip code? true or false
		function is_zip ($zipcode = "")
		{
			if($this->CLEAR) { $this->clear_error(); }

			if(empty($zipcode))
			{
				$this->ERROR = 'is_zip: No zipcode submitted';
				return false;
			}

			$Bad = eregi_replace("([-0-9]+)",'',$zipcode);

			if(!empty($Bad))
			{
				$this->ERROR = "is_zip: Bad data in zipcode [$Bad]";
				return false;
			}
			$Num = eregi_replace("\-",'',$zipcode);
			$len = strlen($Num);
			if ( ($len > 10) or ($len < 5) )
			{
				$this->ERROR = "is_zipcode: Invalid length [$len] for zipcode";
				return false;
			}

			return true;
		}

		//	************************************************************
		//	Valid postal country code?
		//	Returns the name of the country, or null on failure
		//	Current array recognizes ~232 country codes. 

		//	I don't know if all of these are 100% accurate.
		//	You don't wanna know how difficult it was just getting
		//	this listing in here. :)
		function is_country ($countrycode='')
		{
			if($this->CLEAR) { $this->clear_error(); }

			$Return = '';

			if(empty($countrycode))
			{
				$this->ERROR = 'is_country: No country code submitted';
				return $Return;
			}

			$countrycode = strtolower($countrycode);

			if( (strlen($countrycode)) != 2 )
			{
				$this->ERROR = "is_country: 2 digit codes only [$countrycode]";
				return $Return;
			}

			//	Now for a really big array

			//	Dominican Republic, cc = "do" because it's a reserved
			//	word in PHP. That parse error took 10 minutes of
			//	head-scratching to figure out :)

			//	A (roughly) 3.1 Kbyte array

			$CCodes = array (
				'do' => 'Dominican Republic',
				'ad' => 'Andorra',
				'ae' => 'United Arab Emirates',
				'af' => 'Afghanistan',
				'ag' => 'Antigua and Barbuda',
				'ai' => 'Anguilla',
				'al' => 'Albania',
				'am' => 'Armenia',
				'an' => 'Netherlands Antilles',
				'ao' => 'Angola',
				'aq' => 'Antarctica',
				'ar' => 'Argentina',
				'as' => 'American Samoa',
				'at' => 'Austria',
				'au' => 'Australia',
				'aw' => 'Aruba',
				'az' => 'Azerbaijan',
				'ba' => 'Bosnia Hercegovina',
				'bb' => 'Barbados',
				'bd' => 'Bangladesh',
				'be' => 'Belgium',
				'bf' => 'Burkina Faso',
				'bg' => 'Bulgaria',
				'bh' => 'Bahrain',
				'bi' => 'Burundi',
				'bj' => 'Benin',
				'bm' => 'Bermuda',
				'bn' => 'Brunei Darussalam',
				'bo' => 'Bolivia',
				'br' => 'Brazil',
				'bs' => 'Bahamas',
				'bt' => 'Bhutan',
				'bv' => 'Bouvet Island',
				'bw' => 'Botswana',
				'by' => 'Belarus (Byelorussia)',
				'bz' => 'Belize',
				'ca' => 'Canada',
				'cc' => 'Cocos Islands',
				'cd' => 'Congo, The Democratic Republic of the',
				'cf' => 'Central African Republic',
				'cg' => 'Congo',
				'ch' => 'Switzerland',
				'ci' => 'Ivory Coast',
				'ck' => 'Cook Islands',
				'cl' => 'Chile',
				'cm' => 'Cameroon',
				'cn' => 'China',
				'co' => 'Colombia',
				'cr' => 'Costa Rica',
				'cs' => 'Czechoslovakia',
				'cu' => 'Cuba',
				'cv' => 'Cape Verde',
				'cx' => 'Christmas Island',
				'cy' => 'Cyprus',
				'cz' => 'Czech Republic',
				'de' => 'Germany',
				'dj' => 'Djibouti',
				'dk' => 'Denmark',
				'dm' => 'Dominica',
				'dz' => 'Algeria',
				'ec' => 'Ecuador',
				'ee' => 'Estonia',
				'eg' => 'Egypt',
				'eh' => 'Western Sahara',
				'er' => 'Eritrea',
				'es' => 'Spain',
				'et' => 'Ethiopia',
				'fi' => 'Finland',
				'fj' => 'Fiji',
				'fk' => 'Falkland Islands',
				'fm' => 'Micronesia',
				'fo' => 'Faroe Islands',
				'fr' => 'France',
				'fx' => 'France, Metropolitan FX',
				'ga' => 'Gabon',
				'gb' => 'United Kingdom (Great Britain)',
				'gd' => 'Grenada',
				'ge' => 'Georgia',
				'gf' => 'French Guiana',
				'gh' => 'Ghana',
				'gi' => 'Gibraltar',
				'gl' => 'Greenland',
				'gm' => 'Gambia',
				'gn' => 'Guinea',
				'gp' => 'Guadeloupe',
				'gq' => 'Equatorial Guinea',
				'gr' => 'Greece',
				'gs' => 'South Georgia and the South Sandwich Islands',
				'gt' => 'Guatemala',
				'gu' => 'Guam',
				'gw' => 'Guinea-bissau',
				'gy' => 'Guyana',
				'hk' => 'Hong Kong',
				'hm' => 'Heard and McDonald Islands',
				'hn' => 'Honduras',
				'hr' => 'Croatia',
				'ht' => 'Haiti',
				'hu' => 'Hungary',
				'id' => 'Indonesia',
				'ie' => 'Ireland',
				'il' => 'Israel',
				'in' => 'India',
				'io' => 'British Indian Ocean Territory',
				'iq' => 'Iraq',
				'ir' => 'Iran',
				'is' => 'Iceland',
				'it' => 'Italy',
				'jm' => 'Jamaica',
				'jo' => 'Jordan',
				'jp' => 'Japan',
				'ke' => 'Kenya',
				'kg' => 'Kyrgyzstan',
				'kh' => 'Cambodia',
				'ki' => 'Kiribati',
				'km' => 'Comoros',
				'kn' => 'Saint Kitts and Nevis',
				'kp' => 'North Korea',
				'kr' => 'South Korea',
				'kw' => 'Kuwait',
				'ky' => 'Cayman Islands',
				'kz' => 'Kazakhstan',
				'la' => 'Laos',
				'lb' => 'Lebanon',
				'lc' => 'Saint Lucia',
				'li' => 'Lichtenstein',
				'lk' => 'Sri Lanka',
				'lr' => 'Liberia',
				'ls' => 'Lesotho',
				'lt' => 'Lithuania',
				'lu' => 'Luxembourg',
				'lv' => 'Latvia',
				'ly' => 'Libya',
				'ma' => 'Morocco',
				'mc' => 'Monaco',
				'md' => 'Moldova Republic',
				'mg' => 'Madagascar',
				'mh' => 'Marshall Islands',
				'mk' => 'Macedonia, The Former Yugoslav Republic of',
				'ml' => 'Mali',
				'mm' => 'Myanmar',
				'mn' => 'Mongolia',
				'mo' => 'Macau',
				'mp' => 'Northern Mariana Islands',
				'mq' => 'Martinique',
				'mr' => 'Mauritania',
				'ms' => 'Montserrat',
				'mt' => 'Malta',
				'mu' => 'Mauritius',
				'mv' => 'Maldives',
				'mw' => 'Malawi',
				'mx' => 'Mexico',
				'my' => 'Malaysia',
				'mz' => 'Mozambique',
				'na' => 'Namibia',
				'nc' => 'New Caledonia',
				'ne' => 'Niger',
				'nf' => 'Norfolk Island',
				'ng' => 'Nigeria',
				'ni' => 'Nicaragua',
				'nl' => 'Netherlands',
				'no' => 'Norway',
				'np' => 'Nepal',
				'nr' => 'Nauru',
				'nt' => 'Neutral Zone',
				'nu' => 'Niue',
				'nz' => 'New Zealand',
				'om' => 'Oman',
				'pa' => 'Panama',
				'pe' => 'Peru',
				'pf' => 'French Polynesia',
				'pg' => 'Papua New Guinea',
				'ph' => 'Philippines',
				'pk' => 'Pakistan',
				'pl' => 'Poland',
				'pm' => 'St. Pierre and Miquelon',
				'pn' => 'Pitcairn',
				'pr' => 'Puerto Rico',
				'pt' => 'Portugal',
				'pw' => 'Palau',
				'py' => 'Paraguay',
				'qa' => 'Qatar',
				're' => 'Reunion',
				'ro' => 'Romania',
				'ru' => 'Russia',
				'rw' => 'Rwanda',
				'sa' => 'Saudi Arabia',
				'sb' => 'Solomon Islands',
				'sc' => 'Seychelles',
				'sd' => 'Sudan',
				'se' => 'Sweden',
				'sg' => 'Singapore',
				'sh' => 'St. Helena',
				'si' => 'Slovenia',
				'sj' => 'Svalbard and Jan Mayen Islands',
				'sk' => 'Slovakia (Slovak Republic)',
				'sl' => 'Sierra Leone',
				'sm' => 'San Marino',
				'sn' => 'Senegal',
				'so' => 'Somalia',
				'sr' => 'Suriname',
				'st' => 'Sao Tome and Principe',
				'sv' => 'El Salvador',
				'sy' => 'Syria',
				'sz' => 'Swaziland',
				'tc' => 'Turks and Caicos Islands',
				'td' => 'Chad',
				'tf' => 'French Southern Territories',
				'tg' => 'Togo',
				'th' => 'Thailand',
				'tj' => 'Tajikistan',
				'tk' => 'Tokelau',
				'tm' => 'Turkmenistan',
				'tn' => 'Tunisia',
				'to' => 'Tonga',
				'tp' => 'East Timor',
				'tr' => 'Turkey',
				'tt' => 'Trinidad, Tobago',
				'tv' => 'Tuvalu',
				'tw' => 'Taiwan',
				'tz' => 'Tanzania',
				'ua' => 'Ukraine',
				'ug' => 'Uganda',
				'uk' => 'United Kingdom',
				'um' => 'United States Minor Islands',
				'us' => 'United States of America',
				'uy' => 'Uruguay',
				'uz' => 'Uzbekistan',
				'va' => 'Vatican City',
				'vc' => 'Saint Vincent, Grenadines',
				've' => 'Venezuela',
				'vg' => 'Virgin Islands (British)',
				'vi' => 'Virgin Islands (USA)',
				'vn' => 'Viet Nam',
				'vu' => 'Vanuatu',
				'wf' => 'Wallis and Futuna Islands',
				'ws' => 'Samoa',
				'ye' => 'Yemen',
				'yt' => 'Mayotte',
				'yu' => 'Yugoslavia',
				'za' => 'South Africa',
				'zm' => 'Zambia',
				'zr' => 'Zaire',
				'zw' => 'Zimbabwe'
			);

			if(isset($CCodes[$countrycode]))
			{
				$Return = $CCodes[$countrycode];
			}
			else
			{
				$this->ERROR = "is_country: Unrecognized country code [$countrycode]";
				$Return = "";
			}

			// make sure this monster is removed from memory

			unset($CCodes);

			return ($Return);
		} // end is_country
	} // End class
?>
