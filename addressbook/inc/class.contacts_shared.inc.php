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
		var $db;
		var $account_id;
		var $stock_contact_fields;     // This is an array of all the fields in the addressbook
		var $email_types;              // VCard email type array
		var $total_records;            // This will contain numrows for data retrieved

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

		// sort a multi-dimensional array on an array element
		// using the named element
		// This is neither used (yet) or guaranteed to work
		function qsort_multiarray($array,$column,$order = "ASC",$left = 0,$right = -1,$num=0) 
		{ 
			if($right == -1)
				{ $right = count($array) - 1; }

			$i=0;
			
			if (!$num) {
				$num=0;
				echo "nonum";
				if ($column && !empty($column)) {
					while (list($name,$value) = each($array[0])) {
						if ($column == $name) {
							$num = $i;
							break;
						}
						$i++;
					}
				}
			}
			echo "<br>". $num ." - name='".$name."', value='". $value . "'";

			$lefts = $left;
			$rights = $right;
			$middle = $array[($left + $right) / 2][$num];

			if($rights > $lefts) {
				do {
					if($order == "ASC") {
						while($array[$lefts][$num]<$middle) $lefts++; 
						while($array[$rights][$num]>$middle) $rights--; 
					} else {
						while($array[$lefts][$num]>$middle) $lefts++;
						while($array[$rights][$num]<$middle) $rights--;
					}

					if($lefts <= $rights) {
						$tmp = $array[$lefts];
						$array[$lefts++] = $array[$rights];
						$array[$rights--] = $tmp;
					}
				} while($lefts <= $rights);

				$array = $this->qsort_multiarray($array,"",$order,$left,$rights,$num); 
				$array = $this->qsort_multiarray($array,"",$order,$lefts,$right,$num); 
			} 
			return $array; 
		}
	}
?>
