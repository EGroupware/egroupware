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

	/* array_keys (PHP 4 >= 4.0.0)
	 *   array array_keys (array input, mixed [search_value])
	 * array_keys() returns the keys, numeric and string, from the input array.
	 */
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

	/* array_merge (PHP 4 >= 4.0.0)
	 *   array array_merge (array array1, array array2 [, array ...])
	 * array_merge() merges the elements of two or more arrays together so that the values
	 * of one are appended to the end of the previous one. It returns the resulting array.
	 */
	function array_merge ($array1, $array2, $array3 = '', $array4 = '', $array5 = '', $array6 = '', $array7 = '', $array8 = '', $array9 = '', $array10 = '')
	{
		$rarray = array ();

		for ($i = 1; $i <= 10; $i++)
		{
			$this_array = ${'array' . $i};
			if (is_array ($this_array))
			{
				reset ($this_array);
				while (list ($key, $value) = each ($this_array))
				{
					if (is_int ($key))
					{
						$rarray[] = $value;
					}
					else
					{
						$rarray[$key] = $value;
					}
				}
			}
		}

		return $rarray;
	}

	/* array_pop (PHP 4 >= 4.0.0)
	 *   mixed array_pop (array array)
	 * array_pop() pops and returns the last value of the array, shortening the array by
	 * one element. If array is empty (or is not an array), NULL will be returned.
	 */
	function array_pop(&$array)
	{
		if(!is_array($array) || @count($array) == 0)
		{
			return NULL;
		}

		reset($array);
		$rtrn = array();

		$i = count($array) + 1;

		while(list($key,$value) = each($array))
		{
			$i--;
			if($i == 1)
			{
				$last = $value;
			}
			else
			{
				$rtrn[$key] = $value;
			}
		}
		$array = $rtrn;
		return $last;
	}

	/* array_push 
	 *   int array_push (array array, mixed var [, mixed ...])
	 * array_push() treats array as a stack, and pushes the passed variables onto the end
	 * of array. The length of array increases by the number of variables pushed. Has the
	 * same effect as '$array[] = $var;' repeated for each var.
	*/
	/*
	function array_push()
	{
	}
	*/

	/* array_reverse (PHP 4 >= 4.0.0)
	 *   array array_reverse (array array [, bool preserve_keys])
	 * array_reverse() takes input array and returns a new array with the order of the
	 * elements reversed, preserving the keys if preserve_keys is TRUE.
	 *   Note: The second parameter was added in PHP 4.0.3.
	 */
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

	/* array_search (PHP 4 >= 4.0.5)
	 *   mixed array_search (mixed needle, array haystack [, bool strict])
	 * Searches haystack for needle and returns the key if it is found in the array, FALSE
	 * otherwise.
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

	/* array_shift (PHP 4 >= 4.0.0)
	 *   mixed array_shift (array array)
	 * array_shift() shifts the first value of the array off and returns it, shortening the
	 * array by one element and moving everything down. If array is empty (or is not an
	 * array), NULL will be returned.
	 */
	function array_shift(&$array)
	{
		if(!is_array($array) || @count($array) == 0)
		{
			return NULL;
		}

		reset($array);
		$rtrn = array();
		$i = 0;

		while(list($key,$value) = each($array))
		{
			$i++;
			if($i == 1)
			{
				$one = $value;
			}
			else
			{
				$rtrn[$key] = $value;
			}
		}
		$array = $rtrn;
		return $one;
	}

	/* array_unique (PHP 4)
	 *   array array_unique (array array)
	 * array_unique() takes input array and returns a new array without duplicate values.
	 */
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

	/* array_unshift (PHP 4 >= 4.0.0)
	 *   int array_unshift (array array, mixed var, mixed [...])
	 * array_unshift() prepends passed elements to the front of the array. Note that the
	 * list of elements is prepended as a whole, so that the prepended elements stay in the
	 * same order.  Returns the new number of elements in the array.
	 */
	/*
	function array_unshift()
	{
	}
	*/

	/* in_array (PHP 4 >= 4.0.0)
	 *   bool in_array (mixed needle, array haystack [, bool strict])
	 * Searches haystack for needle and returns TRUE if it is found in the array, FALSE
	 * otherwise.
	 */
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

	/* is_bool (PHP 4 >= 4.0.0)
	 *   bool is_bool (mixed var)
	 * is_bool --  Finds out whether a variable is a boolean
	 */
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

	/* str_repeat (PHP 4 >= 4.0.0)
	 *   string str_repeat (string input, int multiplier)
	 * Returns input_str repeated multiplier times. multiplier has to be greater than 0.
	 */
	function str_repeat($input,$multiplier)
	{
		for($i=0,$output='';$i<$multiplier;$i++)
		{
			$output .= $input;
		}
		return $output;
	}

	function print_r($array)
	{
		if(gettype($array)=="array")
		{
			echo '<ul>';
			while (list($index, $subarray) = each($array) )
			{
				echo '<li>'.$index.' <code>=&gt;</code>';
				print_r($subarray);
				echo '</li>';
			}
			echo '</ul>';
		}
		else
		{
			echo $array;
		}
	}
?>
