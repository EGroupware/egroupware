<?php
  /**************************************************************************\
  * phpGroupWare API - Contact Management Shared Routines                    *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  *   and Miles Lott <milosch@phpgroupware.org>                              *
  *   and Bettina Gille <ceb@phpgroupware.org>                               *
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
			while (list($field,$value) = @each($fields))
			{
				/* Depending on how the array was built, this is needed. */
				if (gettype($value) == 'integer')
				{
					$value = $field;
				}
				if ($this->stock_contact_fields[$field])
				{
					$stock_fields[$field]     = $value;
					$stock_fieldnames[$field] = $this->stock_contact_fields[$field];
				}
				else
				{
					$extra_fields[$field] = $value;
				}
			}
			return array($stock_fields,$stock_fieldnames,$extra_fields);
		}

		function loop_addslashes($fields)
		{
			$absf = $this->stock_contact_fields;
			while ($t = each($absf))
			{
				$ta[] = $this->db->db_addslashes($fields[$t[0]]);
			}
			reset($absf);
			return $ta;
		}

		/* This will take an array or integer */
		function delete($id)
		{
			if (gettype($id) == 'array')
			{
				while (list($null,$t_id) = each($id))
				{
					$this->delete_($t_id);
				}
			}
			else
			{
				$this->delete_($id);
			}
		}

		function asc_sort($a,$b)
		{
			echo "<br>A:'".$a."' B:'".$b;
			if($a[1]==$b[1]) return 0;
			return ($a[1]>$b[1])?1:-1;
		}

		function desc_sort($a,$b)
		{
			echo "<br>A:'".$a."' B:'".$b;
			if($a[1]==$b[1]) return 0;
			return ($a[1]<$b[1])?1:-1;
		}

		/*
		comesafter ($s1, $s2)
		Returns 1 if $s1 comes after $s2 alphabetically, 0 if not.
		*/
		function comesafter ($s1, $s2)
		{
			/*
			We don't want to overstep the bounds of one of the strings and segfault,
			so let's see which one is shorter.
			*/
			$order = 1;

			if ( (strlen($s1) == 0) )
			{
				return 0;
			}

			if ( (strlen($s2) == 0) )
			{
				return 1;
			}

			if (strlen ($s1) > strlen ($s2))
			{
				$temp = $s1;
				$s1 = $s2;
				$s2 = $temp;
				$order = 0;
			}

			for ($index = 0; $index < strlen ($s1); $index++)
			{
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
			if($DEBUG) { echo '<br>'; }
			for($i=0;$i<count($ldap_fields);$i++)
			{
				$yes = True;

				if ($ldap_fields[$i]['uidnumber'][0])
				{
					reset($filterfields);
					while (list($col,$filt) = each($filterfields))
					{
						if ($col == 'phpgwcontactcatid')
						{
							$colarray = explode(',',$ldap_fields[$i][$col][0]);
							if ($colarray[1])
							{
								while(list($key,$val) = each ($colarray))
								{
									if($DEBUG) { echo '&nbsp;&nbsp;Testing "'.$col.'" for "'.$val.'"'; }
									if ($val == $filt)
									{
										if($DEBUG) { echo ', and number '.$ldap_fields[$i]['uidnumber'][0].' matched.'.'&nbsp;&nbsp;'; }
										$yes &= True;
										$match++;
										break;
									}
								}
							}
							else
							{
								if($DEBUG) { echo '&nbsp;&nbsp;Testing "'.$col.'" for "'.$filt.'"'; }
								if ($ldap_fields[$i][$col][0] == $filt)
								{
									if($DEBUG) { echo ', and number '.$ldap_fields[$i]['uidnumber'][0].' matched.'.'&nbsp;&nbsp;'; }
									$yes &= True;
									$match++;
								}
								else
								{
									if($DEBUG) { echo ', but number '.$ldap_fields[$i]['uidnumber'][0].' did not match.'.'&nbsp;&nbsp;'; }
									$yes &= False;
									$match--;
								}							
							}
						}
						else
						{
							if($DEBUG) { echo '&nbsp;&nbsp;Testing "'.$col.'" for "'.$filt.'"'; }
							if ($ldap_fields[$i][$col][0] == $filt)
							{
								if($DEBUG) { echo ', and number '.$ldap_fields[$i]['uidnumber'][0].' matched.'.'&nbsp;&nbsp;'; }
								$yes &= True;
								$match++;
							}
							else
							{
								if($DEBUG) { echo ', but number '.$ldap_fields[$i]['uidnumber'][0].' did not match.'.'&nbsp;&nbsp;'; }
								$yes &= False;
								$match--;
							}
						}
					}

					if ($yes)
					{
						if($DEBUG) { echo $ldap_fields[$i]['uidnumber'][0].' matched all!'.'<br>'; }
						$new_ldap[] = $ldap_fields[$i];
					}
					else
					{
						if($DEBUG) { echo $ldap_fields[$i]['uidnumber'][0].' did not match all.'.'<br>'; }
					}
				}
			}
			if($DEBUG)
			{
				if($match)
				{
					echo '<br>'.$match.' total matches.'."\n";
				}
				else
				{
					echo '<br>No matches :('."\n";
				}
			}
			$this->total_records = count($new_ldap);

			return $new_ldap;
		}

		function formatted_address($id = '',$fields = '',$business = True)
		{
			$font = $GLOBALS['phpgw_info']['theme']['font'];

			$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('addressbook'));
			$s = CreateObject('phpgwapi.sbox');

			$address = $this->read_single_entry($id,$fields);

			if ($address[0]['title'])
			{
				$title = $address[0]['title'] . '&nbsp;';
			}

			if ($business)
			{
				$street  = $address[0]['adr_one_street'];
				$city    = $address[0]['adr_one_locality'];
				$zip     = $address[0]['adr_one_postalcode'];
				$state   = $address[0]['adr_one_region'];
				$country = $address[0]['adr_one_countryname'];
			}
			else
			{
				$street  = $address[0]['adr_two_street'];
				$city    = $address[0]['adr_two_locality'];
				$zip     = $address[0]['adr_two_postalcode'];
				$state   = $address[0]['adr_two_region'];
				$country = $address[0]['adr_two_countryname'];
			}

			if (! $country)
			{
				$country = $GLOBALS['phpgw_info']['user']['preferences']['common']['country'];
			}

			if (file_exists(PHPGW_SERVER_ROOT . SEP . 'addressbook' . SEP . 'templates' . SEP .'default' . SEP . 'format_' . strtolower($country) . '.tpl'))
			{
				$a = $t->set_file(array('address_format' => 'format_' . strtolower($country) . '.tpl'));
			}
			else
			{
				$a = $t->set_file(array('address_format' => 'format_us.tpl'));
			}

			$a .= $t->set_var('font',$font);
			$a .= $t->set_var('title',$title);
			$a .= $t->set_var('firstname',$address[0]['n_given']);
			$a .= $t->set_var('lastname',$address[0]['n_family']);
			$a .= $t->set_var('company',$address[0]['org_name']);
			$a .= $t->set_var('department',$address[0]['org_unit']);
			$a .= $t->set_var('street',$street);
			$a .= $t->set_var('city',$city);
			$a .= $t->set_var('zip',$zip);
			$a .= $t->set_var('state',$state);

			$countryname = $s->get_full_name($country);
			$a .= $t->set_var('country',lang($countryname));

			$a .= $t->fp('out','address_format');
			return $a;
		}
	}
?>
