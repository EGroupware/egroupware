<?php
/**************************************************************************\
* phpGroupWare API - Accounts manager for SQL                              *
* This file written by Joseph Engo <jengo@phpgroupware.org>                *
* View and manipulate contact records using SQL                            *
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

	/*!
	 @class contacts
	 @abstract Contact List System
	 @discussion Author: jengo/Milosch <br>
	 This class provides a contact database scheme. <br>
	 It attempts to be based on the vcard 2.1 standard, with mods as needed to make for more reasonable sql storage. <br>
	 Syntax: CreateObject('phpgwapi.contacts'); <br>
	 Example1: $contacts = CreateObject('phpgwapi.contacts');
	*/
	class contacts_
	{
		var $db;
		var $ldap;
		var $nextid;
		var $std_table='';
		var $ext_table='phpgw_addressbook_extra';

		var $account_id;
		var $stock_contact_fields;	// This is an array of almost the fields in the phpgw_addressbook table
		var $non_contact_fields;    // Here are the rest: d,owner,access,lid,tid,cat_id
		var $email_types;			// VCard email type array
		var $total_records;			// This will contain numrows for data retrieved
		var $grants;                // This holds all of the users that have granted access to there entrys

		function contacts_()
		{
			global $phpgw, $phpgw_info;

			$this->db = $phpgw->db;
			$this->ldap = $phpgw->common->ldapConnect(
				$phpgw_info['server']['ldap_contact_host'],
				$phpgw_info['server']['ldap_contact_dn'],
				$phpgw_info['server']['ldap_contact_pw']
			);
			$this->account_id = $phpgw_info['user']['account_id'];
			$this->grants     = $phpgw->acl->get_grants('addressbook');

			// The left side are the array elements used throughout phpgw, right side are the ldap attributes
			$this->stock_contact_fields = array(
				'fn'                  => 'cn',        // 'prefix given middle family suffix'
				'n_given'             => 'givenname',   // firstname
				'n_family'            => 'sn',  // lastname
				'n_middle'            => 'middlename',
				'n_prefix'            => 'prefix',
				'n_suffix'            => 'suffix',
				'sound'               => 'audio',
				'bday'                => 'birthday',
				'note'                => 'description',
				'tz'                  => 'tz',
				'geo'                 => 'geo',
				'url'                 => 'url',
				'pubkey'              => 'publickey',

				'org_name'            => 'o',  // company
				'org_unit'            => 'ou',  // division
				'title'               => 'title',

				'adr_one_street'      => 'streetaddress',
				'adr_one_locality'    => 'locality', 
				'adr_one_region'      => 'st', 
				'adr_one_postalcode'  => 'postalcode',
				'adr_one_countryname' => 'countryname',
				'adr_one_type'        => 'phpgwadronetype', // address is domestic/intl/postal/parcel/work/home
				'label'               => 'phpgwaddresslabel', // address label

				'adr_two_street'      => 'phpgwadrtwostreet',
				'adr_two_locality'    => 'phpgwadrtwolocality', 
				'adr_two_region'      => 'phpgwadrtworegion', 
				'adr_two_postalcode'  => 'phpgwadrtwopostalcode',
				'adr_two_countryname' => 'phpgwadrtwocountryname',
				'adr_two_type'        => 'phpgwadrtwotype', // address is domestic/intl/postal/parcel/work/home

				'tel_work'            => 'telephonenumber',
				'tel_home'            => 'homephone',
				'tel_voice'           => 'voicetelephonenumber',
				'tel_fax'             => 'facsimiletelephonenumber', 
				'tel_msg'             => 'msgtelephonenumber',
				'tel_cell'            => 'cellphone',
				'tel_pager'           => 'pagertelephonenumber',
				'tel_bbs'             => 'bbstelephonenumber',
				'tel_modem'           => 'modemtelephonenumber',
				'tel_car'             => 'mobiletelephonenumber',
				'tel_isdn'            => 'isdnphonenumber',
				'tel_video'           => 'videophonenumber',
				'tel_prefer'          => 'preferphone', // home, work, voice, etc
				'email'               => 'mail',
				'email_type'          => 'mailtype', //'INTERNET','CompuServe',etc...
				'email_home'          => 'mailhome',
				'email_home_type'     => 'mailhometype' //'INTERNET','CompuServe',etc...
			);

			$this->non_contact_fields = array(
				'id'     => 'uidnumber',
				'lid'    => 'uid',
				'tid'    => 'phpgwtypeid',
				'cat_id' => 'phpgwcatid',
				'access' => 'phpgwaccess',
				'owner'  => 'phpgwowner'
			);

			/* Used to flag an address as being:
			   domestic OR  international(default)
			   parcel(default)
			   postal(default)
			   work(default) OR home
			*/
			$this->adr_types = array(
				'dom'    => lang('Domestic'),
				'intl'   => lang('International'),
				'parcel' => lang('Parcel'),
				'postal' => lang('Postal')
			);

			// Used to set preferphone field
			$this->tel_types = array(
				'work'  => 'work',
				'home'  => 'home',
				'voice' => 'voice',
				'fax'   => 'fax',
				'msg'   => 'msg',
				'cell'  => 'cell',
				'pager' => 'pager',
				'bbs'   => 'bbs',
				'modem' => 'modem',
				'car'   => 'car',
				'isdn'  => 'isdn',
				'video' => 'video'
			);

			// Used to set mail_type fields
			$this->email_types = array(
				'INTERNET'   => 'INTERNET',
				'CompuServe' => 'CompuServe',
				'AOL'        => 'AOL',
				'Prodigy'    => 'Prodigy',
				'eWorld'     => 'eWorld',
				'AppleLink'  => 'AppleLink',
				'AppleTalk'  => 'AppleTalk',
				'PowerShare' => 'PowerShare',
				'IBMMail'    => 'IBMMail',
				'ATTMail'    => 'ATTMail',
				'MCIMail'    => 'MCIMail',
				'X.400'      => 'X.400',
				'TLX'        => 'TLX'
			);
		}

		// send this the id and whatever fields you want to see
		function read_single_entry($id,$fields = '')
		{
			global $phpgw_info;
			if (!$fields || empty($fields)) { $fields = $this->stock_contact_fields; }
			list($stock_fields,$stock_fieldnames,$extra_fields) =
				$this->split_stock_and_extras($fields);

			if (count($stock_fieldnames))
			{
				$t_fields = "," . implode(",",$stock_fieldnames);
				if ($t_fields == ",")
				{
					unset($t_fields);
				}
			}

			$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], 'uidnumber='.$id);
			$ldap_fields = ldap_get_entries($this->ldap, $sri);

			$return_fields[0]['id']     = $ldap_fields[0]['uidnumber'][0];
			$return_fields[0]['lid']    = $ldap_fields[0]['uid'][0];
			$return_fields[0]['tid']    = $ldap_fields[0]['phpgwtypeid'][0];
			$return_fields[0]['owner']  = $ldap_fields[0]['phpgwowner'][0];
			$return_fields[0]['access'] = $ldap_fields[0]['phpgwaccess'][0];
			$return_fields[0]['cat_id'] = $ldap_fields[0]['phpgwcatid'][0];

			if (gettype($stock_fieldnames) == 'array')
			{
				while(list($name,$value)=each($stock_fieldnames))
				{
					$return_fields[0][$name] = $ldap_fields[0][$value][0];
				}
			}

			// Setup address type fields
			if ($return_fields[0]['adr_one_type'])
			{
				$one_type = $return_fields[0]['adr_one_type'];
				reset($this->adr_types);
				while (list($name,$val) = each($this->adr_types))
				{
					eval("if (strstr(\$one_type,\$name)) { \$return_fields[0][\"one_\$name\"] = \"on\"; }");
				}
			}
			if ($return_fields[0]["adr_two_type"])
			{
				$two_type = $return_fields[0]['adr_two_type'];
				reset($this->adr_types);
				while (list($name,$val) = each($this->adr_types))
				{
					eval("if (strstr(\$two_type,\$name)) { \$return_fields[0][\"two_\$name\"] = \"on\"; }");
				}
			}

			$this->db->query("select contact_name,contact_value from $this->ext_table where contact_id='" . $id . "'",__LINE__,__FILE__);
			while ($this->db->next_record())
			{
				// If its not in the list to be returned, don't return it.
				// This is still quicker then 5(+) separate queries
				if ($extra_fields[$this->db->f('contact_name')])
				{
					$return_fields[0][$this->db->f('contact_name')] = $this->db->f('contact_value');
				}
			}
			return $return_fields;
		}

		function read_last_entry($fields = '')
		{
			global $phpgw_info;

			if (!$fields || empty($fields)) { $fields = $this->stock_contact_fields; }
			list($stock_fields,$stock_fieldnames,$extra_fields) =
				$this->split_stock_and_extras($fields);

			if (count($stock_fieldnames))
			{
				$t_fields = "," . implode(",",$stock_fieldnames);
				if ($t_fields == ",")
				{
					unset($t_fields);
				}
			}

			$id = $this->nextid;
			if ($id == -1) { $id = 1; }

			$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], 'uidnumber='.$id);
			$ldap_fields = ldap_get_entries($this->ldap, $sri);

			$return_fields[0]['id']     = $ldap_fields[0]['uidnumber'][0];
			$return_fields[0]['lid']    = $ldap_fields[0]['uid'][0];
			$return_fields[0]['tid']    = $ldap_fields[0]['phpgwtypeid'][0];
			$return_fields[0]['owner']  = $ldap_fields[0]['phpgwowner'][0];
			$return_fields[0]['access'] = $ldap_fields[0]['phpgwaccess'][0];
			$return_fields[0]['cat_id'] = $ldap_fields[0]['phpgwcatid'][0];

			if (gettype($stock_fieldnames) == 'array')
			{
				while(list($name,$value)=each($stock_fieldnames))
				{
					$return_fields[0][$name] = $ldap_fields[0][$value][0];
				}
			}

			// Setup address type fields
			if ($return_fields[0]['adr_one_type'])
			{
				$one_type = $return_fields[0]['adr_one_type'];
				reset($this->adr_types);
				while (list($name,$val) = each($this->adr_types))
				{
					eval("if (strstr(\$one_type,\$name)) { \$return_fields[0][\"one_\$name\"] = \"on\"; }");
				}
			}
			if ($return_fields[0]['adr_two_type'])
			{
				$two_type = $return_fields[0]['adr_two_type'];
				reset($this->adr_types);
				while (list($name,$val) = each($this->adr_types))
				{
					eval("if (strstr(\$two_type,\$name)) { \$return_fields[0][\"two_\$name\"] = \"on\"; }");
				}
			}

			$this->db->query("select contact_name,contact_value from $this->ext_table where contact_id='" . $id . "'",__LINE__,__FILE__);
			while ($this->db->next_record())
			{
				// If its not in the list to be returned, don't return it.
				// This is still quicker then 5(+) separate queries
				if ($extra_fields[$this->db->f('contact_name')])
				{
					$return_fields[0][$this->db->f('contact_name')] = $this->db->f('contact_value');
				}
			}
			return $return_fields;
		}

		// send this the range, query, sort, order and whatever fields you want to see
		// 'rights' and 'access' are  unused at this time
		function read($start=0,$offset=0,$fields="",$query="",$filter="",$sort="",$order="")
		{
			global $phpgw,$phpgw_info;

			if (!$fields || empty($fields)) { $fields = $this->stock_contact_fields; }
			$DEBUG = 0;

			list($stock_fields,$stock_fieldnames,$extra_fields) = $this->split_stock_and_extras($fields);

			$filterfields = array();
			// turn filter's a=b,c=d OR a=b into an array
			if ($filter)
			{
				if ($DEBUG) { echo "DEBUG - Inbound filter is: #".$filter."#"; }
				$filterarray = split(',',$filter);
				if ($filterarray[1])
				{
					$i=0;
					for ($i=0;$i<count($filterarray);$i++)
					{
						list($name,$value) = split("=",$filterarray[$i]);
						if ($name)
						{
							if ($DEBUG) { echo "<br>DEBUG - Filter strings: #".$this->non_contact_fields[$name]."# => #".$value."#"; }
							$filterfields[$this->non_contact_fields[$name]] = $value;
						}
					}
				}
				else
				{
					list($name,$value) = split('=',$filter);
					if ($DEBUG)
					{
						echo "<br>DEBUG - Filter strings: #".$this->non_contact_fields[$name]."# => #".$value."#";
					}
					$filterfields = array($this->non_contact_fields[$name] => $value);
				}
			}
			else
			{
				$filterfields += array('phpgwtypeid' => 'n');
				if ($DEBUG) { echo "<br>DEBUG - Filter strings: #phpgwtypeid=n#"; }
			}	

			if (is_array($this->grants))
			{
				$filterfields += array('phpgwaccess' => 'public');
				$grants = $this->grants;
				while (list($user) = each($grants))
				{
					if ($DEBUG) { echo "<br>DEBUG - Grant from owner: ".$user; }
					$filterfields += array('phpgwowner' => $user);
				}
			}
			//if ($DEBUG) {
			//	while(list($name,$value) = each($filterfields)) {
			//		echo "<br>DEBUG - Filter strings: #".$name.",".$value."#";
			//	}
			//}

			if (!$sort) { $sort = "ASC";  }

			if (!$order)
			{
				$order = "n_family";
			}

			if ($DEBUG && $order)
			{
				echo "<br>DEBUG - ORDER by $order";
			}

			if ($query)
			{
				$ldap_fields = array();
				$total = 0;
				// Query each field seperately instead of using ldap OR search.
				// It seems PHP cannot do this correctly.
				reset($stock_fieldnames);
				while (list($name,$value) = each($stock_fieldnames) )
				{
					$lquery = $value.'=*'.$query.'*';
					//echo $lquery; exit;
					$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], $lquery);
					// append the results
					$ldap_fields += ldap_get_entries($this->ldap, $sri);
					// add the # rows to our total
					$total = $total + ldap_count_entries($this->ldap, $sri);
				}

				if ($filterfields)
				{
					$ldap_fields = $this->filter_ldap($ldap_fields,$filterfields,$DEBUG);
				}

				//echo '<br>first total="'.$total.'"';
				// Now, remove duplicate rows
				//if (floor(phpversion()) == 4) {
			//		$tmp = array_unique($ldap_fields);
			//		$ldap_fields = $tmp;
			//	} else {
					$ldap_fields = $this->asortbyindex($ldap_fields,'uidnumber');
					reset($ldap_fields);
					if (count($ldap_fields) > 0)
					{
						for ($a = 0; $a < count($ldap_fields); $a++)
						{
							if ($ldap_fields[$a])
							{
								//echo '<br>comparing "'.$ldap_fields[$a]['uidnumber'][0]
								//	.'" to "'.$ldap_fields[$a - 1]['uidnumber'][0].'"';
								if (($ldap_fields[$a]['uidnumber'][0] <> $ldap_fields[$a - 1]['uidnumber'][0]))
								{
									$uniquearray[$a] = $ldap_fields[$a];
								}
								else
								{
									//echo '<br>deleting "'.$ldap_fields[$a -1 ]['uidnumber'][0];
								}
							}
						}
						$ldap_fields = $uniquearray;
					}
			//	} // end version check

				$this->total_records = count($ldap_fields);
				//echo '<br>total="'.$this->total_records.'"';
			}
			else
			{
				$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], 'phpgwowner=*');
				$ldap_fields = ldap_get_entries($this->ldap, $sri);
				$this->total_records = ldap_count_entries($this->ldap, $sri);

				if ($filterfields)
				{
					$ldap_fields = $this->filter_ldap($ldap_fields,$filterfields,$DEBUG);
				}
			}

			// Use shared sorting routines, based on sort and order
			if ($sort == 'ASC')
			{
				$ldap_fields = $this->asortbyindex($ldap_fields, $this->stock_contact_fields[$order]);
			}
			else
			{
				$ldap_fields = $this->arsortbyindex($ldap_fields, $this->stock_contact_fields[$order]);
			}

			// This logic allows you to limit rows, or not.
			// The export feature, for example, does not limit rows.
			// This way, it can retrieve all rows at once.
			if ($start && $offset) {
				$limit = $start + $offset;;
			} elseif ($start && !$offset) {
				$limit = $start;
			} elseif(!$start && !$offset) {
				$limit = $this->total_records;
			} else { #(!$start && $offset) {
				$start = 0;
				$limit = $offset;
			}
			//echo '('.$start.','.$limit.')';

			@reset($ldap_fields);
			$j=0;
			for ($i=$start;$i<$limit;$i++)
			{
				if ($i<$this->total_records && $ldap_fields[$i]['uid'][0])
				{
					//echo '<br>j:'.$j;
					// unique id, lid for group/account records,
					// type id (g/u) for groups/accounts, and
					// id of owner/parent for the record
					$return_fields[$j]['id']     = $ldap_fields[$i]['uidnumber'][0];
					$return_fields[$j]['lid']    = $ldap_fields[$i]['uid'][0];
					$return_fields[$j]['tid']    = $ldap_fields[$i]['phpgwtypeid'][0];
					$return_fields[$j]['owner']  = $ldap_fields[$i]['phpgwowner'][0];
					$return_fields[$j]['access'] = $ldap_fields[$i]['phpgwaccess'][0];
					$return_fields[$j]['cat_id'] = $ldap_fields[$i]['phpgwcatid'][0];

					if (gettype($stock_fieldnames) == 'array')
					{
						reset($stock_fieldnames);
						while (list($f_name,$f_value) = each($stock_fieldnames))
						{
							$return_fields[$j][$f_name] = $ldap_fields[$i][$f_value][0];
						}
						reset($stock_fieldnames);
					}
					$this->db->query("SELECT contact_name,contact_value FROM $this->ext_table WHERE contact_id='"
						. $ldap_fields[$i]['id'] . "'",__LINE__,__FILE__);
					while ($this->db->next_record())
					{
						// If its not in the list to be returned, don't return it.
						// This is still quicker then 5(+) separate queries
						if ($extra_fields[$this->db->f('contact_name')])
						{
							$return_fields[$j][$this->db->f('contact_name')] = $this->db->f('contact_value');
						}
					}
					$j++;
				}
			}
			return $return_fields;
		}

		function add($owner,$fields,$access='private',$cat_id='0',$tid='n')
		{
			global $phpgw,$phpgw_info;

			if (!$phpgw_info['server']['ldap_contact_context'])
			{
				return False;
			}

			list($stock_fields,$stock_fieldnames,$extra_fields) = $this->split_stock_and_extras($fields);

			$free = 0;
			$this->nextid = $phpgw->common->last_id('contacts');
			// Loop until we find a free id
			while (!$free)
			{
				$ldap_fields = '';
				$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], 'uidnumber='.$this->nextid);
				$ldap_fields = ldap_get_entries($this->ldap, $sri);
				if ($ldap_fields[0]['dn'][0])
				{
					$this->nextid = $phpgw->common->next_id('contacts');
				}
				else
				{
					$free = True;
				}
			}

			$ldap_fields = '';
			if (gettype($stock_fieldnames) == 'array')
			{
				while(list($name,$value)=each($stock_fieldnames))
				{
					$ldap_fields[$value] = $stock_fields[$name];
				}
			}

			$time = gettimeofday();
			$ldap_fields['uid'] = time().$time['usec'].':'.$ldap_fields['givenname'];
			
			$dn = 'uid=' . $ldap_fields['uid'].',' . $phpgw_info['server']['ldap_contact_context'];
			$ldap_fields['phpgwtypeid']    = $tid;
			$ldap_fields['phpgwowner']     = $owner;
			$ldap_fields['phpgwaccess']    = $access;
			$ldap_fields['phpgwcatid']     = $cat_id;
			$ldap_fields['uidnumber']      = $this->nextid;
			$ldap_fields['objectclass'][0] = 'person';
			$ldap_fields['objectclass'][1] = 'organizationalPerson';
			$ldap_fields['objectclass'][2] = 'inetOrgPerson';
			$ldap_fields['objectclass'][3] = 'phpgwContact';

			/*
			while (list($name,$value) = each($ldap_fields) ) {
				echo '<br>fieldname ="'.$name.'", value ="'.$value.'"';
			}
			exit;
			*/
			$err = ldap_add($this->ldap, $dn, $ldap_fields);

			//$this->db->unlock();
			if (count($extra_fields))
			{
				while (list($name,$value) = each($extra_fields))
				{
					$this->db->query("insert into $this->ext_table values ('".$this->nextid."','" . $this->account_id . "','"
						. addslashes($name) . "','" . addslashes($value) . "')",__LINE__,__FILE__);
				}
			}
		}

		function field_exists($id,$field_name)
		{
			$this->db->query("select count(*) from $this->ext_table where contact_id='$id' and contact_name='"
			. addslashes($field_name) . "'",__LINE__,__FILE__);
			$this->db->next_record();
			return $this->db->f(0);
		}

		function add_single_extra_field($id,$owner,$field_name,$field_value)
		{
			$this->db->query("insert into $this->ext_table values ($id,'$owner','" . addslashes($field_name)
			. "','" . addslashes($field_value) . "')",__LINE__,__FILE__);
		}

		function delete_single_extra_field($id,$field_name)
		{
			$this->db->query("delete from $this->ext_table where contact_id='$id' and contact_name='"
			. addslashes($field_name) . "'",__LINE__,__FILE__);
		}

		function update($id,$owner,$fields,$access='private',$cat_id='0',$tid='n')
		{
			global $phpgw_info;
			$nonfields = $this->non_contact_fields;

			if (!$phpgw_info['server']['ldap_contact_context'])
			{
				return False;
			}

			// First make sure that id number exists
			$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], 'uidnumber='.$id);
			$ldap_fields = ldap_get_entries($this->ldap, $sri);

			if ($ldap_fields[0]['dn'])
			{
				$dn = $ldap_fields[0]['dn'];
				list($stock_fields,$stock_fieldnames,$extra_fields) = $this->split_stock_and_extras($fields);
				if (gettype($stock_fieldnames) == 'array')
				{
					// Check each value, add our extra attributes if they are missing, and
					// otherwise fix the entry while we can.
					//
					// Verify uidnumber
					$stock_fields['id']   = $id;
					if (empty($ldap_fields[0]['uidnumber']))
					{
						$err = ldap_modify($this->ldap,$dn,array('uidnumber'  => $stock_fields['uidnumber']));
					}
					elseif (!$ldap_fields[0]['uidnumber'])
					{
						$err = ldap_mod_add($this->ldap,$dn,array('uidnumber' => $stock_fields['uidnumber']));
					}

					// Verify uid
					$uids = split(',',$dn);
					$stock_fields['lid'] = $uids[0];
					if (empty($ldap_fields[0]['uid']))
					{
						$err = ldap_modify($this->ldap,$dn,array('uid'  => $stock_fields['lid']));
					}
					elseif (!$ldap_fields[0]['uid'])
					{
						$err = ldap_mod_add($this->ldap,$dn,array('uid' => $stock_fields['lid']));
					}

					// Verify objectclasses are there
					if (empty($ldap_fields[0]['objectclass']))
					{
						$stock_fields['objectclass'][0] = 'person';
						$stock_fields['objectclass'][1] = 'organizationalPerson';
						$stock_fields['objectclass'][2] = 'inetOrgPerson';
						$stock_fields['objectclass'][3] = 'phpgwContact';
						$err = ldap_modify($this->ldap,$dn,array('objectclass'  => $stock_fields['objectclass']));
					}
					elseif (!$ldap_fields[0]['objectclass'])
					{
						$stock_fields['objectclass'][0] = 'person';
						$stock_fields['objectclass'][1] = 'organizationalPerson';
						$stock_fields['objectclass'][2] = 'inetOrgPerson';
						$stock_fields['objectclass'][3] = 'phpgwContact';
						$err = ldap_mod_add($this->ldap,$dn,array('objectclass'  => $stock_fields['objectclass']));
					}

					// Verify owner
					$stock_fields['owner']  = $owner;
					if (empty($ldap_fields[0]['phpgwowner']))
					{
						$err = ldap_modify($this->ldap,$dn,array('phpgwowner'  => $stock_fields['owner']));
					}
					elseif (!$ldap_fields[0]['phpgwowner'])
					{
						$err = ldap_mod_add($this->ldap,$dn,array('phpgwowner' => $stock_fields['owner']));
					}

					// Verify access
					$stock_fields['access'] = $access;
					if (empty($ldap_fields[0]['phpgwaccess']))
					{
						$err = ldap_modify($this->ldap,$dn,array('phpgwaccess'  => $stock_fields['access']));
					}
					elseif (!$ldap_fields[0]['phpgwaccess'])
					{
						$err = ldap_mod_add($this->ldap,$dn,array('phpgwaccess' => $stock_fields['access']));
					}

					// Verify cat_id
					$stock_fields['cat_id']  = $cat_id;
					if (empty($ldap_fields[0]['phpgwcatid']))
					{
						$err = ldap_modify($this->ldap,$dn,array('phpgwcatid'  => $stock_fields['cat_id']));
					}
					elseif (!$ldap_fields[0]['phpgwcatid'])
					{
						$err = ldap_mod_add($this->ldap,$dn,array('phpgwcatid' => $stock_fields['cat_id']));
					}

					// Verify tid
					$stock_fields['tid'] = $tid;
					if (empty($ldap_fields[0]['phpgwtypeid']))
					{
						$err = ldap_modify($this->ldap,$dn,array('phpgwtypeid'  => $stock_fields['tid']));
					}
					elseif (!$ldap_fields[0]['phpgwtypeid'])
					{
						$err = ldap_mod_add($this->ldap,$dn,array('phpgwtypeid' => $stock_fields['tid']));
					}

					// OK, just mod the data already
					$allfields = $stock_fieldnames + $nonfields;
					while ( list($fname,$fvalue) = each($allfields) )
					{
						if ($ldap_fields[0][$fvalue])
						{
							//echo "<br>".$fname." => ".$fvalue." was there";
							$err = ldap_modify($this->ldap,$dn,array($fvalue => $stock_fields[$fname]));
						}
						elseif (!$ldap_fields[0][$fvalue])
						{
							//echo "<br>".$fname." not there";
							$err = ldap_mod_add($this->ldap,$dn,array($fvalue => $stock_fields[$fname]));
						}
					}
				}

				while (list($x_name,$x_value) = each($extra_fields))
				{
					if ($this->field_exists($id,$x_name))
					{
						if (! $x_value)
						{
							$this->delete_single_extra_field($id,$x_name);
						}
						else
						{
							$this->db->query("update $this->ext_table set contact_value='" . addslashes($x_value)
							. "',contact_owner='$owner' where contact_name='" . addslashes($x_name)
							. "' and contact_id='$id'",__LINE__,__FILE__);
						}
					}
					else
					{
						$this->add_single_extra_field($id,$owner,$x_name,$x_value);
					}
				}
			}
			else
			{
				return False;
			}
		}

		// Used by admin to change ownership on account delete
		function change_owner($old_owner='',$new_owner='')
		{
			if (!($new_owner && $old_owner))
			{
				return False;
			}

			$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], 'phpgwowner='.$old_owner);
			$ldap_fields = ldap_get_entries($this->ldap, $sri);

			$entry = "";
			while (list($null,$entry) =  each($ldap_fields))
			{
				$err = ldap_modify($this->ldap,$dn,array('phpgwowner' => $new_owner));
			}

			$this->db->query("update $this->ext_table set contact_owner='$new_owner' WHERE contact_owner=$owner",__LINE__,__FILE__);
			return;
		}

		// This is where the real work of delete() is done, shared class file contains calling function
		function delete_($id)
		{
			global $phpgw_info;

			if (!$phpgw_info['server']['ldap_contact_context']) {
				return False;
			}

			$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], 'uidnumber='.$id);
			$ldap_fields = ldap_get_entries($this->ldap, $sri);

			if ($ldap_fields[0]['dn'])
			{
				$err = ldap_delete($this->ldap,$ldap_fields[0]['dn']);

				$this->db->query("delete from $this->ext_table where contact_id='$id' and contact_owner='"
				. $this->account_id . "'",__LINE__,__FILE__);
			}
			else
			{
				return False;
			}
		}

		// This is for the admin script deleteaccount.php
		function delete_all($owner=0)
		{
			global $phpgw_info;

			if (!$phpgw_info['server']['ldap_contact_context'])
			{
				return False;
			}

			if ($owner)
			{
				$sri = ldap_search($this->ldap, $phpgw_info['server']['ldap_contact_context'], 'phpgwowner='.$owner);
				$ldap_fields = ldap_get_entries($this->ldap, $sri);

				$entry = '';
				while (list($null,$entry) =  each($ldap_fields))
				{
					$err = ldap_delete($this->ldap,$entry['dn']);
				}

				$this->db->query("DELETE FROM $this->ext_table WHERE contact_owner=$owner",__LINE__,__FILE__);
			}
			return;
		}
	}
?>
