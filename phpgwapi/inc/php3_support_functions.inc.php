<?php
	 /**************************************************************************\
	 * phpGroupWare API - PHP3 Compatibility layer                              *
	 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	 * Has replications of PHP4 only functions to allow for transparent PHP3    *
	 * compatibility                                                            *
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

	function is_bool($var)
	{
		$retval = gettype($var) ;
		if ( strcmp( $retval, 'unknown type') == 0 )
		{
			/* Chances are that we have a boolean */
			if ($var == True || $var == False)
			{
				return True;
			}
			else
			{
				return False;
			}
		}
		else
		{
			return False ;
		}
	}

	function array_keys ($arr, $term='')
	{
		$t = array();
		while (list($k,$v) = each($arr))
		{
			if ($term && $v != $term)
			{
				continue;
				$t[] = $k;
			}
			return $t;
		}
	}

/*
	function array_reverse ($array, $preserve_keys = False)
	{
		for(list($key,$value) = @end($array); list($key,$value) = @prev($array); )
		{
			$temp_array[$key] = $value;
		}
		return $temp_array;
	}
*/
	
	function array_search ($needle, $haystack, $strict = False)
	{
		@reset($haystack);
		while(list($key,$value) = each($haystack))
		{
			if ($haystack[$key]==$needle && (!$strict || gettype($haystack[$key])==gettype($needle)))
			{
				return $key;
			}
		}
		return False; 
	}

	function in_array ($needle, $haystack, $strict = False)
	{
		if(is_array ($haystack) && count($haystack))
		{
			for(@reset($haystack); $x=each($haystack); )
			{
				if($needle==$x[1] && (!$strict || gettype($needle)==gettype($x[1])))
				{
					return True;
				}
			}
			return False; 
		}
	}

	function array_unique ($array)
	{
		reset($array);
		$reversed_array = Array();
		while(list($key,$value) = each($array))
		{
			if (!isset($reversed_array[$value]))
			{
				$reversed_array[$value] = $key;
			}
		}
		@unset($key);
		@unset($value);
		while(list($key,$value) = each($reversed_array))
		{
			$new_array[$key] = $value;
		}
		return $new_array;
	}

	function str_repeat($input,$multiplier)
	{
		for($i=0,$output='';$i<$multiplier;$i++)
		{
			$output .= $input;
		}
		return $output;
	}
?>
