<?php
  /**************************************************************************\
  * phpGroupWare API - Accounts manager for SQL                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * View and manipulate contact records                                      *
  * Copyright (C) 2001 Joseph Engo                                           *
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

	class contacts extends contacts_
	{
		function check_perms($has, $needed)
		{
			return (!!($has & $needed) == True);
		}

		function split_stock_and_extras($fields)
		{
			while (list($field,$value) = each($fields)) {
				// Depending on how the array was build, this is needed.
				// Yet, I can't figure out why ....
				if (gettype($value) == "integer") {
					$field = $value;
				}
				if ($this->stock_contact_fields[$field]) {
					$stock_fields[$field]     = $value;
					$stock_fieldnames[$field] = $this->stock_contact_fields[$field];
				} else {
					$extra_fields[$field] = $value;
				}
			}
			return array($stock_fields,$stock_fieldnames,$extra_fields);
		}

		function loop_addslashes($fields)
		{
			$absf = $this->stock_contact_fields;
			while ($t = each($absf)) {
				$ta[] = addslashes($fields[$t[0]]);
			}
			reset($absf);        // Is this needed ?
			return $ta;
		}

		// This will take an array or integer
		function delete($id)
		{
			if (gettype($id) == "array") {
				while (list($null,$t_id) = each($id)) {
					$this->delete_($t_id);
				}
			} else {
				$this->delete_($id);
			}
		}

		function asc_sort($a,$b) { 
			echo "<br>A:'".$a."' B:'".$b;
			if($a[1]==$b[1]) return 0; 
			return ($a[1]>$b[1])?1:-1; 
		}

		function desc_sort($a,$b) { 
			echo "<br>A:'".$a."' B:'".$b;
			if($a[1]==$b[1]) return 0; 
			return ($a[1]<$b[1])?1:-1; 
		}

		/** 
		** comesafter ($s1, $s2) 
		** 
		** Returns 1 if $s1 comes after $s2 alphabetically, 0 if not. 
		**/ 
		function comesafter ($s1, $s2) { 
			/** 
			** We don't want to overstep the bounds of one of the strings and segfault, 
			** so let's see which one is shorter. 
			**/ 
			$order = 1;

			if ( (strlen($s1) == 0) ) {
				return 0;
			}
			
			if ( (strlen($s2) == 0) ) {
				return 1;
			}

			if (strlen ($s1) > strlen ($s2)) {
				$temp = $s1;
				$s1 = $s2;
				$s2 = $temp;
				$order = 0;
			}

			for ($index = 0; $index < strlen ($s1); $index++) { 
				/* $s1 comes after $s2 */ 
				if (strtolower($s1[$index]) > strtolower($s2[$index])) { return ($order); }

				/* $s1 comes before $s2 */
				if (strtolower($s1[$index]) < strtolower($s2[$index])) { return (1 - $order); }
			}
				/* Special case in which $s1 is a substring of $s2 */

			return ($order); 
		}

		/*
		* asortbyindex ($sortarray, $index)
		*
		* Sort a multi-dimensional array by a second-degree index. For instance, the 0th index
		* of the Ith member of both the group and user arrays is a string identifier. In the
		* case of a user array this is the username; with the group array it is the group name.
		* asortby
		*/
		function asortbyindex ($sortarray, $index)
		{
			$lastindex = count($sortarray) - 2;
			for ($subindex = 0; $subindex < $lastindex; $subindex++)
			{
				$lastiteration = $lastindex - $subindex;
				for ($iteration = 0; $iteration < $lastiteration; $iteration++)
				{
					$nextchar = 0;
					if ($this->comesafter($sortarray[$iteration][$index], $sortarray[$iteration + 1][$index]))
					{
						$temp = $sortarray[$iteration];
						$sortarray[$iteration] = $sortarray[$iteration + 1];
						$sortarray[$iteration + 1] = $temp;
					}
				}
			}
			return ($sortarray);
		}

		function arsortbyindex ($sortarray, $index)
		{
			$lastindex = count($sortarray) - 1;
			for ($subindex = $lastindex; $subindex > 0; $subindex--)
			{
				$lastiteration = $lastindex - $subindex;
				for ($iteration = $lastiteration; $iteration > 0; $iteration--)
				{
					$nextchar = 0;
					if ($this->comesafter($sortarray[$iteration][$index], $sortarray[$iteration - 1][$index]))
					{
						$temp = $sortarray[$iteration];
						$sortarray[$iteration] = $sortarray[$iteration - 1];
						$sortarray[$iteration - 1] = $temp;
					}
				}
			}
			return ($sortarray);
		}

		function filter_ldap ($ldap_fields,$filterfields,$DEBUG=0)
		{
			$match = 0;
			if($DEBUG) { echo "<br>"; }
			for($i=0;$i<count($ldap_fields);$i++) {
				$yes = True;

				reset($filterfields);
				while (list($col,$filt) = each($filterfields))
				{
					if($DEBUG) { echo '&nbsp;&nbsp;Testing "'.$col.'" for "'.$filt.'"'; }
					if ($ldap_fields[$i][$col][0] == $filt)
					{
						if($DEBUG) { echo ', and number '.$ldap_fields[$i]["uidnumber"][0].' matched.'."&nbsp;&nbsp;"; }
						$yes &= True;
						$match++;
					}
					else
					{
						if($DEBUG) { echo ', but number '.$ldap_fields[$i]["uidnumber"][0].' did not match.'."&nbsp;&nbsp;"; }
						$yes &= False;
						$match--;
					}
				}

				if ($yes)
				{
					if($DEBUG) { echo $ldap_fields[$i]["uidnumber"][0].' matched all!'."<br>"; }
					$new_ldap[] = $ldap_fields[$i];
				}
				else
				{
					if($DEBUG) { echo $ldap_fields[$i]["uidnumber"][0].' did not match all.'."<br>"; }
				}
			}
			if ($match) { if($DEBUG) { echo '<br'.$match.' total matches.'."\n"; } }
			else        { if($DEBUG) { echo '<br>No matches :('."\n"; } }
			$this->total_records = count($new_ldap);

			return $new_ldap;
		}
	}
?>
