<?php
	/*
	 *	This is a fork of a slick piece of procedural code called 'kses' written by Ulf Harnhammar
	 * The entire set of functions was wrapped in a PHP object with some internal modifications
	 * by Richard Vasquez (http://www.chaos.org/) 7/25/2003
	 *
	 *	The original (procedural) version of the code can be found at:
	 * http://sourceforge.net/projects/kses/
	 *
	 *	[kses strips evil scripts!]
	 *
	 * ==========================================================================================
	 *
	 * class.kses.php 0.0.2 - PHP class that filters HTML/XHTML only allowing some elements and
	 *	                       attributes to be passed through.
	 *
	 * Copyright (C) 2003 Richard R. Vasquez, Jr.
	 *
	 * Derived from kses 0.2.1 - HTML/XHTML filter that only allows some elements and attributes
	 * Copyright (C) 2002, 2003  Ulf Harnhammar
	 *
	 * ==========================================================================================
	 *
	 * This program is free software and open source software; you can redistribute
	 * it and/or modify it under the terms of the GNU General Public License as
	 * published by the Free Software Foundation; either version 2 of the License,
	 * or (at your option) any later version.
	 *
	 * This program is distributed in the hope that it will be useful, but WITHOUT
	 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
	 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
	 * more details.
	 *
	 * You should have received a copy of the GNU General Public License along
	 * with this program; if not, write to the Free Software Foundation, Inc.,
	 * 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA  or visit
	 * http://www.gnu.org/licenses/gpl.html
	 *
	 * ==========================================================================================
	 * CONTACT INFORMATION:
	 *
	 * Email:    View current valid email address at http://www.chaos.org/contact/
	 */

	class kses
	{
		var $allowed_protocols = array('http', 'https', 'ftp', 'news', 'nntp', 'telnet', 'gopher', 'mailto');
		var $allowed_html      = array();

		function kses()
		{
		}

		function Parse($string = "")
		{
			if (get_magic_quotes_gpc())
			{
  				$string = stripslashes($string);
			}
			$string = $this->_no_null($string);
			$string = $this->_js_entities($string);
			$string = $this->_normalize_entities($string);
			$string = $this->_hook($string);
			return    $this->_split($string);
		}

		function Protocols()
		{
			$c_args = func_num_args();
			if($c_args != 1)
			{
				return false;
			}

			$protocol_data = func_get_arg(0);

			if(is_array($protocol_data))
			{
				foreach($protocol_data as $protocol)
				{
					$this->AddProtocol($protocol);
				}
			}
			elseif(is_string($protocol_data))
			{
				$this->AddProtocol($protocol_data);
				return true;
			}
			else
			{
				trigger_error("kses::Protocols() did not receive a string or an array.", E_USER_WARNING);
				return false;
			}
		}

		function AddProtocol($protocol = "")
		{
			if(!is_string($protocol))
			{
				trigger_error("kses::AddProtocol() requires a string.", E_USER_WARNING);
				return false;
			}

			$protocol = strtolower(trim($protocol));
			if($protocol == "")
			{
				trigger_error("kses::AddProtocol() tried to add an empty/NULL protocol.", E_USER_WARNING);
				return false;
			}

			// Remove any inadvertent ':' at the end of the protocol.
			if(substr($protocol, strlen($protocol) - 1, 1) == ":")
			{
				$protocol = substr($protocol, 0, strlen($protocol) - 1);
			}

			if(!in_array($protocol, $this->allowed_protocols))
			{
				array_push($this->allowed_protocols, $protocol);
				sort($this->allowed_protocols);
			}
			return true;
		}

		function AddHTML($tag = "", $attribs = array())
		{
			if(!is_string($tag))
			{
				trigger_error("kses::AddHTML() requires the tag to be a string", E_USER_WARNING);
				return false;
			}

			$tag = strtolower(trim($tag));
			if($tag == "")
			{
				trigger_error("kses::AddHTML() tried to add an empty/NULL tag", E_USER_WARNING);
				return false;
			}

			if(!is_array($attribs))
			{
				trigger_error("kses::AddHTML() requires an array (even an empty one) of attributes for '$tag'", E_USER_WARNING);
				return false;
			}

			$new_attribs = array();
			foreach($attribs as $idx1 => $val1)
			{
				$new_idx1 = strtolower($idx1);
				$new_val1 = $attribs[$idx1];

				if(is_array($new_val1))
				{
					$tmp_val = array();
					foreach($new_val1 as $idx2 => $val2)
					{
						$new_idx2 = strtolower($idx2);
						$tmp_val[$new_idx2] = $val2;
					}
					$new_val1 = $tmp_val;
				}

				$new_attribs[$new_idx1] = $new_val1;
			}

			$this->allowed_html[$tag] = $new_attribs;
			return true;
		}

		###############################################################################
		# This function removes any NULL or chr(173) characters in $string.
		###############################################################################
		function _no_null($string)
		{
			$string = preg_replace('/\0+/', '', $string);
			$string = preg_replace('/(\\\\0)+/', '', $string);
			# commented out, because it breaks chinese chars
			#$string = preg_replace('/\xad+/', '', $string); # deals with Opera "feature"
			return $string;
		} # function _no_null

		###############################################################################
		# This function removes the HTML JavaScript entities found in early versions of
		# Netscape 4.
		###############################################################################
		function _js_entities($string)
		{
		  return preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%', '', $string);
		} # function _js_entities


		###############################################################################
		# This function normalizes HTML entities. It will convert "AT&T" to the correct
		# "AT&amp;T", "&#00058;" to "&#58;", "&#XYZZY;" to "&amp;#XYZZY;" and so on.
		###############################################################################
		function _normalize_entities($string)
		{
			# Disarm all entities by converting & to &amp;
		  $string = str_replace('&', '&amp;', $string);

			# Change back the allowed entities in our entity white list

		  $string = preg_replace('/&amp;([A-Za-z][A-Za-z0-9]{0,19});/', '&\\1;', $string);
		  $string = preg_replace('/&amp;#0*([0-9]{1,5});/e', '\$this->_normalize_entities2("\\1")', $string);
		  $string = preg_replace('/&amp;#([Xx])0*(([0-9A-Fa-f]{2}){1,2});/', '&#\\1\\2;', $string);

		  return $string;
		} # function _normalize_entities


		###############################################################################
		# This function helps _normalize_entities() to only accept 16 bit values
		# and nothing more for &#number; entities.
		###############################################################################
		function _normalize_entities2($i)
		{
		  return (($i > 65535) ? "&amp;#$i;" : "&#$i;");
		} # function _normalize_entities2

		###############################################################################
		# You add any kses hooks here.
		###############################################################################
		function _hook($string)
		{
		  return $string;
		} # function _hook

		###############################################################################
		# This function goes through an array, and changes the keys to all lower case.
		###############################################################################
		function _array_lc($inarray)
		{
		  $outarray = array();

		  foreach ($inarray as $inkey => $inval)
		  {
			 $outkey = strtolower($inkey);
			 $outarray[$outkey] = array();

			 foreach ($inval as $inkey2 => $inval2)
			 {
				$outkey2 = strtolower($inkey2);
				$outarray[$outkey][$outkey2] = $inval2;
			 } # foreach $inval
		  } # foreach $inarray

		  return $outarray;
		} # function _array_lc

		###############################################################################
		# This function searches for HTML tags, no matter how malformed. It also
		# matches stray ">" characters.
		###############################################################################
		function _split($string)
		{
			return preg_replace(
				'%(<'.   # EITHER: <
				'[^>]*'. # things that aren't >
				'(>|$)'. # > or end of string
				'|>)%e', # OR: just a >
				"\$this->_split2('\\1')",
				$string);
		} # function _split

		function _split2($string)
		###############################################################################
		# This function does a lot of work. It rejects some very malformed things
		# like <:::>. It returns an empty string, if the element isn't allowed (look
		# ma, no strip_tags()!). Otherwise it splits the tag into an element and an
		# attribute list.
		###############################################################################
		{
			$string = $this->_stripslashes($string);

			if (substr($string, 0, 1) != '<')
			{
				# It matched a ">" character
				return '&gt;';
			}

			if (!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9]+)([^>]*)>?$%', $string, $matches))
			{
				# It's seriously malformed
				return '';
			}

			$slash    = trim($matches[1]);
			$elem     = $matches[2];
			$attrlist = $matches[3];

			if (!is_array($this->allowed_html[strtolower($elem)]))
			{
				# They are using a not allowed HTML element
				return '';
			}

			return $this->_attr("$slash$elem", $attrlist);
		} # function _split2

		###############################################################################
		# This function removes all attributes, if none are allowed for this element.
		# If some are allowed it calls s_hair() to split them further, and then it
		# builds up new HTML code from the data that _hair() returns. It also
		# removes "<" and ">" characters, if there are any left. One more thing it
		# does is to check if the tag has a closing XHTML slash, and if it does,
		# it puts one in the returned code as well.
		###############################################################################
		function _attr($element, $attr)
		{
			# Is there a closing XHTML slash at the end of the attributes?
			$xhtml_slash = '';
			if (preg_match('%\s/\s*$%', $attr))
			{
				$xhtml_slash = ' /';
			}

			# Are any attributes allowed at all for this element?
			if (count($this->allowed_html[strtolower($element)]) == 0)
			{
				return "<$element$xhtml_slash>";
			}

			# Split it
			//_debug_array($attr);
			$attrarr = $this->_hair($attr);
			
			# Go through $attrarr, and save the allowed attributes for this element
			# in $attr2
			$attr2 = '';
			foreach ($attrarr as $arreach)
			{
				$current = $this->allowed_html[strtolower($element)][strtolower($arreach['name'])];

				if ($current == '')
				{
					# the attribute is not allowed
					continue;
				}

				if (!is_array($current))
				{
					# there are no checks
					$attr2 .= ' '.$arreach['whole'];
				}
				else
				{
					# there are some checks
					$ok = true;
					foreach ($current as $currkey => $currval)
					{
						if (!$this->_check_attr_val($arreach['value'], $arreach['vless'], $currkey, $currval))
						{
							$ok = false;
							break;
						}
					}

					if ($ok)
					{
						# it passed them
						$attr2 .= ' '.$arreach['whole'];
					}
				} # if !is_array($current)
			} # foreach

			# Remove any "<" or ">" characters
			$attr2 = preg_replace('/[<>]/', '', $attr2);
			return "<$element$attr2$xhtml_slash>";
		} # function _attr

		###############################################################################
		# This function does a lot of work. It parses an attribute list into an array
		# with attribute data, and tries to do the right thing even if it gets weird
		# input. It will add quotes around attribute values that don't have any quotes
		# or apostrophes around them, to make it easier to produce HTML code that will
		# conform to W3C's HTML specification. It will also remove bad URL protocols
		# from attribute values.
		###############################################################################
		function _hair($attr)
		{
			//echo __METHOD__.'called<br>';
			$attrarr  = array();
			$mode     = 0;
			$attrname = '';

			# Loop through the whole attribute list

			while (strlen($attr) != 0)
			{
				# Was the last operation successful?
				$working = 0;

				switch ($mode)
				{
					case 0:	# attribute name, href for instance
						if (preg_match('/^([-a-zA-Z]+)/', $attr, $match))
						{
							//echo 'mode 0:'.$match[0].'<br>';
							$attrname = $match[1];
							//echo 'mode 0 -> attrname:'.$attrname.'<br>';
							$working = $mode = 1;
							$attr = preg_replace('/^[-a-zA-Z]+/', '', $attr);
						}
						break;
					case 1:	# equals sign or valueless ("selected")
						if (preg_match('/^\s*=\s*/', $attr)) # equals sign
						{
							$working = 1;
							$mode    = 2;
							$attr    = preg_replace('/^\s*=\s*/', '', $attr);
							//echo 'mode 1:'.$attr.'<br>';
							break;
						}
						if (preg_match('/^\s+/', $attr)) # valueless
						{
							$working   = 1;
							$mode      = 0;
							$attrarr[] = array(
								'name'  => $attrname,
								'value' => '',
								'whole' => $attrname,
								'vless' => 'y'
							);
							$attr      = preg_replace('/^\s+/', '', $attr);
						}
						break;
					case 2: # attribute value, a URL after href= for instance
							//echo 'mode 2 Attrname:'.$attrname.'<br>';
						if (preg_match('/^"([^"]*)"(\s+|$)/', $attr, $match)) # "value"
						{
							$thisval   = ($attrname == 'name' ? $match[1] : $this->_bad_protocol($match[1]));
							$attrarr[] = array(
								'name'  => $attrname,
								'value' => $thisval,
								'whole' => "$attrname=\"$thisval\"",
								'vless' => 'n'
							);
							$working   = 1;
							$mode      = 0;
							$attr      = preg_replace('/^"[^"]*"(\s+|$)/', '', $attr);
							//echo 'mode 2:'.$attr.'<br>';
							break;
						}
						if (preg_match("/^'([^']*)'(\s+|$)/", $attr, $match)) # 'value'
						{
							$thisval   = ($attrname == 'name' ? $match[1] : $this->_bad_protocol($match[1]));
							$attrarr[] = array(
								'name'  => $attrname,
								'value' => $thisval,
								'whole' => "$attrname='$thisval'",
								'vless' => 'n'
							);
							$working   = 1;
							$mode      = 0;
							$attr      = preg_replace("/^'[^']*'(\s+|$)/", '', $attr);
							//echo 'mode 2:'.$attr.'<br>';
							break;
						}
						if (preg_match("%^([^\s\"']+)(\s+|$)%", $attr, $match)) # value
						{
							$thisval   = ($attrname == 'name' ? $match[1] : $this->_bad_protocol($match[1]));
							$attrarr[] = array(
								'name'  => $attrname,
								'value' => $thisval,
								'whole' => "$attrname=\"$thisval\"",
								'vless' => 'n'
							);
							# We add quotes to conform to W3C's HTML spec.
							$working   = 1;
							$mode      = 0;
							$attr      = preg_replace("%^[^\s\"']+(\s+|$)%", '', $attr);
						}
						break;
				} # switch

				if ($working == 0) # not well formed, remove and try again
				{
					$attr = $this->_html_error($attr);
					$mode = 0;
				}
			} # while

			# special case, for when the attribute list ends with a valueless
			# attribute like "selected"
			if ($mode == 1)
			{
				$attrarr[] = array(
					'name'  => $attrname,
					'value' => '',
					'whole' => $attrname,
					'vless' => 'y'
				);
			}

			return $attrarr;
		} # function _hair

		###############################################################################
		# This function removes all non-allowed protocols from the beginning of
		# $string. It ignores whitespace and the case of the letters, and it does
		# understand HTML entities. It does its work in a while loop, so it won't be
		# fooled by a string like "javascript:javascript:alert(57)".
		###############################################################################
		function _bad_protocol($string)
		{
			$string  = $this->_no_null($string);
			$string2 = $string.'a';

			while ($string != $string2)
			{
				$string2 = $string;
				$string  = $this->_bad_protocol_once($string);
			} # while

			return $string;
		} # function _bad_protocol

		###############################################################################
		# This function searches for URL protocols at the beginning of $string, while
		# handling whitespace and HTML entities.
		###############################################################################
		function _bad_protocol_once($string)
		{
			if ($string[0]=='#') return $string; // its an anchor, dont check for protocol any further
			$string2 = preg_split('/:|&#58;|&#x3a;/i', $string, 2);
			if(isset($string2[1]) && !preg_match('%/\?%',$string2[0]))
			{
				return $this->_bad_protocol_once2($string2[0]).trim($string2[1]);
			} else {
				if (!isset($string2[1]))
				{
					return $string2[0];
				} else {
					return '';
				}
			}
			return '';
		} # function _bad_protocol_once


		###############################################################################
		# This function processes URL protocols, checks to see if they're in the white-
		# list or not, and returns different data depending on the answer.
		###############################################################################
		function _bad_protocol_once2($string)
		{
			$string2 = $this->_decode_entities($string);
			$string2 = preg_replace('/\s/', '', $string2);
			$string2 = $this->_no_null($string2);
			$string2 = preg_replace('/\xad+/', '', $string2); # deals with Opera "feature"
			$string2 = strtolower($string2);

			$allowed = false;
			if(is_array($this->allowed_protocols) && count($this->allowed_protocols) > 0)
			{
				foreach ($this->allowed_protocols as $one_protocol)
				{
					if (strtolower($one_protocol) == $string2)
					{
						$allowed = true;
						break;
					}
				}
			}
			if ($allowed)
			{
				return "$string2:";
			}
			else
			{
				return '';
			}
		} # function _bad_protocol_once2

		###############################################################################
		# This function performs different checks for attribute values. The currently
		# implemented checks are "maxlen", "minlen", "maxval", "minval" and "valueless"
		# with even more checks to come soon.
		###############################################################################
		function _check_attr_val($value, $vless, $checkname, $checkvalue)
		{
			$ok = true;
			
			switch (strtolower($checkname))
			{
				# The maxlen check makes sure that the attribute value has a length not
				# greater than the given value. This can be used to avoid Buffer Overflows
				# in WWW clients and various Internet servers.
				case 'maxlen':
					if (strlen($value) > $checkvalue)
					{
						$ok = false;
					}
					break;

				# The minlen check makes sure that the attribute value has a length not
				# smaller than the given value.
				case 'minlen':
					if (strlen($value) < $checkvalue)
					{
						$ok = false;
					}
					break;

				# The maxval check does two things: it checks that the attribute value is
				# an integer from 0 and up, without an excessive amount of zeroes or
				# whitespace (to avoid Buffer Overflows). It also checks that the attribute
				# value is not greater than the given value.
				# This check can be used to avoid Denial of Service attacks.
				case 'maxval':
					if (!preg_match('/^\s{0,6}[0-9]{1,6}\s{0,6}$/', $value))
					{
						$ok = false;
					}
					if ($value > $checkvalue)
					{
						$ok = false;
					}
					break;

				# The minval check checks that the attribute value is a positive integer,
				# and that it is not smaller than the given value.
				case 'minval':
					if (!preg_match('/^\s{0,6}[0-9]{1,6}\s{0,6}$/', $value))
					{
						$ok = false;
					}
					if ($value < $checkvalue)
					{
						$ok = false;
					}
					break;

				# The valueless check checks if the attribute has a value
				# (like <a href="blah">) or not (<option selected>). If the given value
				# is a "y" or a "Y", the attribute must not have a value.
				# If the given value is an "n" or an "N", the attribute must have one.
				case 'valueless':
				if (strtolower($checkvalue) != $vless)
				{
					$ok = false;
				}
				break;

				# The minval check checks that the attribute value is a positive integer,
				# and that it is not smaller than the given value.
				case 'match':
					if (!preg_match($checkvalue, $value)) {
						$ok = false;
					}
					break;

			} # switch

			return $ok;
		} # function _check_attr_val

		###############################################################################
		# This function changes the character sequence  \"  to just  "
		# It leaves all other slashes alone. It's really weird, but the quoting from
		# preg_replace(//e) seems to require this.
		###############################################################################
		function _stripslashes($string)
		{
			return preg_replace('%\\\\"%', '"', $string);
		} # function _stripslashes

		###############################################################################
		# This function deals with parsing errors in _hair(). The general plan is
		# to remove everything to and including some whitespace, but it deals with
		# quotes and apostrophes as well.
		###############################################################################
		function _html_error($string)
		{
			return preg_replace('/^("[^"]*("|$)|\'[^\']*(\'|$)|\S)*\s*/', '', $string);
		} # function _html_error

		###############################################################################
		# This function decodes numeric HTML entities (&#65; and &#x41;). It doesn't
		# do anything with other entities like &auml;, but we don't need them in the
		# URL protocol white listing system anyway.
		###############################################################################
		function _decode_entities($string)
		{
			$string = preg_replace('/&#([0-9]+);/e', 'chr("\\1")', $string);
			$string = preg_replace('/&#[Xx]([0-9A-Fa-f]+);/e', 'chr(hexdec("\\1"))', $string);
			return $string;
		} # function _decode_entities

		###############################################################################
		# This function returns kses' version number.
		###############################################################################
		function _version()
		{
			return '0.0.2 (OOP fork of kses 0.2.1)';
		} # function _version
	}
?>
