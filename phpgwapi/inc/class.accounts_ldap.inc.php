<?php
	/**************************************************************************\
	* phpGroupWare API - Accounts manager for LDAP                             *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	*        and Lars Kneschke <lkneschke@phpgw.de>                            *
	*        and Bettina Gille <ceb@phpgroupware.org>                          *
	* View and manipulate account records using LDAP                           *
	* Copyright (C) 2000 - 2002 Joseph Engo, Lars Kneschke                     *
	* Copyright (C) 2003 Lars Kneschke, Bettina Gille                          *
	* ------------------------------------------------------------------------ *
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

	class accounts_
	{
		var $db;
		var $ds;
		var $account_id;
		var $data;
		var $user_context  = '';
		var $group_context = '';
		var $total;

		function accounts_()
		{
			/* This does not get called */
		}

		function read_repository()
		{
			$acct_type = $this->get_type($this->account_id);

			/* search the dn for the given uid */
			if ( ($acct_type == 'g') && $this->group_context )
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber='.$this->account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber='.$this->account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			/* Now dump it into the array; take first entry found */
			if($acct_type =='g')
			{
				$this->data['account_id']   = $allValues[0]['gidnumber'][0];
				$this->data['account_lid']  = $allValues[0]['cn'][0];
				$this->data['firstname']    = utf8_decode($allValues[0]['cn'][0]);
				$this->data['lastname']     = 'Group';
			}
			else
			{
				$this->data['account_id']   		= $allValues[0]['uidnumber'][0];
				$this->data['account_primary_group']	= $allValues[0]['gidnumber'][0];
				$this->data['account_lid']  		= $allValues[0]['uid'][0];
				$this->data['firstname']    		= utf8_decode($allValues[0]['givenname'][0]);
				$this->data['lastname']     		= utf8_decode($allValues[0]['sn'][0]);
			}
			$this->data['account_dn']  = $allValues[0]['dn'];
			$this->data['fullname']    = $allValues[0]['cn'][0];

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'])
			{
				$this->data['homedirectory']  = $allValues[0]['homedirectory'][0];
				$this->data['loginshell'] = $allValues[0]['loginshell'][0];
			}

			$this->data['lastlogin']         = $allValues[0]['phpgwaccountlastlogin'][0];
			$this->data['lastloginfrom']     = $allValues[0]['phpgwaccountlastloginfrom'][0];
			$this->data['lastpasswd_change'] = @$allValues[0]['phpgwlastpasswdchange'][0];
			$this->data['status']            = trim($allValues[0]['phpgwaccountstatus'][0]);
			$this->data['type']              = $allValues[0]['phpgwaccounttype'][0];
			$this->data['expires']           = $allValues[0]['phpgwaccountexpires'][0];

			return $this->data;
		}

		function save_repository()
		{
			#_debug_array($this->data);
			$acct_type = $this->get_type($this->account_id);

			/* search the dn for the given u/gidnumber */
			if ( ($acct_type == 'g') && $this->group_context )
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber='.$this->account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber='.$this->account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			$this->data['account_type'] = $allValues[0]['phpgwaccounttype'][0];

			if($acct_type == 'u')
			{
				// data for posixaccount
				$newData['cn']				= utf8_encode(sprintf("%s %s", 
										$this->data['firstname'], 
										$this->data['lastname']));
				$newData['uid']				= utf8_encode($this->data['account_lid']);
				if($this->data['lastname'])
					$newData['sn']			= utf8_encode($this->data['lastname']);
				if($this->data['firstname'])
					$newData['givenname']		= utf8_encode($this->data['firstname']);
				if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'])
				{
					$newData['homedirectory'] 	= $this->data['homedirectory'];
					$newData['loginshell']    	= $this->data['loginshell'];
				}
				else
				{
					// the posixaccount schema requires this
					$entry['homedirectory'] 	= '/home/'.$this->data['account_lid'];
					$entry['loginshell']		= '/bin/false';
				}
				if($this->data['account_primary_group'])
					$newData['gidnumber']			= $this->data['account_primary_group'];
				if($this->data['lastlogin'])
					$newData['phpgwaccountlastlogin']	= $this->data['lastlogin'];
				if($this->data['lastloginfrom'])
					$newData['phpgwaccountlastloginfrom']	= $this->data['lastloginfrom'];
				if($this->data['lastpasswd_change'])
					$newData['phpgwlastpasswdchange']	= $this->data['lastpasswd_change'];
				if($this->data['status'])
					$newData['phpgwaccountstatus']		= $this->data['status'];
				else
					$newData['phpgwaccountstatus']		= array();
				if($this->data['expires'])
					$newData['phpgwaccountexpires']		= $this->data['expires'];

				$newAccountID					= $newData['uid'];
				$oldAccountID					= $newData['uid'];
			}
			else
			{
				// data for posixgroup
				$newData['cn']				= utf8_encode($this->data['account_lid']);
				$newData['gidnumber']			= $this->data['account_id'];
				$newGroupID				= $newData['cn'];
				$oldGroupID				= $newData['cn'];
			}
			if($this->data['type'])
				$newData['phpgwaccounttype']		= $this->data['type'];

			

			/*
			Changing the uid:  Need to delete and add new, since
			PHP cannot change the dn for the entry.
			*/
			if ($acct_type == 'g')
			{
				$test = $allValues[0]['cn'][0];
			}
			else
			{
				$test = $allValues[0]['uid'][0];
			}
			if (utf8_decode($test) != $this->data['account_lid'])
			{
				$oldData 	= $allValues[0];
				$oldDN		= $oldData['dn'];
				// remove all unneeded fields
				unset($oldData['dn']);
				unset($oldData['count']);
				foreach($oldData as $key => $value)
				{
					if(is_numeric($key))
					{
						// remove the key, its no ldap key
						unset($oldData[$key]);
					}
					else
					{
						// remove the count key
						if($oldData[$key]['count'] == 1)
						{
							$oldData[$key] = $value[0];
						}
						else
						{
							unset($oldData[$key]['count']);
						}
					}
				}

				$oldAccountID	= $oldData['uid'];
				$oldGroupID	= $oldData['cn'];
				
				// merge the old data with the new one
				$newData = array_merge($oldData, $newData);
				
				/* Groups */
				if ($this->data['account_type'] == 'g' && $this->group_context )
				{
					$newDN = 'cn='.$this->data['account_lid'].','.$this->group_context;
					$members = $this->member($this->data['account_id']);
					$newData['memberuid'] = array();
					for ($i=0;$i<count($members);$i++)
					{
						$currname = $this->id2name($members[$i]['account_id']);
						if (!$this->isin_array($currname,$entry['memberuid']))
						{
							$newData['memberuid'][] = $currname;
						}
					}
				}
				/* Accounts */
				else
				{
					$newDN = 'uid='.$this->data['account_lid'].','.$this->user_context;
				}
				// delete the old account
				ldap_delete($this->ds,$oldDN);

				// add the new account
				#_debug_array($newData);
				ldap_add($this->ds, $newDN, $newData);
			}
			/* Normal behavior for save_repository 
			   update Account */
			else
			{
				// add the list group members
				if ($this->data['account_type'] == 'g' && $this->group_context )
				{
					$members = $this->member($this->data['account_id']);
					#_debug_array($members);
					$newData['memberuid'] = array();
					for ($i=0;$i<count($members);$i++)
					{
						$currname = $this->id2name($members[$i]['account_id']);
						if (!$this->isin_array($currname,$newData['memberuid']))
						{
							$newData['memberuid'][] = $currname;
						}
					}
				}
				// modify the DN
				ldap_modify($this->ds, $allValues[0]['dn'], $newData);
				
			}
			
			if ($this->data['account_type'] == 'u')
			{
				// lets check for groups, the user needs to be removed
				
				// first lets search for the groups, the user is currently member of
				// and from which he needs to be removed
				$filter 	= "(&(objectclass=posixgroup)(memberuid=$oldAccountID))";
				$justThese 	= array('memberuid','gidnumber');
				$sri = ldap_search($this->ds, $this->group_context, $filter, $justThese);
				if($sri)
				{
					$allValues = ldap_get_entries($this->ds, $sri);
					if($allValues['count'] > 0)
					{
						unset($allValues['count']);
						foreach($allValues as $key)
						{
							#_debug_array($key);
							#_debug_array($this->data['account_groups']);
							// delete the old accountid from any group
							if($newAccountID != $oldAccountID)
							{
								$dn = $key['dn'];
								$newData = array();
								$newData['memberuid'] = $key['memberuid'];
								unset($newData['memberuid']['count']);
								// remove the uid from memberuid
								$newData['memberuid'] = array_flip($newData['memberuid']); 
								unset($newData['memberuid'][$oldAccountID]);
								$newData['memberuid'] = array_values(array_unique(array_flip($newData['memberuid'])));
								ldap_mod_replace($this->ds, $dn, $newData);
								#print ldap_error($this->ds);
								
							}
							else
							{
								if(!in_array($key['gidnumber'][0],$this->data['account_groups']))
								{
									$dn = $key['dn'];
									$newData = array();
									$newData['memberuid'] = $key['memberuid'];
									unset($newData['memberuid']['count']);
									// remove the uid from memberuid
									$newData['memberuid'] = array_flip($newData['memberuid']); 
									unset($newData['memberuid'][$oldAccountID]);
									$newData['memberuid'] = array_values(array_unique(array_flip($newData['memberuid'])));
									ldap_mod_replace($this->ds, $dn, $newData);
									#print ldap_error($this->ds);
								}
							}
						}
					}
				}        
				
				// lets check group the user needs to be added
				foreach($this->data['account_groups'] as $key => $value)
				{
					// search for the group
					$filter 	= 'gidnumber='.$value;
					$justThese 	= array('memberuid');
					$sri = ldap_search($this->ds, $this->group_context, $filter, $justThese);
					if($sri)
					{
						$allValues = ldap_get_entries($this->ds, $sri);
						// if the user is not member of this group, add him
						if(is_array($allValues[0]['memberuid']))
						{
							// this group has already some members
							if(!in_array($newData['uid'],$allValues[0]['memberuid']))
							{
								$dn = $allValues[0]['dn'];
								$newData = array();
								$newData['memberuid'] = $allValues[0]['memberuid'];
								unset($newData['memberuid']['count']);
								$newData['memberuid'][]	= $newAccountID;
								$newData['memberuid'] = array_values(array_unique($newData['memberuid']));
								ldap_mod_replace($this->ds, $dn, $newData);
							}
						}
						else
						{
							// this group has no members
							$dn = $allValues[0]['dn'];
							$newData = array();
							$newData['memberuid'][] = $newAccountID;
							ldap_mod_replace($this->ds, $dn, $newData);
						}
					}
				}
			}
		}

		function isin_array($needle,$haystack='') 
		{
			if(gettype($haystack) != 'array')
			{
				return False;
			}
			for($i=0;$i<count($haystack) && $haystack[$i] !=$needle;$i++); 
				return ($i!=count($haystack)); 
		}

		function delete($accountid = '')
		{
			$account_id = get_account_id($accountid);
			$account_lid = $this->id2name($account_id);
			
			$filter = 'gidnumber='.$account_id;
			$sri = ldap_search($this->ds, $this->group_context, $filter);
			if($sri)
			{
				$allValues = ldap_get_entries($this->ds, $sri);
			}
			
			if(!$allValues[0]['dn'])
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uid='.$account_lid);
				if($sri)
				{
					$allValues = ldap_get_entries($this->ds, $sri);
					$accountID = $allValues['0']['uid'][0];
					$wasAccount = True;
				}
			}

			if ($allValues[0]['dn'])
			{
				$del = ldap_delete($this->ds, $allValues[0]['dn']);
			}
			
			if($wasAccount)
			{
				// remove the user from any group he is member of
				$filter 	= "(&(objectclass=posixgroup)(memberuid=$accountID))";
				$justThese 	= array('memberuid','gidnumber');
				$sri = ldap_search($this->ds, $this->group_context, $filter, $justThese);
				if($sri)
				{
					$allValues = ldap_get_entries($this->ds, $sri);
					if($allValues['count'] > 0)
					{
						unset($allValues['count']);
						foreach($allValues as $key)
						{
							$dn = $key['dn'];
							$newData = array();
							$newData['memberuid'] = $key['memberuid'];
							unset($newData['memberuid']['count']);
							// remove the uid from memberuid
							$newData['memberuid'] = array_flip($newData['memberuid']); 
							unset($newData['memberuid'][$accountID]);
							$newData['memberuid'] = array_unique(array_flip($newData['memberuid']));
							ldap_mod_replace($this->ds, $dn, $newData);
						}
					}
				}
			}        
		}

		function get_list($_type='both', $start = '',$sort = '', $order = '', $query = '', $offset = '')
		{
			//print "\$_type=$_type, \$start=$start , \$sort=$sort, \$order=$order, \$query=$query, \$offset=$offset<br>";
			$query = strtolower($query);
			if ($offset)
			{
				$limitclause = '';//$phpgw->db->limit($start,$offset);
			}
			elseif ($start && !$offset)
			{
				$limitclause = '';//$phpgw->db->limit($start);
			}

			if (! $sort)
			{
				$sort = '';//"desc";
			}

			if ($_type == 'accounts')
			{
				if(empty($query) || $query == "*")
				{
					$filter = "(&(uidnumber=*)(phpgwaccounttype=u))";
				}
				else
				{
					$filter = "(&(uidnumber=*)(phpgwaccounttype=u)(|(uid=*$query*)(sn=*$query*)(cn=*$query*)(givenname=*$query*)))";
				}
				$sri = ldap_search($this->ds, $this->user_context, $filter);
				$allValues = ldap_get_entries($this->ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					settype($allVals,'array');
					$test = @$allVals['uid'][0];
					if (!$GLOBALS['phpgw_info']['server']['global_denied_users'][$test] && $allVals['uid'][0])
					{
						$accounts[] = Array(
							'account_id'        => $allVals['uidnumber'][0],
							'account_lid'       => $allVals['uid'][0],
							'account_type'      => $allVals['phpgwaccounttype'][0],
							'account_firstname' => utf8_decode($allVals['givenname'][0]),
							'account_lastname'  => utf8_decode($allVals['sn'][0]),
							'account_status'    => $allVals['phpgwaccountstatus'][0]
						);
					}
				}
			}
			elseif ($_type == 'groups')
			{
				if(empty($query) || $query == "*")
				{
					$filter = "(&(gidnumber=*)(phpgwaccounttype=g))";
				}
				else
				{
					$filter = "(&(gidnumber=*)(phpgwaccounttype=g)(|(uid=*$query*)(sn=*$query*)(cn=*$query*)(givenname=*$query*)))";
				}
				$sri = ldap_search($this->ds, $this->group_context, $filter);
				$allValues = ldap_get_entries($this->ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					settype($allVals,'array');
					$test = $allVals['cn'][0];
					if (!$GLOBALS['phpgw_info']['server']['global_denied_groups'][$test] && $allVals['cn'][0])
					{
						$accounts[] = Array(
							'account_id'        => $allVals['gidnumber'][0],
							'account_lid'       => $allVals['cn'][0],
							'account_type'      => $allVals['phpgwaccounttype'][0],
							'account_firstname' => utf8_decode($allVals['givenname'][0]),
							'account_lastname'  => utf8_decode($allVals['sn'][0]),
							'account_status'    => $allVals['phpgwaccountstatus'][0]
						);
					}
				}
			}
			else
			{
				if(empty($query) || $query == "*")
				{
					$filter = "(&(gidnumber=*)(phpgwaccounttype=*))";
				}
				else
				{
					$filter = "(&(gidnumber=*)(phpgwaccounttype=*)(|(uid=*$query*)(sn=*$query*)(cn=*$query*)(givenname=*$query*)))";
				}
				$sri = ldap_search($this->ds, $this->group_context, $filter);
				$allValues = ldap_get_entries($this->ds, $sri);
				while (list($null,$allVals) = @each($allValues))
				{
					settype($allVals,'array');

					if($allVals['phpgwaccounttype'][0] == 'u')
					{
						$_uid = @$allVals['uid'][0];
					}
					else
					{
						$_uid = $allVals['cn'][0];
					}

					if (!$GLOBALS['phpgw_info']['server']['global_denied_groups'][$_uid] && $_uid)
					{
						$accounts[] = Array(
							'account_id'		=> $allVals['gidnumber'][0],
							'account_lid'		=> $_uid,
							'account_type'		=> $allVals['phpgwaccounttype'][0],
							'account_firstname'	=> $allVals['givenname'][0],
							'account_lastname'	=> $allVals['sn'][0],
							'account_status'	=> $allVals['phpgwaccountstatus'][0]
						);
					}
				}
			}

			// sort the array
			$arrayFunctions = CreateObject('phpgwapi.arrayfunctions');
			if(empty($order))
			{
				$order = 'account_lid';
			}
			$sortedAccounts = $arrayFunctions->arfsort($accounts,array($order),$sort);
			$this->total = count($accounts);

			// return only the wanted accounts

			if (is_array($sortedAccounts))
			{
				reset($sortedAccounts);
				if(is_int($start) && is_int($offset))
				{
					return array_slice($sortedAccounts, $start, $offset);
				}
				elseif(is_int($start))
				{
					return array_slice($sortedAccounts, $start, $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']);
				}
				else
				{
					return $sortedAccounts;
				}
			}
			return False;
		}

		function name2id($account_lid)
		{
			static $name_list;

			if(@isset($name_list[$account_lid]) && $name_list[$account_lid])
			{
				return $name_list[$account_lid];
			}

			$sri = ldap_search($this->ds, $this->group_context, "(&(cn=$account_lid)(phpgwaccounttype=g))");
			$allValues = ldap_get_entries($this->ds, $sri);
			
			if (@$allValues[0]['gidnumber'][0])
			{
				$name_list[$account_lid] = intval($allValues[0]['gidnumber'][0]);
			}

			$sri = ldap_search($this->ds, $this->user_context, "(&(uid=$account_lid)(phpgwaccounttype=u))");

			$allValues = ldap_get_entries($this->ds, $sri);

			if (@$allValues[0]['uidnumber'][0])
			{
				$name_list[$account_lid] = intval($allValues[0]['uidnumber'][0]);
			}

			return $name_list[$account_lid];
		}

		function id2name($account_id)
		{
			static $id_list;

			if(isset($id_list[$account_id]))
			{
				return $id_list[$account_id];
			}

			$allValues = array();
			$sri = ldap_search($this->ds, $this->group_context, "(&(gidnumber=$account_id)(phpgwaccounttype=g))");
			$allValues = ldap_get_entries($this->ds, $sri);

			if (@$allValues[0]['cn'][0])
			{
				$id_list[$account_id] = $allValues[0]['cn'][0];
				return $id_list[$account_id];
			}

			$allValues = array();
			$sri = ldap_search($this->ds, $this->user_context, "(&(uidnumber=$account_id)(phpgwaccounttype=u))");
			$allValues = ldap_get_entries($this->ds, $sri);

			if (@$allValues[0]['uid'][0])
			{
				$id_list[$account_id] = $allValues[0]['uid'][0];
				return $id_list[$account_id];
			}

			return $id_list[$account_id];
		}

		function get_type($accountid = '')
		{
			static $account_type;
			$account_id = get_account_id($accountid);

			if (isset($this->account_type) && $account_id == $this->account_id)
			{
				return $this->account_type;
			}

			if(@isset($account_type[$account_id]) && @$account_type[$account_id])
			{
				return $account_type[$account_id];
			}
			$allValues = array();
			$sri = ldap_search($this->ds, $this->user_context, "(&(uidnumber=$account_id)(phpgwaccounttype=u))");
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($allValues[0]['phpgwaccounttype'][0])
			{
				$allValues[0]['phpgwaccounttype'][0];
				$account_type[$account_id] = $allValues[0]['phpgwaccounttype'][0];
				return $account_type[$account_id];
			}

			$allValues = array();
			$sri = ldap_search($this->ds, $this->group_context, "(&(gidnumber=$account_id)(phpgwaccounttype=g))");
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($allValues[0]['phpgwaccounttype'][0])
			{
				$account_type[$account_id] = $allValues[0]['phpgwaccounttype'][0];
				return $account_type[$account_id];
			}
			return $account_type[$account_id];
		}

		/*
		 * returns nonzero if $account exists in LDAP: 0: nowhere 1: user accounts, 2: group accounts, 3: both
		 * $account can be an account_id (LDAP: uidnumber) or an account_lid (LDAP: uid) (is determinded by gettype($account) == 'integer')
		 */
		function exists($account)
		{
			/* This sets up internal caching variables for this functon */
			static $by_id, $by_lid;
			$users  = array();
			$groups = array();

			if(is_int($account))
			{
				$ldapgroup = 'gidnumber';
				$ldapacct  = 'uidnumber';
				/* If data is cached, use it. */
				if(@isset($by_id[$account]) && @$by_id[$account])
				{
					return $by_id[$account];
				}
			}
			else
			{
				$ldapgroup = 'cn';
				$ldapacct  = 'uid';
				/* If data is cached, use it. */
				if(@isset($by_lid[$account]) && @$by_lid[$account])
				{
					return $by_lid[$account];
				}
			}

			$acct_type = $this->get_type($account);

			if ($acct_type == 'g' && $this->group_context)
			{
				$sri = ldap_search($this->ds, $this->group_context, $ldapgroup . '=' . $account);
				$groups = ldap_get_entries($this->ds, $sri);
			}
			$sri = ldap_search($this->ds, $this->user_context, $ldapacct . '=' . $account);
			$users = ldap_get_entries($this->ds, $sri);

			if ($users[0]['dn'])
			{
				$in += 1;
			}
			if ($groups[0]['dn'])
			{
				$in += 2;
			}
			/* This sets up internal caching for this function */
			if($ldapgroup == 'gidnumber')
			{
				$by_id[$account] = $in;
				$by_lid[$this->id2name($account)] = $in;
			}
			else
			{
				$by_lid[$account] = $in;
				$by_id[$this->name2id($account)] = $in;
			}

			return $in;
		}

		function create($account_info,$default_prefs=True)
		{
			/* echo '<br>in create for account_lid: "'.$account_lid.'"'; */
			if (empty($account_info['account_id']) || !$account_info['account_id'])
			{
				$account_id = $this->get_nextid($account_info['account_type']);
				/* echo '<br>using'.$account_id;exit; */
			}
			else
			{
				$account_id = $account_info['account_id'];
			}
			$entry['userpassword']              = $account_info['account_passwd'];
			$entry['phpgwaccounttype']          = $account_info['account_type'];
			$entry['phpgwaccountexpires']       = $account_info['account_expires'];

			if ($account_info['account_type'] == 'g')
			{
				$sri = ldap_search($this->ds, $this->group_context, 'cn=' . $account_info['account_lid']);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uid=' . $account_info['account_lid']);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'] && $account_info['account_type'] != 'g')
			{
				$entry['homedirectory'] = $account_info['homedirectory'] && $account_info['homedirectory'] != $GLOBALS['phpgw_info']['server']['ldap_account_home'] ? $account_info['homedirectory'] : $GLOBALS['phpgw_info']['server']['ldap_account_home'].SEP.$account_info['account_lid'];
				$entry['loginshell'] = $account_info['loginshell'] ? $account_info['loginshell'] : $GLOBALS['phpgw_info']['server']['ldap_account_shell'];
			}
			elseif($account_info['account_type'] != 'g')
			{
				$entry['homedirectory'] = '/home/'.$account_info['account_lid'];
				$entry['loginshell'] = '/bin/false';
			}

			if ($allValues[0]['dn'])
			{
				/* This should keep the password from being overwritten here on ldap import */
				unset($entry['userpassword']);
				$entry['gidnumber'] = $account_id;

				while (list($key,$val) = each($entry))
				{
					$tmpentry = '';
					$tmpentry[$key] = trim($val); /* must trim! */
					/* echo '<br>'.$key.' '.$val; */
					if ($tmpentry[$key])
					{
						if (!$allValues[0][$key][0])
						{
							/* attribute was not in LDAP, add it */
							ldap_mod_add($this->ds, $allValues[0]['dn'], $tmpentry);
						}
						else
						{
							/* attribute was in LDAP, modify it */
							ldap_modify($this->ds, $allValues[0]['dn'], $tmpentry);
						}
					}
				}

				if ($account_info['account_type'] == 'g')
				{
					$tmpentry['objectclass'][0] = 'top';
					$tmpentry['objectclass'][1] = 'posixGroup';
					$tmpentry['objectclass'][2] = 'phpgwAccount';
				}
				else
				{
					$tmpentry['uidnumber']      = $account_id;
					$tmpentry['objectclass'][0] = 'top';
					$tmpentry['objectclass'][1] = 'person';
					$tmpentry['objectclass'][2] = 'organizationalPerson';
					$tmpentry['objectclass'][3] = 'inetOrgPerson';
					$tmpentry['userpassword']   = $GLOBALS['phpgw']->common->encrypt_password($account_info['account_passwd']);
					/* $tmpentry['objectclass'][4] = 'account'; Causes problems with some LDAP servers */
					$tmpentry['objectclass'][4] = 'posixAccount';
					$tmpentry['objectclass'][5] = 'shadowAccount';
					$tmpentry['objectclass'][6] = 'phpgwAccount';
					$tmpentry['phpgwaccountstatus']    = $account_info['account_status'];
					$tmpentry['phpgwaccounttype']      = $account_info['account_type'];
					$tmpentry['phpgwaccountexpires']   = $account_info['account_expires'];
				}
				ldap_modify($this->ds, $allValues[0]['dn'], $tmpentry);
			}
			else
			{
				/* Not already there, we will add it */
				if ($account_info['account_type'] == 'g')
				{
					$dn = 'cn='.$account_info['account_lid'] . ',' . $this->group_context;
					unset($entry['homedirectory']);
					unset($entry['loginshell']);
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'posixGroup';
					$entry['objectclass'][2] = 'phpgwAccount';
					$entry['cn']             = utf8_encode($account_info['account_lid']);
					$entry['gidnumber']      = $account_id;
					#$entry['userpassword']   = $GLOBALS['phpgw']->common->encrypt_password($account_info['account_passwd']);
					$entry['description']    = 'phpgw-created group';
				}
				else
				{
					$dn = 'uid=' . $account_info['account_lid'] . ',' . $this->user_context;
					
					$entry['cn']	= utf8_encode(sprintf("%s %s",
						$account_info['account_firstname'],
						$account_info['account_lastname']
					));
								
					$entry['sn']	= utf8_encode($account_info['account_lastname']);
						
					if($account_info['account_firstname'])
						$entry['givenname']	= utf8_encode($account_info['account_firstname']);
						
					$entry['uid']       	= $account_info['account_lid'];
					$entry['uidnumber'] 	= $account_id;
					$entry['gidnumber']	= $account_info['account_primary_group'];
					$entry['userpassword']   = $GLOBALS['phpgw']->common->encrypt_password($account_info['account_passwd']);
					$entry['objectclass'][0] = 'top';
					$entry['objectclass'][1] = 'person';
					$entry['objectclass'][2] = 'organizationalPerson';
					$entry['objectclass'][3] = 'inetOrgPerson';
					$entry['objectclass'][4] = 'posixAccount';
					$entry['objectclass'][5] = 'shadowAccount';
					$entry['objectclass'][6] = 'phpgwAccount';
					if($account_info['account_status'])
						$entry['phpgwaccountstatus']    = $account_info['account_status'];
					$entry['phpgwaccounttype']      = $account_info['account_type'];
					$entry['phpgwaccountexpires']   = $account_info['account_expires'];
				}

				#_debug_array($entry);

				// stop processing if ldap_add fails
				if(!ldap_add($this->ds, $dn, $entry))
				{
					return false;
				}
			}
			// print ldap_error($this->ds);

			// lets check group the user needs to be added
			if($account_info['account_type'] == 'u')
			{
				foreach($account_info['account_groups'] as $key => $value)
				{
					// search for the group
					$filter 	= 'gidnumber='.$value;
					$justThese 	= array('memberuid');
					$sri = ldap_search($this->ds, $this->group_context, $filter, $justThese);
					if($sri)
					{
						$allValues = ldap_get_entries($this->ds, $sri);
						// if the user is not member of this group, add him
						if(is_array($allValues[0]['memberuid']))
						{
							// this group has already some members
							if(!in_array($account_info['account_lid'],$allValues[0]['memberuid']))
							{
								$dn = $allValues[0]['dn'];
								$newData = array();
								$newData['memberuid'] = $allValues[0]['memberuid'];
								unset($newData['memberuid']['count']);
								$newData['memberuid'][]	= $account_info['account_lid'];
								$newData['memberuid'] = array_unique($newData['memberuid']);
								ldap_mod_replace($this->ds, $dn, $newData);
								#print ldap_error($this->ds)."<br>";
							}
						}
						else
						{
							// this group has no members
							$dn = $allValues[0]['dn'];
							$newData = array();
							$newData['memberuid'][] = $account_info['account_lid'];
							ldap_mod_replace($this->ds, $dn, $newData);
						}
					}
				}
			}
			
			if($account_id && is_object($GLOBALS['phpgw']->preferences) && $default_prefs)
			{
				$GLOBALS['phpgw']->preferences->create_defaults($account_id);
			}
			
			return $account_id;
		}

		function auto_add($accountname, $passwd, $default_prefs = False, $default_acls = False, $expiredate = 0, $account_status = 'A')
		{
			if ($expiredate)
			{
				$expires = mktime(2,0,0,date('n',$expiredate), intval(date('d',$expiredate)), date('Y',$expiredate));
			}
			else
			{
				if($GLOBALS['phpgw_info']['server']['auto_create_expire'])
				{
					if($GLOBALS['phpgw_info']['server']['auto_create_expire'] == 'never')
					{
						$expires = -1;
					}
					else
					{
						$expiredate = time() + $GLOBALS['phpgw_info']['server']['auto_create_expire'];
						$expires   = mktime(2,0,0,date('n',$expiredate), intval(date('d',$expiredate)), date('Y',$expiredate));
					}
				}
				else
				{
					/* expire in 30 days by default */
					$expiredate = time() + ( ( 60 * 60 ) * (30 * 24) );
					$expires   = mktime(2,0,0,date('n',$expiredate), intval(date('d',$expiredate)), date('Y',$expiredate));
				}
			}

			$acct_info = array(
				'account_lid'       => $accountname,
				'account_type'      => 'u',
				'account_passwd'    => $passwd,
				'account_firstname' => 'New',
				'account_lastname'  => 'User',
				'account_status'    => $account_status,
				'account_expires'   => $expires
			);
			$this->create($acct_info,$default_prefs);
			$accountid = $this->name2id($accountname);

			$this->db->transaction_begin();
			if ($default_acls == False)
			{
				$apps = Array(
					'addressbook',
					'calendar',
					'email',
					'notes',
					'todo',
					'phpwebhosting',
					'manual'
				);

				$default_group_lid = $GLOBALS['phpgw_info']['server']['default_group_lid'];
				$default_group_id  = $this->name2id($default_group_lid);
				$defaultgroupid = $default_group_id ? $default_group_id : $this->name2id('Default');
				if($defaultgroupid)
				{
					$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', "
						. $defaultgroupid . ", " . $accountid . ", 1)",__LINE__,__FILE__);
				}
				$this->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)values('preferences', 'changepassword', ".$accountid.", 1)",__LINE__,__FILE__);
				@reset($apps);
				while(list($key,$app) = each($apps))
				{
					$this->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('".$app."', 'run', ".$accountid.", 1)",__LINE__,__FILE__);
				}
			}
			$this->db->transaction_commit();
			return $accountid;
		}

		function get_account_name($accountid,&$lid,&$fname,&$lname)
		{
			static $account_name;
			
			$account_id = get_account_id($accountid);
			if(isset($account_name[$account_id]))
			{
				$lid = $account_name[$account_id]['lid'];
				$fname = $account_name[$account_id]['fname'];
				$lname = $account_name[$account_id]['lname'];
				return;
			}
			$acct_type = $this->get_type($account_id);

			/* search the dn for the given uid */
			if ( ($acct_type == 'g') && $this->group_context )
			{
				$sri = ldap_search($this->ds, $this->group_context, 'gidnumber='.$account_id);
			}
			else
			{
				$sri = ldap_search($this->ds, $this->user_context, 'uidnumber='.$account_id);
			}
			$allValues = ldap_get_entries($this->ds, $sri);

			if($acct_type =='g')
			{
				$account_name[$account_id]['lid']   = $allValues[0]['cn'][0];
				$account_name[$account_id]['fname'] = $allValues[0]['cn'][0];
				$account_name[$account_id]['lname'] = 'Group';
			}
			else
			{
				$account_name[$account_id]['lid']   = $allValues[0]['uid'][0];
				$account_name[$account_id]['fname'] = $allValues[0]['givenname'][0];
				$account_name[$account_id]['lname'] = $allValues[0]['sn'][0];
			}
			$lid = $account_name[$account_id]['lid'];
			$fname = $account_name[$account_id]['fname'];
			$lname = $account_name[$account_id]['lname'];
			return;
		}

		function get_account_data($account_id)
		{
			$this->account_id = $account_id;
			$this->read_repository();

			$data[$this->data['account_id']]['lid']       = $this->data['account_lid'];
			$data[$this->data['account_id']]['firstname'] = $this->data['firstname'];
			$data[$this->data['account_id']]['lastname']  = $this->data['lastname'];
			$data[$this->data['account_id']]['fullname']  = $this->data['fullname'];
			$data[$this->data['account_id']]['type']      = $this->data['account_type'];

			return $data;
		}

		function getDNforID($_accountid = '')
		{
			$_account_id = get_account_id($_accountid);

			$sri = ldap_search($this->ds, $this->user_context, "uidnumber=$_account_id");
			$allValues = ldap_get_entries($this->ds, $sri);

			return $allValues[0]['dn'];
		}
	}
