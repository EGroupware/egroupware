<?php
	/**************************************************************************\
	* phpGroupWare API - Contact Management Shared Routines                    *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	*        and Miles Lott <milosch@phpgroupware.org>                         *
	*        and Bettina Gille <ceb@phpgroupware.org>                          *
	* View and manipulate contact records                                      *
	* Copyright (C) 2001, 2002 Joseph Engo, Miles Lott, Bettina Gille          *
	* -------------------------------------------------------------------------*
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org                                              * 
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

	if (!isset($GLOBALS['phpgw_info']['server']['contact_repository']))
	{
		$GLOBALS['phpgw_info']['server']['contact_repository'] = 'sql';
	}
	require_once(PHPGW_API_INC . '/class.contacts_'.$GLOBALS['phpgw_info']['server']['contact_repository'] . '.inc.php');

	class contacts extends contacts_
	{
		function contacts()
		{
			$this->contacts_();	// call constructor of extended class
		}

		/*!
		@function check_perms
		@abstract checks if user has the necessary permissions on a contact
		@syntax check_perms($rights,$needed,$addr=False)
		@param $rights integer the rights the user has / grants from the owner of the contact, only used if $addr not given
		@param $needed integer PHPGW_ACL_{READ|EDIT|DELETE}
		@param $addr mixed contact-array or contact-id, if False rights have to be supplyed in $rights
		*/
		function check_perms($rights,$needed,$addr=False)
		{
			//echo "<p>contacts::check_perms($rights,$needed,".print_r($addr,True).")";
			if ($addr !== False)	// addr-record or id given
			{
				if(@is_array($addr))
				{
					if (isset($addr['rights']))
					{
						$rights = $addr['rights'];
					}
					elseif (isset($addr['owner']))
					{
						$rights = $this->grants[$addr['owner']];
					}
					else
					{
						$id = (int)(isset($addr['id']) ? $addr['id'] : $addr['ab_id']);
					}
				}
				else
				{
					$id = (int)$addr;
				}
				if (isset($id))
				{
					$addr = $this->read_single_entry($id,array('owner' => 'owner'));
					$rights = @$addr[0]['rights'];
					//echo "addr($id)=<pre>".print_r($addr[0],True)."</pre>\n";
				}
			}
			$ret = !!((int)$rights & $needed);
			//echo " rights=$rights, id=$id => ".($ret?'True':'False')."</p>\n";
			//echo "grants=<pre>".print_r($this->grants,True)."</pre>\n";

			return $ret;
		}

		function split_stock_and_extras($fields)
		{
			settype($fields, 'array');
			foreach($fields as $field => $value)
			{
				/* Depending on how the array was built, this is needed. */
				if(@is_int($value))
				{
					$value = $field;
				}
				if ($this->stock_contact_fields[$field])
				{
					$stock_fields[$field]     = $value;
					$stock_fieldnames[$field] = $this->stock_contact_fields[$field];
				}
				elseif (!isset($this->non_contact_fields[$field]))
				{
					$extra_fields[$field] = $value;
				}
			}
			return array($stock_fields,$stock_fieldnames,$extra_fields);
		}

		function loop_addslashes($fields)
		{
			$absf = $this->stock_contact_fields;
			foreach($absf as $t => $nul)
			{
				$ta[] = $this->db->db_addslashes($fields[$t]);
			}
			return $ta;
		}

		/* This will take an array or integer */
		function delete($id)
		{
			if(@is_array($id))
			{
				foreach($id as $nul => $t_id)
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
			if($a[1] == $b[1])
			{
				return 0;
			}
			return ($a[1]>$b[1])?1:-1;
		}

		function desc_sort($a,$b)
		{
			echo "<br>A:'".$a."' B:'".$b;
			if($a[1]==$b[1])
			{
				return 0;
			}
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

			if((strlen($s1) == 0))
			{
				return 0;
			}

			if((strlen($s2) == 0))
			{
				return 1;
			}

			if(strlen ($s1) > strlen ($s2))
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

		function formatted_address($id, $business = True, $afont = '', $asize = '2')
		{
			$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('addressbook'));
			$s = CreateObject('phpgwapi.sbox');

			$fields = array(
				'n_given'  => 'n_given',
				'n_family' => 'n_family',
				'title'    => 'title',
				'org_name' => 'org_name',
				'org_unit' => 'org_unit',
				'adr_one_street'      => 'adr_one_street',
				'adr_one_locality'    => 'adr_one_locality',
				'adr_one_postalcode'  => 'adr_one_postalcode',
				'adr_one_region'      => 'adr_one_region',
				'adr_one_countryname' => 'adr_one_countryname',
				'adr_two_street'      => 'adr_two_street',
				'adr_two_locality'    => 'adr_two_locality',
				'adr_two_postalcode'  => 'adr_two_postalcode',
				'adr_two_region'      => 'adr_two_region',
				'adr_two_countryname' => 'adr_two_countryname'
			);

			list($address) = $this->read_single_entry($id,$fields);
			foreach($address as $k => $val)
			{
				$address[$k] = $GLOBALS['phpgw']->strip_html($val);
			}

			if ($address['title'])
			{
				$title = $address['title'] . '&nbsp;';
			}

			if ($business)
			{
				if ($address['org_name'])
				{
					$company = $address['org_name'];
				}
				else
				{
					$company = $title . $address['n_given'] . '&nbsp;' . $address['n_family'];
				}

				$street  = $address['adr_one_street'];
				$city    = $address['adr_one_locality'];
				$zip     = $address['adr_one_postalcode'];
				$state   = $address['adr_one_region'];
				$country = $address['adr_one_countryname'];
			}
			else
			{
				$company = $title . $address['n_given'] . '&nbsp;' . $address['n_family'];
				$street  = $address['adr_two_street'];
				$city    = $address['adr_two_locality'];
				$zip     = $address['adr_two_postalcode'];
				$state   = $address['adr_two_region'];
				$country = $address['adr_two_countryname'];
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

			if (!$afont)
			{
				$afont = $GLOBALS['phpgw_info']['theme']['font'];
			}

			$a .= $t->set_var('font',$afont);
			$a .= $t->set_var('fontsize',$asize);
			$a .= $t->set_var('company',$company);
			$a .= $t->set_var('department',$address['org_unit']);
			$a .= $t->set_var('street',$street);
			$a .= $t->set_var('city',$city);
			$a .= $t->set_var('zip',$zip);
			$a .= $t->set_var('state',$state);

			if ($country != $GLOBALS['phpgw_info']['user']['preferences']['common']['country'])
			{
				$countryname = $s->get_full_name($country);
				$a .= $t->set_var('country',lang($countryname));
			}

			$a .= $t->fp('out','address_format');
			return $a;
		}

		function formatted_address_full($id, $business = True, $afont = '', $asize = '2')
		{
			$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('addressbook'));
			$s = CreateObject('phpgwapi.sbox');

			$fields = array(
				'n_given'				=> 'n_given',
				'n_family'				=> 'n_family',
				'title'					=> 'title',
				'org_name'				=> 'org_name',
				'org_unit'				=> 'org_unit',
				'adr_one_street'		=> 'adr_one_street',
				'adr_one_locality'		=> 'adr_one_locality',
				'adr_one_postalcode'	=> 'adr_one_postalcode',
				'adr_one_region'		=> 'adr_one_region',
				'tel_work'				=> 'tel_work',
				'tel_fax'				=> 'tel_fax',
				'email'					=> 'email',
				'url'					=> 'url',
				'adr_one_countryname'	=> 'adr_one_countryname',
				'adr_two_street'		=> 'adr_two_street',
				'adr_two_locality'		=> 'adr_two_locality',
				'adr_two_postalcode'	=> 'adr_two_postalcode',
				'adr_two_region'		=> 'adr_two_region',
				'adr_two_countryname'	=> 'adr_two_countryname',
				'tel_home'				=> 'tel_home',
				'email_home'			=> 'email_home'
			);

			list($address) = $this->read_single_entry($id,$fields);
			foreach($address as $k => $val)
			{
				$address[$k] = $GLOBALS['phpgw']->strip_html($val);
			}

			if($address['title'])
			{
				$title = $address['title'] . '&nbsp;';
			}

			if($business)
			{
				if($address['org_name'])
				{
					$company = $address['org_name'];
				}
				else
				{
					$company = $title . $address['n_given'] . '&nbsp;' . $address['n_family'];
				}

				$street		= $address['adr_one_street'];
				$city		= $address['adr_one_locality'];
				$zip		= $address['adr_one_postalcode'];
				$state		= $address['adr_one_region'];
				$country	= $address['adr_one_countryname'];
				$tel		= $address['tel_work'];
				$email		= $address['email'];
			}
			else
			{
				$company	= $title . $address['n_given'] . '&nbsp;' . $address['n_family'];
				$street		= $address['adr_two_street'];
				$city		= $address['adr_two_locality'];
				$zip		= $address['adr_two_postalcode'];
				$state		= $address['adr_two_region'];
				$country	= $address['adr_two_countryname'];
				$tel		= $address['tel_home'];
				$email		= $address['email_home'];
			}

			if(!$country)
			{
				$country = $GLOBALS['phpgw_info']['user']['preferences']['common']['country'];
			}

			if(file_exists(PHPGW_SERVER_ROOT . SEP . 'addressbook' . SEP . 'templates' . SEP .'default' . SEP . 'full_format_' . strtolower($country) . '.tpl'))
			{
				$a = $t->set_file(array('address_format' => 'full_format_' . strtolower($country) . '.tpl'));
			}
			else
			{
				$a = $t->set_file(array('address_format' => 'full_format_us.tpl'));
			}

			if(!$afont)
			{
				$afont = $GLOBALS['phpgw_info']['theme']['font'];
			}

			$a .= $t->set_var('font',$afont);
			$a .= $t->set_var('fontsize',$asize);
			$a .= $t->set_var('lang_url',lang('url'));
			$a .= $t->set_var('lang_email',lang('email'));
			$a .= $t->set_var('lang_fax',lang('fax number'));
			$a .= $t->set_var('lang_fon',lang('phone number'));
			$a .= $t->set_var('company',$company);
			$a .= $t->set_var('department',$address['org_unit']);
			$a .= $t->set_var('street',$street);
			$a .= $t->set_var('city',$city);
			$a .= $t->set_var('zip',$zip);
			$a .= $t->set_var('state',$state);
			$a .= $t->set_var('email',$email);
			$a .= $t->set_var('tel',$tel);
			$a .= $t->set_var('fax',$address['tel_fax']);
			$a .= $t->set_var('url',$address['url']);

			if($country != $GLOBALS['phpgw_info']['user']['preferences']['common']['country'])
			{
				$countryname = $s->get_full_name($country);
				$a .= $t->set_var('country',lang($countryname));
			}

			$a .= $t->fp('out','address_format');
			return $a;
		}

		function formatted_address_line($id, $business = True, $afont = '', $asize = '2')
		{
			$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('addressbook'));
			$s = CreateObject('phpgwapi.sbox');

			$fields = array(
				'n_given'				=> 'n_given',
				'n_family'				=> 'n_family',
				'title'					=> 'title',
				'org_name'				=> 'org_name',
				'adr_one_street'		=> 'adr_one_street',
				'adr_one_locality'		=> 'adr_one_locality',
				'adr_one_postalcode'	=> 'adr_one_postalcode',
				'adr_one_region'		=> 'adr_one_region',
				'adr_one_countryname'	=> 'adr_one_countryname',
				'adr_two_street'		=> 'adr_two_street',
				'adr_two_locality'		=> 'adr_two_locality',
				'adr_two_postalcode'	=> 'adr_two_postalcode',
				'adr_two_region'		=> 'adr_two_region',
				'adr_two_countryname'	=> 'adr_two_countryname'
			);

			list($address) = $this->read_single_entry($id,$fields);
			foreach($address as $k => $val)
			{
				$address[$k] = $GLOBALS['phpgw']->strip_html($val);
			}

			if($address['title'])
			{
				$title = $address['title'] . '&nbsp;';
			}

			if($business)
			{
				if($address['org_name'])
				{
					$company = $address['org_name'];
				}
				else
				{
					$company = $title . $address['n_given'] . '&nbsp;' . $address['n_family'];
				}

				$street  = $address['adr_one_street'];
				$city    = $address['adr_one_locality'];
				$zip     = $address['adr_one_postalcode'];
				$state   = $address['adr_one_region'];
				$country = $address['adr_one_countryname'];
			}
			else
			{
				$company = $title . $address['n_given'] . '&nbsp;' . $address['n_family'];
				$street  = $address['adr_two_street'];
				$city    = $address['adr_two_locality'];
				$zip     = $address['adr_two_postalcode'];
				$state   = $address['adr_two_region'];
				$country = $address['adr_two_countryname'];
			}

			if(!$country)
			{
				$country = $GLOBALS['phpgw_info']['user']['preferences']['common']['country'];
			}

			if(file_exists(PHPGW_SERVER_ROOT . SEP . 'addressbook' . SEP . 'templates' . SEP .'default' . SEP . 'line_format_' . strtolower($country) . '.tpl'))
			{
				$a = $t->set_file(array('address_format' => 'line_format_' . strtolower($country) . '.tpl'));
			}
			else
			{
				$a = $t->set_file(array('address_format' => 'line_format_us.tpl'));
			}

			if(!$afont)
			{
				$afont = $GLOBALS['phpgw_info']['theme']['font'];
			}

			$a .= $t->set_var('font',$afont);
			$a .= $t->set_var('fontsize',$asize);
			$a .= $t->set_var('company',$company);
			$a .= $t->set_var('street',$street);
			$a .= $t->set_var('city',$city);
			$a .= $t->set_var('zip',$zip);
			$a .= $t->set_var('state',$state);

			if($country != $GLOBALS['phpgw_info']['user']['preferences']['common']['country'])
			{
				$countryname = $s->get_full_name($country);
				$a .= $t->set_var('country','&nbsp;°&nbsp;' . lang($countryname));
			}

			$a .= $t->fp('out','address_format');
			return $a;
		}
	}
?>
